<?php
$res = @include '../main.inc.php'; if (!$res) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';

$langs->load("dolielec@dolielec");
if (!$user->admin && empty($user->rights->dolielec->read)) accessforbidden();

$title = 'Identidad de Biel';
llxHeader('', $title);
print load_fiche_titre($title, '', 'user');

$identity = dolielec_get_biel_identity();
echo dolielec_md_render_accessible($identity, $title);

llxFooter();
