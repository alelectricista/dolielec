<?php
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';
$langs->loadLangs(array('admin','dolielec'));
$cfg = dolielecTabsConfig();
if (!$user->admin) accessforbidden();

// Tab activa

$tab = GETPOST('tab','alpha');
if (!isset($cfg[$tab])) {
$keys = array_keys($cfg);
$tab = reset($keys);
}
// Header
$help_url = '';

llxHeader('', $langs->trans('DoliElecSetup'), $help_url);

// Título + back

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';

print load_fiche_titre($langs->trans('DoliElecSetup'), $linkback, 'title_setup');

// Pestañas

$head = dolielecAdminPrepareHead();
 dol_fiche_head($head, $tab, $langs->trans('DoliElecSetup'), -1, 'dolielec@dolielec');
$base = __DIR__;
include $base.'/'.$cfg[$tab][1];
$js = '/custom/dolielec/js/dolielec.js.php';
echo "\n<script type=\"text/javascript\" src=\"".dol_buildpath($js,1)."\"></script>\n";
dol_fiche_end();
llxFooter();