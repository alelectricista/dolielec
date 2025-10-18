<?php
//doc.php - dolielec documentation manager file.
$res = 0;
if (!$res) {
 $res = @include '../../main.inc.php';
if (!$res) {
 $res = @include '../../../main.inc.php';
if (!$res) {
 $res = @include '../../../../main.inc.php';
if (!$res) {
 die('Include of main fails');
 }
}
}
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';
global $langs, $user, $conf, $db;
$langs->loadLangs(array('admin','dolielec@dolielec'));
if (!$user->rights->dolielec->read) {
	accessforbidden();
}
$action = GETPOST('action', 'alpha');
$tab = GETPOST('tab', 'alpha') ?? 'signature';
$head = array();
$head[] = array(dol_buildpath('/custom/dolielec/doc.php?tab=signature',1), $langs->trans('Signature'), 'signature');
$head[] = array(dol_buildpath('/custom/dolielec/doc.php?tab=brie',1), $langs->trans('BRIE'), 'BRIE');
llxHeader('', $langs->trans('InternalDocumentManager'));
dol_fiche_head($head, $tab, $langs->trans('InternalDocumentManager'));
switch($tab) {
	case 'signature':
	include 'includes/sign.inc.php';
	break;
		case 'brie':
		define('DOLIELEC_brie', 1);
	include 'includes/certificates.inc.php';
	break;
}
$js = '/custom/dolielec/js/dolielec.js.php';
echo "\n<script type=\"text/javascript\" src=\"".dol_buildpath($js,1)."\"></script>\n";
dol_fiche_end();
llxFooter();
?>