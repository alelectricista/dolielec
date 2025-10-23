<?php
// custom/dolielec/ajax/doc.ajax.php
$res = 0;
if (!$res && file_exists("../../main.inc.php"))  $res = @include("../../main.inc.php");
if (!$res && file_exists("../../../main.inc.php")) $res = @include("../../../main.inc.php");
if (!$res && file_exists("../../../../main.inc.php")) $res = @include("../../../../main.inc.php");
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/class/doc.class.php';

global $db, $conf, $langs, $user;
$langs->loadLangs(array('main','companies','dolielec@dolielec'));

if (empty($user->rights->dolielec->read)) accessforbidden();

$action = GETPOST('action','alpha');

// rutas base
$modroot   = !empty($conf->dolielec->dir_output) ? $conf->dolielec->dir_output : DOL_DATA_ROOT.'/dolielec';
$tmpDir    = $modroot.'/var/tmp';
$signedDir = $modroot.'/docs/signed';
$tplBrie   = DOL_DATA_ROOT.'/doctemplates/dolielec/brie_template.odt';
dol_mkdir($tmpDir); dol_mkdir($signedDir);

switch ($action) {
case 'brie_save': // flujo completo por AJAX
	header('Content-Type: application/json; charset=UTF-8');

	if (empty($user->rights->dolielec->write) && empty($user->admin)) {
		echo json_encode(array('success'=>false,'message'=>$langs->trans('NotEnoughPermissions'))); exit;
	}
	$token = GETPOST('token','alphanohtml');
	if (empty($token) || !dol_verifyToken($token)) {
		echo json_encode(array('success'=>false,'message'=>$langs->trans('ErrorBadToken'))); exit;
	}
	if (!is_readable($tplBrie)) {
		echo json_encode(array('success'=>false,'message'=>$langs->trans('TemplateNotFound').' '.$tplBrie)); exit;
	}

	$socid = GETPOST('socid','int');
	$form  = GETPOST('form','array');
	$out   = GETPOST('out','array');

	$doc  = new Documentation($db);
	$data = $doc->setBrie($form, $socid);

	// destinatarios
	$targets = array();
	if (!empty($out['TITULAR']))       $targets[] = 'TITULAR';
	if (!empty($out['DISTRIBUIDORA'])) $targets[] = 'DISTRIBUIDORA';
	if (!empty($out['INSTALADOR']))    $targets[] = 'INSTALADOR';
	if (empty($targets)) $targets = array('TITULAR');

	$cupsSafe = dol_sanitizeFileName(!empty($data['CUPS']) ? $data['CUPS'] : 'SIN_CUPS');
	$dateSafe = dol_print_date(dol_now(), '%Y%m%d');
	$baseName = 'brie_'.$cupsSafe.'_'.$dateSafe;

	$files = array();
	foreach ($targets as $tg) {
		$fname  = $baseName.'_'.$tg;
		$odtOut = $tmpDir.'/'.$fname.'.odt';
		$pdfTmp = $tmpDir.'/'.$fname.'.pdf';
		$signed = $signedDir.'/'.$fname.'-signed.pdf';

		$r1 = $doc->renderODT($tplBrie, $odtOut, $data);
		if (empty($r1['success'])) { echo json_encode(array('success'=>false,'message'=>'ODT: '.$r1['message'])); exit; }

		$r2 = $doc->odtToPdf($odtOut, $pdfTmp);
	if (empty($r2['success'])) { echo json_encode(array('success'=>false,'message'=>'PDF: '.$r2['message'])); exit; }

		$ok = $doc->setSign($pdfTmp, $signed);
		if (!$ok || !is_readable($signed)) { echo json_encode(array('success'=>false,'message'=>$langs->trans('SignFailed'))); exit; }

		$files[] = array(
			'name'   => basename($signed),
			'path'   => $signed,
			'public' => dol_buildpath('/document.php',1).'?modulepart=dolielec&file='.urlencode('docs/signed/'.basename($signed))
		);
	}

	// ECM opcional
	if (!empty($socid) && !empty($out['DEST']) && $out['DEST']==='ECM') {
		foreach ($files as $f) { $doc->attachToThirdparty($socid, $f['path']); }
	}

	echo json_encode(array('success'=>true,'files'=>$files));
	exit;

case 'signPdf': // firma suelta (subida input file) → devuelve blob/pdf
	if (empty($user->rights->dolielec->write) && empty($user->admin)) accessforbidden();

	if (empty($_FILES['file_pdf']['tmp_name']) || !is_uploaded_file($_FILES['file_pdf']['tmp_name'])) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('success'=>false,'message'=>$langs->trans('BadParams'))); exit;
	}

	$srcTmp = $tmpDir.'/upload_'.dol_print_date(dol_now(),'dayhourlog').'_'.dol_sanitizeFileName($_FILES['file_pdf']['name']);
	if (!dol_move_uploaded_file($_FILES['file_pdf']['tmp_name'], $srcTmp, 1, 0, $_FILES['file_pdf']['error'])) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('success'=>false,'message'=>$langs->trans('ErrorFileUpload'))); exit;
	}

	$dest = preg_replace('/\.pdf$/i','-signed.pdf',$srcTmp);
	$doc  = new Documentation($db);
	$ok   = $doc->setSign($srcTmp, $dest);
	if (!$ok || !is_readable($dest)) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('success'=>false,'message'=>$langs->trans('SignFailed'))); exit;
	}

	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="'.dol_escape_htmltag(basename($dest)).'"');
	header('Content-Length: '.filesize($dest));
	readfile($dest);
	exit;

default:
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(array('success'=>false,'message'=>'Unknown action')); exit;
}
