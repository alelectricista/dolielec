<?php
// ajax/doc.ajax.php – endpoint AJAX for dolielec documentation manager.
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) $res = @include '../../../../main.inc.php';
if (!$res) {
    header('Content-Type: application/json; charset=UTF-8');
    die(json_encode(array('error' => 'main.inc.php not found')));
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/class/doc.class.php';
global $user, $conf, $langs, $db;
$langs->loadLangs(array('admin','dolielec'));
if (!$user->admin && empty($user->rights->dolielec->read)) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('error' => 'Access denied'));
    exit;
}
$action = GETPOST('action', 'alpha');
switch ($action) {
    case 'signPdf':
        $doc  = new Documentation($db);
        $src  = isset($_FILES['file_pdf']['tmp_name']) ? $_FILES['file_pdf']['tmp_name'] : '';
        $pass = $conf->global->DOLIELEC_CERT_PASS;
        if (!$src || !$pass) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('success' => false, 'message' => $langs->trans('BadParams')));
            exit;
        }
        try {
            $dir = DOL_DATA_ROOT.'/dolielec/docs/signed/';
            if (!is_dir($dir)) dol_mkdir($dir);
            $orig     = isset($_FILES['file_pdf']['name']) ? basename($_FILES['file_pdf']['name']) : 'document.pdf';
            $basename = preg_replace('/\.pdf$/i', '', $orig);
            $dest     = $dir.$basename.'_signed.pdf';
            $signed = $doc->setSign($src, $dest, null);
            clearstatcache();
            while (ob_get_level()) ob_end_clean();

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="'.dol_escape_htmltag(basename($signed)).'"');
            if (is_readable($signed)) header('Content-Length: '.filesize($signed));
            readfile($signed);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('success' => false, 'message' => $e->getMessage()));
            exit;
        }
        // no break; because of exit

    case 'brieSave':
        if (empty($user->rights->dolielec->write)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('ok'=>0,'error'=>'PERM_DENIED'));
            exit;
        }
        $socid = GETPOST('socid','int');
        $form  = GETPOST('form','array');
        $out   = GETPOST('out','array');
        // Validación dura: todo obligatorio excepto PISO y Actividades
        $must = array(
            'CUPS',
            'TIT_NOMBRE','TIT_NIF','TIT_TLF','TIT_DOM','TIT_CP','TIT_LOCALIDAD',
            'VIA','NUM','PORTA','CP','LOCALIDAD',
            // ACTIVIDAD_ANT y ACTIVIDAD_ACT son opcionales
            'SUPERF',
            'TENSION_ANT','TENSION_ACT',
            'POT_MAX','POT_INICIAL','POT_CONTRATAR',
            'DI_SECCION','DIF_NUM','DIF_IN','DIF_SENS',
            'PROT_GEN_TIPO','PROT_GEN_INT',
            'TIERRA_EXISTE','TIERRA_VALOR',
            'AISLAMIENTO','ASCENSOR',
            'TRABAJO','COMENTARIOS',
            'CERT_NOMBRE','CERT_DNI','LUGAR'
        );
        for ($i=1; $i<=10; $i++) $must[] = 'DEF_'.$i;
        $missing = array();
        foreach ($must as $k) {
            if (!array_key_exists($k, $form)) { $missing[] = $k; continue; }
            $v = is_array($form[$k]) ? '' : trim((string)$form[$k]);
            if (dol_strlen($v) === 0) $missing[] = $k;
        }
        // Requisito condicional: POT_ASCENSOR si ASCENSOR = SI
        if (!empty($form['ASCENSOR']) && $form['ASCENSOR'] === 'SI') {
            if (!isset($form['POT_ASCENSOR']) || dol_strlen(trim((string)$form['POT_ASCENSOR'])) === 0) {
                $missing[] = 'POT_ASCENSOR';
            }
        }
        // Debe seleccionarse al menos un rol de salida
        if (empty($out['TITULAR']) && empty($out['DISTRIBUIDORA']) && empty($out['INSTALADOR'])) {
            $missing[] = 'OUTPUT_ROLE';
        }
        if (!empty($missing)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('ok'=>0,'error'=>'MISSING_FIELDS','fields'=>$missing));
            exit;
        }
        $doc  = new Documentation($db);
        $data = $doc->setBrie($form, $socid);
        $tplodt = DOL_DATA_ROOT.($conf->entity>1?'/'.$conf->entity:'').'/doctemplates/dolielec/brie_template.odt';
        if (!is_readable($tplodt)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('ok'=>0,'error'=>'Template ODT not found'));
            exit;
        }
        $tmpdir = DOL_DATA_ROOT.'/dolielec/var/tmp';
        dol_mkdir($tmpdir);
        $stamp  = dol_now();
        // Si no hay socid usamos NIF
        $who   = !empty($socid) ? $socid : dol_sanitizeFileName($form['TIT_NIF']);
        $odtOut = $tmpdir.'/brie_'.$stamp.'_'.$who.'.odt';
        $r1 = $doc->renderODT($data, $tplodt, $odtOut);
        if (empty($r1['success'])) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('ok'=>0,'error'=>$r1['message']));
            exit;
        }
        $pdfOut = preg_replace('/\.odt$/i', '.pdf', $odtOut);
        $r2 = $doc->odtToPdf($odtOut, $pdfOut);
        if (empty($r2['success'])) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('ok'=>0,'error'=>$r2['message']));
            exit;
        }
        // Nombre final + firma (solo una vez)
        $dni     = $form['TIT_NIF'];
        $cups    = $form['CUPS'];
        $datestr = dol_print_date(dol_now(), '%d%m%y');
        $baseNoSuffix = 'brie_'.dol_sanitizeFileName($dni).'_'.dol_sanitizeFileName($cups).'_'.$datestr;
        $signedBase = $tmpdir.'/'.$baseNoSuffix.'_signed.pdf';
        try {
            $signed = $doc->setSign($pdfOut, $signedBase);
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('ok'=>0,'error'=>$e->getMessage()));
            exit;
        }
        // Distribución por rol
        $roles = array('TITULAR'=>'', 'DISTRIBUIDORA'=>'_ede', 'INSTALADOR'=>'_inst');
        $infos = array();
        foreach ($roles as $k=>$suffix) {
            if (empty($out[$k])) continue;
            $roleName = $baseNoSuffix.$suffix.'_signed.pdf';
            $roleTmp  = $tmpdir.'/'.$roleName;
            dol_copy($signedBase, $roleTmp, '0', 0);
            if (!empty($socid) && !empty($out['DEST']) && $out['DEST']==='ECM') {
                $relpath = $doc->attachToThirdparty($socid, $roleTmp);
                $infos[] = array('role'=>$k, 'ok'=>1, 'where'=>'ECM', 'path'=>$relpath);
            } else {
                $dirSigned = DOL_DATA_ROOT.'/dolielec/docs/signed';
                if (!is_dir($dirSigned)) dol_mkdir($dirSigned);
                $final = $dirSigned.'/'.$roleName;
                dol_copy($roleTmp, $final, '0', 0);
                $infos[] = array('role'=>$k, 'ok'=>1, 'where'=>'FILE', 'path'=>$final);
            }
        }
        $backurl = !empty($socid) ? dol_buildpath('/societe/card.php',1).'?id='.$socid.'&tab=documents' : dol_buildpath('/index.php',1);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('ok'=>1,'msg'=>$langs->trans('BRIEGeneratedAndSigned'),'infos'=>$infos,'redirect'=>$backurl));
        exit;

    default:
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('error' => 'Unknown action'));
        exit;
}
