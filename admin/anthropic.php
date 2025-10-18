<?php

global $db, $conf, $langs, $user;

// Parameters

$action = GETPOST('action', 'alpha');
$api_key = GETPOST('ANTHROPIC_API_KEY', 'alpha');
$model = GETPOST('ANTHROPIC_DEFAULT_MODEL', 'alpha');
//$temp = GETPOST('OPENAI_TEMPERATURE', 'alphanohtml');
//$top_p = GETPOST('OPENAI_TOP_P', 'alphanohtml');
//$freq = GETPOST('OPENAI_FREQUENCY_PENALTY', 'alphanohtml');
//$presence = GETPOST('OPENAI_PRESENCE_PENALTY', 'alphanohtml');
//$max_tokens = GETPOST('OPENAI_MAX_TOKENS', 'int');

if ($action == 'save') {
dolibarr_set_const($db, 'ANTHROPIC_API_KEY', $api_key, 'chaine', 0, '', $conf->entity);
dolibarr_set_const($db, 'ANTHROPIC_DEFAULT_MODEL', $model, 'chaine', 0, '', $conf->entity);
//dolibarr_set_const($db, 'OPENAI_TEMPERATURE', $temp, 'chaine', 0, '', $conf->entity);
//dolibarr_set_const($db, 'OPENAI_TOP_P', $top_p, 'chaine', 0, '', $conf->entity);
//dolibarr_set_const($db, 'OPENAI_FREQUENCY_PENALTY', $freq, 'chaine', 0, '', $conf->entity);
//dolibarr_set_const($db, 'OPENAI_PRESENCE_PENALTY', $presence, 'chaine', 0, '', $conf->entity);
//dolibarr_set_const($db, 'OPENAI_MAX_TOKENS', $max_tokens, 'int', 0, '', $conf->entity);
setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}
// Load current values

$api_key = getDolGlobalString('ANTHROPIC_API_KEY', '');
$model = getDolGlobalString('ANTHROPIC_DEFAULT_MODEL', '');
//$temp = getDolGlobalString('OPENAI_TEMPERATURE', '');
//$top_p = getDolGlobalString('OPENAI_TOP_P', '');
//$freq = getDolGlobalString('OPENAI_FREQUENCY_PENALTY', '');
//$presence = getDolGlobalString('OPENAI_PRESENCE_PENALTY', '');
//$max_tokens = getDolGlobalInt('OPENAI_MAX_TOKENS', '');
// JS path (lo dejamos porque tienes planes con él)

//$js = '/custom/dolielec/js/dolielec.js.php';
//echo "\n<script type=\"text/javascript\" src=\"".dol_buildpath($js,1)."\"></script>\n";
// ---- Form UI (igual que el aprobado) ----

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="action" value="save">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th class="titlefield">' . $langs->trans("Parameter") . '</th>';
print '<th>' . $langs->trans("Value") . '</th>';
print '</tr>';
// API Key

print '<tr>';
print '<td><label for="anthropic_api_key">' . $langs->trans("ANTHROPIC_API_KEY") . ' *</label></td>';
print '<td><input type="text" id="anthropic_api_key" name="ANTHROPIC_API_KEY" class="minwidth100" required value="' . dol_escape_htmltag($api_key) . '"> ';
print '<button type="button" id="anthropic_check_api" class="button">' $langs->trans("CheckConnection") . '</button> ';
print '</td>';
print '</tr>';
// Model (select vacío si no hay valor guardado)
print '<tr>';
print '<td><label for="anthropic_model">' . $langs->trans("ANTHROPIC_DEFAULT_MODEL") . ' *</label></td>';
print '<td><select name="ANTHROPIC_DEFAULT_MODEL" id="anthropic_model" required>';
if (!empty($model)) print '<option value="' . dol_escape_htmltag($model) . '">' . dol_escape_htmltag($model) . '</option>';
print '</select></td>';

print '</tr>';

// Temperature

//print '<tr>';
//print '<td><label for="temp">' . $langs->trans("OPENAI_TEMPERATURE") . '</label></td>';
//print '<td><input type="number" step="0.01" min="0" max="2" name="OPENAI_TEMPERATURE" id="temp" placeholder="0.7" value="' . dol_escape_htmltag($temp) . '"></td>';
//print '</tr>';
// Top P

//print '<tr>';
//print '<td><label for="top_p">' . $langs->trans("OPENAI_TOP_P") . '</label></td>';
//print '<td><input type="number" step="0.01" min="0" max="1" name="OPENAI_TOP_P" id="top_p" placeholder="0.7" value="' . dol_escape_htmltag($top_p) . '"></td>';
//print '</tr>';
//// Frequency Penalty

//print '<tr>';
//print '<td><label for="freq">' . $langs->trans("OPENAI_FREQUENCY_PENALTY") . '</label></td>';
//print '<td><input type="number" step="0.01" min="-2" max="2" name="OPENAI_FREQUENCY_PENALTY" id="freq" placeholder="0.5" value="' . dol_escape_htmltag($freq) . '"></td>';
//print '</tr>';

// Presence Penalty

//print '<tr>';
//print '<td><label for="presence">' . $langs->trans("OPENAI_PRESENCE_PENALTY") . '</label></td>';
//print '<td><input type="number" step="0.01" min="-2" max="2" name="OPENAI_PRESENCE_PENALTY" id="presence" placeholder="0.56" value="' . dol_escape_htmltag($presence) . '"></td>';
//print '</tr>';

// Max Tokens

//print '<tr>';
//print '<td><label for="max_tokens">' . $langs->trans("OPENAI_MAX_TOKENS") . '</label></td>';
//print '<td><input type="number" min="1" step="1" name="OPENAI_MAX_TOKENS" id="max_tokens" placeholder="2048" value="' . dol_escape_htmltag($max_tokens) . '"></td>';
//print '</tr>';
print '</table>';
print '</div>';
print '<div class="center">';
print '<input type="submit" class="button button-save" value="' . $langs->trans("Save") . '">';
print '</div>';
print '</form>';
?>