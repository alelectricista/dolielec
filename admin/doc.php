<?php
//doc.php - IDM configuration file.
global $db, $conf, $langs, $user;
$langs->loadLangs(array('admin','main','dolielec@dolielec'));
$currenttab  = GETPOST('tab','alpha');
if (empty($currenttab)) $currenttab = 'signature';
$action      = GETPOST('dle_action','alpha');   // acciones propias para no colisionar
$token       = newToken();
$upload_dir  = rtrim(DOL_DATA_ROOT,'/').'/dolielec/docs/cert/';
$cert_file   = getDolGlobalString('DOLIELEC_CERT_FILE','');
$cert_pass   = GETPOST('DOLIELEC_CERT_PASS','restricthtml');
if ($cert_pass === '') $cert_pass = getDolGlobalString('DOLIELEC_CERT_PASS','');
$cert_orig   = getDolGlobalString('DOLIELEC_CERT_ORIG','');
$cert_sha256 = getDolGlobalString('DOLIELEC_CERT_SHA256','');
$scan_info   = null;
$hasOpenSSL  = function () {
    return function_exists('openssl_pkcs12_read') && function_exists('openssl_x509_parse');
};

if ($action === 'dle_doc_scan') {
    if (!$hasOpenSSL()) {
        setEventMessages($langs->trans('ErrorOpenSSLNotAvailable'), null, 'errors');
    } elseif (empty($_FILES['cert_upload']) || $_FILES['cert_upload']['error'] === UPLOAD_ERR_NO_FILE) {
        setEventMessages($langs->trans('ErrorNoFileProvided'), null, 'errors');
    } elseif ($_FILES['cert_upload']['error'] !== UPLOAD_ERR_OK) {
        setEventMessages($langs->trans('ErrorUploadingFile').' (code '.$_FILES['cert_upload']['error'].')', null, 'errors');
    } else {
        $ext = strtolower(pathinfo($_FILES['cert_upload']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('p12','pfx'))) {
            setEventMessages($langs->trans('ErrorBadCertificateExtension'), null, 'errors');
        } elseif (!dol_strlen($cert_pass)) {
            setEventMessages($langs->trans('ErrorPasswordRequired'), null, 'errors');
        } else {
            $raw = @file_get_contents($_FILES['cert_upload']['tmp_name']);
            $bag = array();
            if ($raw === false || !openssl_pkcs12_read($raw, $bag, $cert_pass)) {
                setEventMessages($langs->trans('ErrorPkcs12ReadFailed'), null, 'errors');
            } else {
                $x509   = isset($bag['cert']) ? $bag['cert'] : '';
                $parsed = $x509 ? openssl_x509_parse($x509, false) : false;
                if (!$parsed) {
                    setEventMessages($langs->trans('ErrorX509ParseFailed'), null, 'errors');
                } else {
                                        $scan_info  = $parsed;
                                        $cert_orig   = $_FILES['cert_upload']['name'];
                    $cert_sha256 = @hash_file('sha256', $_FILES['cert_upload']['tmp_name']) ?: '';
                    dolibarr_set_const($db, 'DOLIELEC_CERT_ORIG',   $cert_orig,   'chaine', 0, '', $conf->entity);
                    dolibarr_set_const($db, 'DOLIELEC_CERT_SHA256', $cert_sha256, 'chaine', 0, '', $conf->entity);
                    setEventMessages($langs->trans('CertificateScannedOK'), null, 'mesgs');
                }
            }
        }
    }
}
if ($action === 'dle_doc_save') {
    dol_mkdir($upload_dir);
    if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
        setEventMessages($langs->trans('ErrorFailedToWriteInDir').' '.$upload_dir, null, 'errors');
    } else {
        $had_new_upload = (!empty($_FILES['cert_upload']) && is_array($_FILES['cert_upload']) && $_FILES['cert_upload']['error'] !== UPLOAD_ERR_NO_FILE);
        if ($had_new_upload) {
            if ($_FILES['cert_upload']['error'] !== UPLOAD_ERR_OK) {
                setEventMessages($langs->trans('ErrorUploadingFile').' (code '.$_FILES['cert_upload']['error'].')', null, 'errors');
            } else {
                $ext = strtolower(pathinfo($_FILES['cert_upload']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, array('p12','pfx'))) {
                    setEventMessages($langs->trans('ErrorBadCertificateExtension'), null, 'errors');
                } else {
                    $dest_abs = $upload_dir.'cert_entity'.$conf->entity.'.'.$ext;
                    $res = dol_move_uploaded_file($_FILES['cert_upload']['tmp_name'], $dest_abs, 1, 0, $_FILES['cert_upload']);
                    if (!($res > 0 && is_readable($dest_abs))) {
                        setEventMessages($langs->trans('ErrorUploadingFile'), null, 'errors');
                    } else {
                                                $okPass = true;
                        if (dol_strlen($cert_pass) && $hasOpenSSL()) {
                            $raw = @file_get_contents($dest_abs);
                            $bag = array();
                            if ($raw === false || !openssl_pkcs12_read($raw, $bag, $cert_pass)) {
                                $okPass = false;
                                setEventMessages($langs->trans('ErrorPkcs12ReadFailed'), null, 'errors');
                            } else {
                                $x509   = isset($bag['cert']) ? $bag['cert'] : '';
                                $parsed = $x509 ? openssl_x509_parse($x509, false) : false;
                                if ($parsed) $scan_info = $parsed;
                            }
                        }
                        if ($okPass) {
                            $cert_file   = $dest_abs;
                            $cert_orig   = $_FILES['cert_upload']['name'];
                            $cert_sha256 = @hash_file('sha256', $dest_abs) ?: '';
                            dolibarr_set_const($db, 'DOLIELEC_CERT_FILE',   $cert_file,   'chaine', 0, '', $conf->entity);
                            dolibarr_set_const($db, 'DOLIELEC_CERT_PASS',   $cert_pass,   'chaine', 0, '', $conf->entity);
                            dolibarr_set_const($db, 'DOLIELEC_CERT_ORIG',   $cert_orig,   'chaine', 0, '', $conf->entity);
                            dolibarr_set_const($db, 'DOLIELEC_CERT_SHA256', $cert_sha256, 'chaine', 0, '', $conf->entity);
                            setEventMessages($langs->trans('FileUploaded'), null, 'mesgs');
                            setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
                        }
                    }
                }
            }
        } else {
                        dolibarr_set_const($db, 'DOLIELEC_CERT_PASS', $cert_pass, 'chaine', 0, '', $conf->entity);
            setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
            if (is_readable($cert_file) && $hasOpenSSL() && dol_strlen($cert_pass)) {
                $raw = @file_get_contents($cert_file);
                $bag = array();
                if ($raw !== false && openssl_pkcs12_read($raw, $bag, $cert_pass)) {
                    $x509   = isset($bag['cert']) ? $bag['cert'] : '';
                    $parsed = $x509 ? openssl_x509_parse($x509, false) : false;
                    if ($parsed) $scan_info = $parsed;
                                        if (empty($cert_sha256)) {
                        $cert_sha256 = @hash_file('sha256', $cert_file) ?: '';
                        dolibarr_set_const($db, 'DOLIELEC_CERT_SHA256', $cert_sha256, 'chaine', 0, '', $conf->entity);
                    }
                }
            }
        }
    }
}
$action_url = $_SERVER['PHP_SELF'].'?tab='.urlencode($currenttab); // permanecer en la pestaña
print '<form enctype="multipart/form-data" method="POST" action="'.$action_url.'">';
print '<input type="hidden" name="token" value="'.$token.'">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th class="titlefield">'.$langs->trans("Parameter").'</th><th>'.$langs->trans("Value").'</th></tr>';
print '<tr>';
print '<td><label for="cert_upload">'.$langs->trans("CertificateFile").' (.p12/.pfx)</label></td>';
print '<td>';
print '<input type="file" id="cert_upload" name="cert_upload" class="minwidth100" accept=".p12,.pfx">';
if (!empty($cert_file)) {
    print '<br><span class="opacitymedium">'.$langs->trans('CurrentFile').': '.dol_escape_htmltag($cert_file).'</span>';
}
if (!empty($cert_orig)) {
    print '<br><span class="opacitymedium">'.$langs->trans('OriginalFileName').': '.dol_escape_htmltag($cert_orig).'</span>';
}
if (!empty($cert_sha256)) {
    print '<br><span class="opacitymedium">'.$langs->trans('SHA256').': '.dol_escape_htmltag($cert_sha256).'</span>';
}
print '<br><span class="opacitymedium">'.$langs->trans('UploadDest').': '.dol_escape_htmltag($upload_dir).'</span>';
print '</td>';
print '</tr>';
print '<tr>';
print '<td><label for="cert_pass">'.$langs->trans("CertificatePassword").' *</label></td>';
print '<td><input type="password" id="cert_pass" name="DOLIELEC_CERT_PASS" class="minwidth100" required value="'.dol_escape_htmltag($cert_pass).'"></td>';
print '</tr>';
print '</table></div>';
print '<div class="center">';
print '<button type="submit" name="dle_action" value="dle_doc_scan" class="button">'.$langs->trans("Scan").'</button> ';
print '<button type="submit" name="dle_action" value="dle_doc_save" class="button button-save">'.$langs->trans("Save").'</button>';
print '</div>';
print '</form>';
if (is_array($scan_info)) {
    $subject = isset($scan_info['subject']) ? $scan_info['subject'] : array();
    $issuer  = isset($scan_info['issuer'])  ? $scan_info['issuer']  : array();
    $get = function($arr,$k,$def=''){ return isset($arr[$k]) ? $arr[$k] : $def; };
    $cnSubj = $get($subject,'CN');
    $gnSubj = $get($subject,'GN', $get($subject,'givenName',''));
    $snSubj = $get($subject,'SN', $get($subject,'surname',''));
    $oSubj  = $get($subject,'O');
    $cnIss  = $get($issuer,'CN');
    $oIss   = $get($issuer,'O');
    $serial = !empty($scan_info['serialNumber']) ? $scan_info['serialNumber'] : (!empty($scan_info['serialNumberHex']) ? $scan_info['serialNumberHex'] : '');
    $validFrom = !empty($scan_info['validFrom_time_t']) ? dol_print_date($scan_info['validFrom_time_t'],'dayhour') : '';
    $validTo   = !empty($scan_info['validTo_time_t'])   ? dol_print_date($scan_info['validTo_time_t'],'dayhour')   : '';
    print '<br>';
    print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
    print '<tr class="liste_titre"><th class="titlefield">'.$langs->trans("Field").'</th><th>'.$langs->trans("Value").'</th></tr>';
    print '<tr><td>'.$langs->trans("SubjectCN").'</td><td>'.dol_escape_htmltag($cnSubj).'</td></tr>';
    print '<tr><td>'.$langs->trans("SubjectGivenName").'</td><td>'.dol_escape_htmltag($gnSubj).'</td></tr>';
    print '<tr><td>'.$langs->trans("SubjectSurname").'</td><td>'.dol_escape_htmltag($snSubj).'</td></tr>';
    print '<tr><td>'.$langs->trans("SubjectOrg").'</td><td>'.dol_escape_htmltag($oSubj).'</td></tr>';
    print '<tr><td>'.$langs->trans("IssuerCN").'</td><td>'.dol_escape_htmltag($cnIss).'</td></tr>';
    print '<tr><td>'.$langs->trans("IssuerOrg").'</td><td>'.dol_escape_htmltag($oIss).'</td></tr>';
    print '<tr><td>'.$langs->trans("SerialNumber").'</td><td>'.dol_escape_htmltag($serial).'</td></tr>';
    print '<tr><td>'.$langs->trans("ValidFrom").'</td><td>'.dol_escape_htmltag($validFrom).'</td></tr>';
    print '<tr><td>'.$langs->trans("ValidTo").'</td><td>'.dol_escape_htmltag($validTo).'</td></tr>';
    print '</table></div>';
}
?>