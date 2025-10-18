<?php
//google.php, page configuration for googleAI parameters configuration
global $db, $conf, $langs, $user;
$action = GETPOST('action', 'alpha');
$api_key = GETPOST('GOOGLE_API_KEY', 'alpha');
$model = GETPOST('GOOGLE_DEFAULT_MODEL', 'alpha');
if ($action == 'save') {
	dolibarr_set_const($db, 'GOOGLE_API_KEY', $api_key, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'GOOGLE_DEFAULT_MODEL', $model, 'chaine', 0, '', $conf->entity);
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}
$api_key = getDolGlobalString('GOOGLE_API_KEY');
$model = getDolGlobalString('GOOGLE_DEFAULT_MODEL');
//$js = '/custom/dolielec/js/dolielec.js.php';
//echo "\n<script type=\"text/javascript\" src=\"".dol_buildpath($js,1)."\"></script>\n";

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="action" value="save">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th class="titlefield">' . $langs->trans("Parameter") . '</th>';
print '<th>' . $langs->trans("Value") . '</th>';
print '</tr>';
print '<tr>';
print '<td><label for="google_api_key">' . $langs->trans("GOOGLE_API_KEY") . ' *</label></td>';
print '<td><input type="text" id="google_api_key" name="GOOGLE_API_KEY" class="minwidth100" required value="' . dol_escape_htmltag($api_key) . '">';
print '<button type="button" id="google_check_api" class="button">' . $langs->trans("CheckConnection") . '</button> ';
print '</td></tr>';
print '<tr>';
print '<td><label for="google_model">' . $langs->trans("GOOGLE_DEFAULT_MODEL") . ' *</label></td>';
print '<td><select name="GOOGLE_DEFAULT_MODEL" id="google_model" required>';
if (!empty($model)) print '<option value="' .dol_escape_htmltag($model) . '">' . dol_escape_htmltag($model) . '</option>';
print '</select></td>';
print '</tr>';
print '</table>';
print '</div>';
print '<div class="center">';
print '<input type="submit" class="button button-save" value="' . $langs->trans("Save") . '">';
print '</div>';
print '</form>';
?>