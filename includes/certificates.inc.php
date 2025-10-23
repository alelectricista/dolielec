<?php
// custom/dolielec/includes/certificates.inc.php
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/class/doc.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';
global $db, $conf, $langs, $user, $mysoc;
$langs->loadLangs(array('main','companies','dolielec@dolielec'));
if (empty($user->rights->dolielec->read)) accessforbidden();
$action  = GETPOST('action','alpha');
$socid   = GETPOST('socid','int');
$backurl = !empty($socid) ? dol_buildpath('/societe/card.php',1).'?id='.$socid.'&tab=documents' : dol_buildpath('/custom/dolielec/doc.php',1).'?tab=brie';
$doc  = new Documentation($db);
$inst = $doc->getInst();
// Directorios base (convención Dolibarr)
$modroot   = !empty($conf->dolielec->dir_output) ? $conf->dolielec->dir_output : DOL_DATA_ROOT.'/dolielec';
$tmpDir    = $modroot.'/var/tmp';
$signedDir = $modroot.'/docs/signed';
$certDir   = $modroot.'/docs/cert';
dol_mkdir($tmpDir);
dol_mkdir($signedDir);
dol_mkdir($certDir);
// Ruta plantilla (correcta: doctemplates)
$templateBrie = DOL_DATA_ROOT.'/doctemplates/dolielec/brie_template.odt';
if ($action === 'brie_save') {
    // CSRF
    $token = GETPOST('token','alphanohtml');
    if (empty($token) || !dol_verifyToken($token)) accessforbidden();
    $form = GETPOST('form','array');
    $out  = GETPOST('out','array');
    // 1) Construir datos
    $data = $doc->setBrie($form, $socid);
    // 2) Destinatarios
    $targets = array();
    if (!empty($out['TITULAR']))        $targets[] = 'TITULAR';
    if (!empty($out['DISTRIBUIDORA']))  $targets[] = 'DISTRIBUIDORA';
    if (!empty($out['INSTALADOR']))     $targets[] = 'INSTALADOR';
    if (empty($targets)) $targets = array('TITULAR');
    // 3) Generación documentos
    $generated = array();   // signed pdfs absolutos

    $cupsSafe   = dol_sanitizeFileName(!empty($data['CUPS']) ? $data['CUPS'] : 'SIN_CUPS');
    $dateSafe   = dol_print_date(dol_now(), '%Y%m%d');
    $baseName   = 'brie_'.$cupsSafe.'_'.$dateSafe;
    // Plantilla debe existir
    if (!is_readable($templateBrie)) {
        setEventMessages($langs->trans('TemplateNotFound').' '.$templateBrie, null, 'errors');
        header('Location: '.$backurl);
        exit;
    }
    foreach ($targets as $tg) {
        $fname = $baseName.'_'.$tg;
        // ODT
        $odtOut = $tmpDir.'/'.$fname.'.odt';
        // >>> orden correcto: (template, output, data)
        $resODT = $doc->renderODT($templateBrie, $odtOut, $data);
        if (empty($resODT['success'])) {
            setEventMessages($langs->trans('Error').' ODT: '.$resODT['message'], null, 'errors');
            continue;
        }
        // PDF
        $pdfTmp = $tmpDir.'/'.$fname.'.pdf';
        $resPDF = $doc->odtToPdf($odtOut, $pdfTmp);
        if (empty($resPDF['success'])) {
            setEventMessages($langs->trans('Error').' PDF: '.$resPDF['message'], null, 'errors');
            continue;
        }
        // Firmar
        try {
            $signedOut = $signedDir.'/'.$fname.'-signed.pdf';
            $ok = $doc->setSign($pdfTmp, $signedOut);
            if ($ok && is_readable($signedOut)) {
                $generated[] = $signedOut;
            } else {
                setEventMessages($langs->trans('ErrorFileNotFound').' (signed pdf)', null, 'errors');
            }
        } catch (Exception $e) {
            setEventMessages($e->getMessage(), null, 'errors');
        }
    }

    // 4) Destino
    $dest = !empty($out['DEST']) ? $out['DEST'] : 'ECM';

    if ($dest === 'ECM') {
        // Guardar/adjuntar en ECM (si hay tercero)
        if (!empty($socid)) {
            foreach ($generated as $abs) {
                $rel = $doc->attachToThirdparty($socid, $abs);
                if ($rel === '') {
                    setEventMessages($langs->trans('Error').' ECM attach: '.basename($abs), null, 'warnings');
                }
            }
            setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
            header('Location: '.$backurl);
            exit;
        } else {
            // Sin tercero, igualmente deja los ficheros en signed y muestra mensaje con nombres
            $names = array_map('basename', $generated);
            setEventMessages($langs->trans('RecordSaved').' — '.implode(', ', $names), null, 'mesgs');
            header('Location: '.$backurl);
            exit;
        }
    } else {
        // SIGNED: preparar descarga (si >1 => ZIP)
        if (count($generated) === 0) {
            setEventMessages($langs->trans('ErrorFileNotFound'), null, 'errors');
            header('Location: '.$backurl);
            exit;
        }
        if (count($generated) === 1) {
            $abs = $generated[0];
            $bn  = basename($abs);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="'.dol_escape_htmltag($bn).'"');
            header('Content-Length: '.filesize($abs));
            readfile($abs);
            exit;
        } else {
            $zipPath = $tmpDir.'/'.$baseName.'_signed.zip';
            if (file_exists($zipPath)) @unlink($zipPath);
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                foreach ($generated as $abs) {
                    $zip->addFile($abs, basename($abs));
                }
                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="'.dol_escape_htmltag(basename($zipPath)).'"');
                header('Content-Length: '.filesize($zipPath));
                readfile($zipPath);
                @unlink($zipPath);
                exit;
            } else {
                // Si falla el zip, muestra links en mensaje y vuelve
                $names = array_map('basename', $generated);
                setEventMessages($langs->trans('RecordSaved').' — '.implode(', ', $names), null, 'mesgs');
                header('Location: '.$backurl);
                exit;
            }
        }
    }
}

if ($action === 'brie' || empty($action) || $action === 'brie_save') {
    $third = null;
    if (!empty($socid)) {
        $third = new Societe($db);
        $third->fetch($socid);
    }

    // Prefills
    $valueDate       = dol_print_date(dol_now(), '%Y-%m-%d'); // HTML date
    $isPerson        = ($inst['type'] === 'person');
    $prefillTechName = $isPerson ? dol_escape_htmltag(trim((!empty($inst['firstname'])?$inst['firstname']:'').' '.(!empty($inst['lastname'])?$inst['lastname']:''))) : '';
    $prefillTechDni  = $isPerson ? dol_escape_htmltag($inst['nif']) : '';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="brie_save">';
    if (!empty($socid)) print '<input type="hidden" name="socid" value="'.$socid.'">';
    print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';

    // Fecha
    print '<tr class="liste_titre"><th colspan="4">'.$langs->trans("Date").'</th></tr>';
    print '<tr><td class="titlefield"><label for="brie_date">'.$langs->trans("Date").' *</label></td>';
    print '<td colspan="3"><input type="date" id="brie_date" name="form[BRIE_FECHA]" value="'.$valueDate.'" required></td></tr>';

    // Identificación
    print '<tr class="liste_titre"><th colspan="4">'.$langs->trans("BRIEIdentification").'</th></tr>';
    print '<tr><td class="titlefield"><label for="cups">'.$langs->trans("CUPS").' *</label></td><td colspan="3"><input type="text" id="cups" name="form[CUPS]" required class="quatrevingtpercent"></td></tr>';

    // Datos del titular
    print '<tr class="liste_titre"><th colspan="4">'.$langs->trans("BRIEOwnerData").'</th></tr>';
    if (!empty($third) && $third->id > 0) {
        print '<tr><td class="titlefield">'.$langs->trans("Name").' *</td><td>'.dol_escape_htmltag($third->name).'</td>';
        print '<td>'.$langs->trans("Email").'</td><td>'.dol_escape_htmltag($third->email).'</td></tr>';
        print '<tr><td>'.$langs->transcountry("ProfId1", !empty($third->country_id) ? $third->country_id : $mysoc->country_id).' *</td><td>'.dol_escape_htmltag($third->idprof1).'</td>';
        print '<td>'.$langs->trans("Phone").' *</td><td>'.dol_escape_htmltag($third->phone).'</td></tr>';
        print '<tr><td>'.$langs->trans("Address").' *</td><td colspan="3">'.dol_escape_htmltag($third->address).'</td></tr>';
        print '<tr><td>'.$langs->trans("Zip").' *</td><td>'.dol_escape_htmltag($third->zip).'</td>';
        print '<td>'.$langs->trans("Town").' *</td><td>'.dol_escape_htmltag($third->town).'</td></tr>';
        print '<input type="hidden" name="form[TIT_NIF]" value="'.dol_escape_htmltag($third->idprof1).'">';
        print '<input type="hidden" name="form[TIT_NOMBRE]" value="'.dol_escape_htmltag($third->name).'">';
        print '<input type="hidden" name="form[TIT_TLF]" value="'.dol_escape_htmltag($third->phone).'">';
        print '<input type="hidden" name="form[TIT_DOM]" value="'.dol_escape_htmltag($third->address).'">';
        print '<input type="hidden" name="form[TIT_CP]" value="'.dol_escape_htmltag($third->zip).'">';
        print '<input type="hidden" name="form[TIT_LOCALIDAD]" value="'.dol_escape_htmltag($third->town).'">';
    } else {
        print '<tr><td class="titlefield"><label for="tit_nombre">'.$langs->trans("Name").' *</label></td><td><input type="text" id="tit_nombre" name="form[TIT_NOMBRE]" required></td>';
        print '<td><label for="tit_nif">'.$langs->transcountry("ProfId1", $mysoc->country_id).' *</label></td><td><input type="text" id="tit_nif" name="form[TIT_NIF]" required></td></tr>';
        print '<tr><td><label for="tit_tel">'.$langs->trans("Phone").' *</label></td><td><input type="text" id="tit_tel" name="form[TIT_TLF]" required></td>';
        print '<td><label for="tit_email">'.$langs->trans("Email").'</label></td><td><input type="email" id="tit_email" name="form[TIT_EMAIL]"></td></tr>';
        print '<tr><td><label for="tit_dom">'.$langs->trans("Address").' *</label></td><td colspan="3"><input type="text" id="tit_dom" name="form[TIT_DOM]" required class="quatrevingtpercent"></td></tr>';
        print '<tr><td><label for="tit_cp">'.$langs->trans("Zip").' *</label></td><td><input type="text" id="tit_cp" name="form[TIT_CP]" required></td>';
        print '<td><label for="tit_loc">'.$langs->trans("Town").' *</label></td><td><input type="text" id="tit_loc" name="form[TIT_LOCALIDAD]" required></td></tr>';
    }

    // Dirección instalación
    print '<tr class="liste_titre"><th colspan="4">'.$langs->trans("BRIEInstallationPlace").'</th></tr>';
    print '<tr><td class="titlefield"><label for="via">'.$langs->trans("Street").' *</label></td><td><input type="text" id="via" name="form[VIA]" required></td>';
    print '<td><label for="num">'.$langs->trans("Number").' *</label></td><td><input type="text" id="num" name="form[NUM]" required></td></tr>';
    print '<tr><td><label for="piso">'.$langs->trans("Floor").'</label></td><td><input type="text" id="piso" name="form[PISO]"></td>';
    print '<td><label for="porta">'.$langs->trans("Door").' *</label></td><td><input type="text" id="porta" name="form[PORTA]" required></td></tr>';
    print '<tr><td><label for="cp">'.$langs->trans("Zip").' *</label></td><td><input type="text" id="cp" name="form[CP]" required></td>';
    print '<td><label for="loc">'.$langs->trans("Town").' *</label></td><td><input type="text" id="loc" name="form[LOCALIDAD]" required></td></tr>';
    print '<tr><td><label for="act_ant">'.$langs->trans("PreviousActivity").'</label></td><td><input type="text" id="act_ant" name="form[ACTIVIDAD_ANT]"></td>';
    print '<td><label for="act_act">'.$langs->trans("NewActivity").'</label></td><td><input type="text" id="act_act" name="form[ACTIVIDAD_ACT]"></td></tr>';
    print '<tr><td><label for="superf">'.$langs->trans("Surface").' (m²) *</label></td><td><input type="number" id="superf" name="form[SUPERF]" min="0" step="1" required></td><td></td><td></td></tr>';

    // Datos técnicos
    print '<tr class="liste_titre"><th colspan="4">'.$langs->trans("BRIETechnicalData").'</th></tr>';
    print '<tr><td class="titlefield"><label for="dif_num">Interruptors diferencials — '.$langs->trans("RCDNumber").' *</label></td><td><input type="number" id="dif_num" name="form[DIF_NUM]" min="0" step="1" required></td>';
    print '<td><label for="dif_sens">'.$langs->trans("Sensitivity").' (mA) *</label></td><td><input type="number" id="dif_sens" name="form[DIF_SENS]" min="0" step="1" required></td></tr>';
    print '<tr><td class="titlefield"><label for="di_sec">'.$langs->trans("Section").' (mm²) *</label></td><td><input type="text" id="di_sec" name="form[DI_SECCION]" required></td><td></td><td></td></tr>';
    print '<tr><td class="titlefield"><label for="ten_ant">'.$langs->trans("PreviousVoltage").' *</label></td><td><input type="text" id="ten_ant" name="form[TENSION_ANT]" required></td>';
    print '<td><label for="ten_act">'.$langs->trans("NewVoltage").' *</label></td><td><input type="text" id="ten_act" name="form[TENSION_ACT]" required></td></tr>';
    print '<tr><td class="titlefield"><label for="pot_max">'.$langs->trans("MaxPowerKW").' *</label></td><td><input type="number" id="pot_max" name="form[POT_MAX]" step="0.01" min="0" required></td>';
    print '<td><label for="pot_ini">'.$langs->trans("InitialPowerKW").' *</label></td><td><input type="number" id="pot_ini" name="form[POT_INICIAL]" step="0.01" min="0" required></td></tr>';
    print '<tr><td class="titlefield"><label for="pot_con">'.$langs->trans("ContractedPowerKW").' *</label></td><td><input type="number" id="pot_con" name="form[POT_CONTRATAR]" step="0.01" min="0" required></td>';
    print '<td><label for="prot_tipo">Protecció general</label></td><td>'.
        '<span class="opacitymedium">IGA / ICPM</span><br>'.
        '<label><input type="radio" name="form[PROT_GEN_TIPO]" value="IGA" required> IGA</label>'.
        '<label class="marginleftonly"><input type="radio" name="form[PROT_GEN_TIPO]" value="ICPM"> ICPM</label> '.
        '<label for="prot_int">'.$langs->trans("NominalIntensity").' (A) *</label> '.
        '<input type="number" id="prot_int" name="form[PROT_GEN_INT]" min="0" step="1" required>'.
    '</td></tr>';

    // Tierra / Aislamiento / Ascensor
    print '<tr><td class="titlefield"><span id="lbl_tierra">'.$langs->trans("EarthingExists").' *</span></td><td>'.
         '<div role="group" aria-labelledby="lbl_tierra">'.
         '  <input type="radio" id="tierra_si" name="form[TIERRA_EXISTE]" value="SI" required aria-labelledby="lbl_tierra"> <label for="tierra_si">'.$langs->trans("Yes").'</label>'.
         '  <input type="radio" id="tierra_no" name="form[TIERRA_EXISTE]" value="NO" aria-labelledby="lbl_tierra" class="marginleftonly"> <label for="tierra_no">'.$langs->trans("No").'</label>'.
         '</div>'.
    '</td>';
    print '<td><label for="tierra_val">'.$langs->trans("EarthingOhms").' *</label></td><td>'.
        '<input type="number" id="tierra_val" name="form[TIERRA_VALOR]" step="0.01" min="0" required>'.
    '</td></tr>';

    print '<tr><td class="titlefield"><span id="lbl_aisla">'.$langs->trans("Insulation").' *</span></td><td>'.
         '<div role="group" aria-labelledby="lbl_aisla">'.
         '  <input type="radio" id="aisla_si" name="form[AISLAMIENTO]" value="SI" required aria-labelledby="lbl_aisla"> <label for="aisla_si">'.$langs->trans("Yes").'</label>'.
         '  <input type="radio" id="aisla_no" name="form[AISLAMIENTO]" value="NO" aria-labelledby="lbl_aisla" class="marginleftonly"> <label for="aisla_no">'.$langs->trans("No").'</label>'.
         '</div>'.
    '</td>';
    print '<td><label for="aisla_val">'.$langs->trans("Resistance").' (Ω) *</label></td><td>'.
        '<input type="number" id="aisla_val" name="form[AISLAMIENTO_VALOR]" step="1" min="0" required>'.
    '</td></tr>';

    print '<tr><td class="titlefield"><span id="lbl_asc">'.$langs->trans("Lift").' *</span></td><td>'.
         '<div role="group" aria-labelledby="lbl_asc">'.
         '  <input type="radio" id="asc_si" name="form[ASCENSOR]" value="SI" required aria-labelledby="lbl_asc"> <label for="asc_si">'.$langs->trans("Yes").'</label>'.
         '  <input type="radio" id="asc_no" name="form[ASCENSOR]" value="NO" aria-labelledby="lbl_asc" class="marginleftonly"> <label for="asc_no">'.$langs->trans("No").'</label>'.
         '</div>'.
    '</td>';
    print '<td><label for="pot_asc">'.$langs->trans("LiftPowerKW").' *</label></td><td>'.
        '<input type="number" id="pot_asc" name="form[POT_ASCENSOR]" step="0.01" min="0" required>'.
    '</td></tr>';

    // Defectos (8)
    print '<tr class="liste_titre"><th colspan="4">'.$langs->trans("BRIEDefects").'</th></tr>';
    $def = array(
        'DEF_1' => $langs->trans('DefectDirectContact'),
        'DEF_2' => $langs->trans('DefectProhibitedZoneBathroom'),
        'DEF_3' => $langs->trans('DefectIndirectContactProtectionLacking'),
        'DEF_4' => $langs->trans('DefectMainSwitchMissing'),
        'DEF_5' => $langs->trans('DefectOvercurrentProtectionMissing'),
        'DEF_6' => $langs->trans('DefectInsulationResistanceLow'),
        'DEF_7' => $langs->trans('DefectLeakageCurrentHigh'),
        'DEF_8' => $langs->trans('DefectEarthResistanceHigh')
    );
    print '<tr><td colspan="4"><div class="div-table-responsive-no-min"><table class="noborder centpercent">';
    print '<tr class="liste_titre"><th class="titlefield">'.$langs->trans("Defect").'</th><th>'.$langs->trans("Yes").'</th><th>'.$langs->trans("No").'</th></tr>';
    $idx = 0;
    foreach ($def as $k => $label) {
        $idx++;
        $rowHdrId = 'defect_'.$idx.'_label';
        $idYes = 'def_'.$idx.'_si';
        $idNo  = 'def_'.$idx.'_no';
        print '<tr>';
        print '<th id="'.dol_escape_htmltag($rowHdrId).'" scope="row" class="titlefield">'.dol_escape_htmltag($label).'</th>';
        print '<td>';
        print '<div role="group" aria-labelledby="'.dol_escape_htmltag($rowHdrId).'">';
        print '  <input type="radio" id="'.dol_escape_htmltag($idYes).'" name="form['.$k.']" value="SI" required aria-labelledby="'.dol_escape_htmltag($rowHdrId).'">';
        print '  <label for="'.dol_escape_htmltag($idYes).'">'.$langs->trans("Yes").'</label>';
        print '</div>';
        print '</td>';
        print '<td>';
        print '<div role="group" aria-labelledby="'.dol_escape_htmltag($rowHdrId).'">';
        print '  <input type="radio" id="'.dol_escape_htmltag($idNo).'" name="form['.$k.']" value="NO" aria-labelledby="'.dol_escape_htmltag($rowHdrId).'">';
        print '  <label for="'.dol_escape_htmltag($idNo).'">'.$langs->trans("No").'</label>';
        print '</div>';
        print '</td>';
        print '</tr>';
    }
    print '</table></div></td></tr>';

    // Trabajo / comentarios
    print '<tr class="liste_titre"><th colspan="4">'.$langs->trans("BRIEWorks").'</th></tr>';
    print '<tr><td class="titlefield"><label for="trab">'.$langs->trans("WorksDone").' *</label></td><td colspan="3"><input type="text" id="trab" name="form[TRABAJO]" required class="quatrevingtpercent"></td></tr>';
    print '<tr><td><label for="coment">'.$langs->trans("Comments").' *</label></td><td colspan="3"><input type="text" id="coment" name="form[COMENTARIOS]" required class="quatrevingtpercent"></td></tr>';

    // Instalador (mostrar nombre/empresa + RASIC) + firmante
    print '<tr class="liste_titre"><th colspan="4">'.$langs->trans("BRIEInstaller").'</th></tr>';
    print '<tr><td class="titlefield">'.$langs->trans("Company").'</td><td>'.dol_escape_htmltag($inst['display']).'</td>';
    print '<td>'.$langs->transcountry("ProfId4", $mysoc->country_id).'</td><td>'.dol_escape_htmltag($inst['rasic']).'</td></tr>';

    // Prefill del firmante si 401
    print '<tr><td><label for="cert_nom">'.$langs->trans("TechnicianName").' *</label></td><td><input id="cert_nom" type="text" name="form[CERT_NOMBRE]" required value="'.$prefillTechName.'"></td>';
    print '<td><label for="cert_dni">'.$langs->transcountry("ProfId1", $mysoc->country_id).' *</label></td><td><input id="cert_dni" type="text" name="form[CERT_DNI]" required value="'.$prefillTechDni.'"></td></tr>';

    print '<tr><td><label for="lugar">'.$langs->trans("Place").' *</label></td><td><input id="lugar" type="text" name="form[LUGAR]" required></td><td></td><td></td></tr>';

    // Salidas
    print '<tr class="liste_titre"><th colspan="4">'.$langs->trans("Outputs").'</th></tr>';
    print '<tr><td class="titlefield">'.$langs->trans("GenerateFor").'</td><td colspan="3">
        <label><input type="checkbox" name="out[TITULAR]" value="1" checked> '.$langs->trans("Owner").'</label>
        <label class="marginleftonly"><input type="checkbox" name="out[DISTRIBUIDORA]" value="1" checked> '.$langs->trans("Distributor").'</label>
        <label class="marginleftonly"><input type="checkbox" name="out[INSTALADOR]" value="1" checked> '.$langs->trans("Installer").'</label>
    </td></tr>';
    print '<tr><td>'.$langs->trans("Destination").'</td><td colspan="3">
        <label><input type="radio" name="out[DEST]" value="ECM" checked> '.$langs->trans("SaveInECM").'</label>
        <label class="marginleftonly"><input type="radio" name="out[DEST]" value="SIGNED"> '.$langs->trans("SaveInSignedFolder").'</label>
    </td></tr>';

    print '</table></div>';
    print '<div class="center">';
    print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
    print ' <a class="button" href="'.$backurl.'">'.$langs->trans("Cancel").'</a>';
    print '</div>';
    print '</form>';
}
