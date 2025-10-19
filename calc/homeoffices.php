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

require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';
global $conf, $db, $langs, $user;
$langs->load('dolielec@dolielec');
$action = GETPOST('action', 'alpha');
if ($action === 'calculate') {
    $params = GETPOST('params', 'array');
    $result = getPower($params);

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>' . $langs->trans("CalculationResult") . '</th>';
    print '</tr>';
    print '<tr>';
    print '<td>';
    if (!empty($result['success']) && isset($result['value'])) {
        print '<strong>' . $langs->trans("TotalPower") . ': ' . dol_escape_htmltag($result['value']) . ' kW</strong>';
    }
	else {
        print '<span class="error">' . dol_escape_htmltag($result['message'] ?? 'Unknown error') . '</span>';
    }
    print '</td>';
    print '</tr>';
    print '</table>';
    print '</div>';
}

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="action" value="calculate">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th class="titlefield">' . $langs->trans("Parameter") . '</th>';
print '<th>' . $langs->trans("Value") . '</th>';
print '</tr>';

// Homes
print '<tr>';
print '<td><label for="nh">' . $langs->trans("NumberOfHomes") . ' *</label></td>';
print '<td><input type="number" id="nh" name="params[home][nh]" min="0" step="1" required></td>';
print '</tr>';

print '<tr>';
print '<td><label for="basic">' . $langs->trans("BasicHomes") . '</label></td>';
print '<td><input type="number" id="basic" name="params[home][basic]" min="0" step="1" placeholder="0"></td>';
print '</tr>';

// Services
print '<tr>';
print '<td><label for="elevation">' . $langs->trans("Elevators") . '</label></td>';
print '<td><input type="number" id="elevation" name="params[service][elevation]" min="0" step="1"></td>';
print '</tr>';

print '<tr>';
print '<td>' . $langs->trans("Engines") . '</td>';
print '<td><fieldset><legend class="sr-only">' . $langs->trans("Engines") . '</legend>';
for ($i = 0; $i < 3; $i++) {
    $id = 'engine_' . $i;
    print '<input type="number" id="' . $id . '" 
        name="params[service][engines][]" 
        min="0" step="0.1" class="minwidth50" 
        placeholder="kW" 
        aria-label="' . $langs->trans("Engine") . ' ' . ($i+1) . '"> ';
}
print '</fieldset></td>';
print '</tr>';

print '<tr>';
print '<td><label for="led">' . $langs->trans("LightingPowerW") . '</label></td>';
print '<td><input type="number" id="led" name="params[service][led]" min="0" step="1" placeholder="W"></td>';
print '</tr>';

// Offices
print '<tr>';
print '<td><label for="pl">' . $langs->trans("NumberOfPremises") . '</label></td>';
print '<td><input type="number" id="pl" name="params[office][pl]" min="0" step="1"></td>';
print '</tr>';

print '<tr>';
print '<td>' . $langs->trans("PremisesSurfaceM2") . '</td>';
print '<td><fieldset><legend class="sr-only">' . $langs->trans("PremisesSurfaceM2") . '</legend>';
for ($i = 0; $i < 3; $i++) {
    $id = 'surface_' . $i;
    print '<input type="number" id="' . $id . '" 
        name="params[office][metter][]" 
        min="0" step="1" class="minwidth50" 
        placeholder="m²" 
        aria-label="' . $langs->trans("PremiseSurface") . ' ' . ($i+1) . '"> ';
}
print '</fieldset></td>';
print '</tr>';

// Garage
print '<tr>';
print '<td><label for="npl">' . $langs->trans("NumberOfParkingLots") . '</label></td>';
print '<td><input type="number" id="npl" name="params[garage][npl]" min="0" step="1"></td>';
print '</tr>';

print '<tr>';
print '<td><label for="area">' . $langs->trans("AverageAreaPerLotM2") . '</label></td>';
print '<td><input type="number" id="area" name="params[garage][area]" min="0" step="1" placeholder="20"></td>';
print '</tr>';

print '<tr>';
print '<td><label for="ev">' . $langs->trans("ElectricVehicleCharger") . '</label></td>';
print '<td><select name="params[garage][ev]" id="ev">
<option value="">' . $langs->trans("DefaultYes") . '</option>
<option value="1">' . $langs->trans("Yes") . '</option>
<option value="0">' . $langs->trans("No") . '</option>
</select></td>';
print '</tr>';

print '<tr>';
print '<td><label for="winn">' . $langs->trans("ForcedVentilation") . '</label></td>';
print '<td><select name="params[garage][winn]" id="winn">
<option value="">' . $langs->trans("DefaultYes") . '</option>
<option value="1">' . $langs->trans("Yes") . '</option>
<option value="0">' . $langs->trans("No") . '</option>
</select></td>';
print '</tr>';

print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="' . $langs->trans("Calculate") . '">';
print '</div>';
print '</form>';
?>