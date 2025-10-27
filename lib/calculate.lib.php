<?php
//functions for calculate load previsions on diferent offices, for ITC-BT-10 compliance and other ITC's with same requirements.
//file started with ITC-BT-10 and ITC-BT-52 calculate functions
function getCoefi($num): array {
global $langs;
if(!is_numeric($num) || $num < 1) {
return array('success' => false, 'message' => $langs->trans('ValueInvalid'));
}
$cs = array('1' => '1', '2' => '2', '3' => '3', '4' => '3.8', '5' => '4.6', '6' => '5.4', '7' => '6.2', '8' => '7', '9' => '7.8', '10' => '8.5', '11' => '9.2', '12' => '9.9', '13' => '10.6', '14' => '11.3', '15' => '11.9', '16' => '12.5', '17' => '13.1', '18' => '13.7', '19' => '14.3', '20' => '14.8', '21' => '15.3');
if ($num <=21) {
$result = $cs[$num];
return array('success' => true, 'value' => $result);
}
$response = 15.3+($num-21)*0.5;
return array('success' => true, 'value' => $response);
}
function getHome($nh, $basic = null) {
    global $langs;
    $nh = (int) $nh;
    if ($basic === "" || $basic === null) {
        $basic = null;
    }
	else {
        $basic = (int) $basic;
    }
    if ($nh < 1) {
        return array('success' => false, 'message' => $langs->trans('NumberInvalid'));
    }
    if ($basic !== null) {
        if ($basic < 0 || $basic > $nh) {
            return array('success' => false, 'message' => $langs->trans('ValueInvalidOrOutOfRange'));
        }
        $elevated = $nh - $basic;
        $power = ((5.75 * $basic) + (9.2 * $elevated)) / $nh;
    }
	else {
        $power = 9.2; // kW
    }
    $cs = getCoefi($nh);
    if (!$cs['success']) {
        return $cs;
	}
$result = (float) $cs['value'];
    $avg = $power * $result;
    return array('success' => true, 'value' => $avg);
}
function getComService($elevation = null, $engines = null, $led = null) {
    global $langs;
    $elevation = ($elevation === "" || $elevation === null) ? 0 : (int) $elevation;
    $led = ($led === "" || $led === null) ? 0 : (int) $led;
    $engines = is_array($engines) ? array_filter($engines, fn($value) => $value !== "" && $value !== null) : [];
    if ($elevation < 0 || $led < 0 || count(array_filter($engines, fn($value) => !is_numeric($value) || $value < 0)) > 0) {
        return array('success' => false, 'message' => $langs->trans('OneOrMoreValuesAreInvalid'));
    }
    if ($elevation === 0 && array_sum($engines) === 0 && $led === 0) {
        return array('success' => true, 'value' => 0);
    }
    $elecorr = $elevation > 0 ? $elevation * 1.3 : 0;
    $engicorr = 0;
    if (count($engines) > 1) {
        rsort($engines);
        $engicorr += $engines[0] * 1.25;
        for ($i = 1; $i < count($engines); $i++) {
            $engicorr += $engines[$i];
       }
    }
	elseif (count($engines) === 1) {
        $engicorr += $engines[0];
    }
    $il = $led > 0 ? ($led * 1.8) / 1000 : 0;
    $result = $elecorr + $engicorr + $il;
    return array('success' => true, 'value' => round($result, 2));
}
function getOffice($pl = null, $metter = null) {
    global $langs;
    if ($pl === "" || $pl === null) {
        return array('success' => true, 'value' => 0);
    }
    $pl = (int) $pl;
    $metter = array_filter((array)$metter, fn($value) => $value !== "" && $value !== null);
    if (count($metter) !== $pl) {
        return array('success' => false, 'message' => $langs->trans('InvalidMetterFormat'));
    }
    $power = 0;
    foreach ($metter as $m2) {
        if (!is_numeric($m2) || $m2 <= 0) {
            return array('success' => false, 'message' => $langs->trans('ValueInvalid'));
       }
        $power += max(3450, $m2 * 100); // W
    }
    return array('success' => true, 'value' => $power / 1000);
}
function getParking($npl, $ev = null, $winn = null, $area = null) {
    global $langs;
    $npl = (int) $npl;
    if ($area === "" || $area === null) {
        $area = 20;
    }    $area = (int) $area;
    if ($npl < 0) {
        return array('success' => false, 'message' => $langs->trans('numberInvalid'));
    }
    if ($npl === 0) {
        return array('success' => true, 'value' => 0);
    }
    $ev = ($ev === "" || $ev === null) ? 1 : (int) $ev;
    $winn = ($winn === "" || $winn === null) ? 1 : (int) $winn;
	$result = 0;
    if ($ev === 1) {
        $result += $npl * 3.68;
    }
	if ($winn === 1) {
        $result += $npl * $area * 0.02;
    }
	else {
        $result += $npl * $area * 0.01;
    }
    if ($result < 3.45) {
        $result = 3.45;
    }
    return array('success' => true, 'value' => $result);
}
function getPower($params) {
global $langs;
$total = 0;
if (!empty($params['home'])) {
$res = getHome($params['home']['nh'] ?? null, $params['home']['basic'] ?? null);
if (!empty($res['success']) && isset($res['value'])) {
$total += $res['value'];
}
}
if (!empty($params['service'])) {
$res = getComService($params['service']['elevation'] ?? null, $params['service']['engines'] ?? [], $params['service']['led'] ?? null);
if (!empty($res['
']) && isset($res['value'])) {
$total += $res['value'];
}
}
if (!empty($params['office'])) {
$res = getOffice($params['office']['pl'] ?? null, $params['office']['metter'] ?? null);
if (!empty($res['success']) && isset($res['value'])) {
$total += $res['value'];
}
}
if (!empty($params['parking'])) {
$res = getParking($params['parking']['npl'] ?? null, $params['parking']['ev'] ?? null, $params['parking']['winn'] ?? null, $params['parking']['area'] ?? null);
if (!empty($res['success']) && isset($res['value'])) {
$total += $res['value'];
}
}
if ($total === 0) {
return array('success' => false, 'message' => $langs->trans('CalculationNotPossible'));
}
return array('success' => true, 'value' => round($total, 2));
}
function getIndustries($pl = null, $metter = null) {
    global $langs;
    if ($pl === "" || $pl === null) {
        return array('success' => true, 'value' => 0);
    }
    $pl = (int) $pl;
    $metter = array_filter((array)$metter, fn($value) => $value !== "" && $value !== null);
    if (count($metter) !== $pl) {
        return array('success' => false, 'message' => $langs->trans('InvalidMetterFormat'));
    }
    $power = 0;
    foreach ($metter as $m2) {
        if (!is_numeric($m2) || $m2 <= 0) {
            return array('success' => false, 'message' => $langs->trans('ValueInvalid'));
       }
        $power += max(10350, $m2 * 125);
    }
    return array('success' => true, 'value' => $power / 1000);
}
//ITC-BT-52 compliance
function getCollectiveParking($pev, $spl) {
	global $langs;
	if ($pev < 0) {
		return array('success' => $langs->trans('NumberInvalid'));
	}
		$total = $pev * 0.1;
		$pot = $total * 3680;
		$ev = $pot;
		$response =getPower($params);
		if(empty($response['success']) || !isset($response['value'])) {
			return array('success' => false, 'message' => $langs->trans('CalculationFailed'));
		}
			if ($spl === 1) {
$pot = (($response['value'] * 1000) + $ev) * 0.3 / 1000;
return array ('success' => true, 'value' => $pot);
		}
		else {
			$pot = ($response['value'] * 1000)+ $ev;
			return array('success' => true, 'values' => $pot);
		}
}			
//calculating wires section, with bt19, bt40, bt20, bt06 compliance.
function getSystemInstall($route, $cores) {
$route = trim($route);
$cores = intval($cores);
$map = array('A'=>array(3=>'a1', 2=>'a2'), 'B'=>array(3=>'b1', 2=>'b2'), 'C'=>array(3=>'c1', 2=>'c2'), 'G'=>array(3=>'g1'));
return isset($map[$route][$cores]) ? $map[$route][$cores] : null;
}
function getVoltage($i, $longitude, $section, $mat = 'Cu', $family = 'UNI', $phase = 3, $cosphi = 0.8) {
    $r_cu = array(1.5 => 12.1, 2.5 => 7.41, 4 => 4.61, 6 => 3.08, 10 => 1.83, 16 => 1.15, 25 => 0.727, 35 => 0.524, 50 => 0.387, 70 => 0.268, 95 => 0.193, 120 => 0.153, 150 => 0.124, 185 => 0.0991, 240 => 0.0754, 300 => 0.0601);
    if ($mat === 'Al' || $mat === 'al') {
        foreach ($r_cu as $k => $v) {
$r_cu[$k] = $v * 1.6;
    }
}
    $x_uni = array(1.5 => 0.080, 2.5 => 0.075, 4 => 0.070, 6 => 0.065, 10 => 0.060, 16 => 0.056, 25 => 0.052, 35 => 0.050, 50 => 0.048, 70 => 0.045, 95 => 0.043, 120 => 0.042, 150 => 0.041, 185 => 0.040, 240 => 0.039, 300 => 0.038);
    $x_multi = array();
    foreach ($x_uni as $k => $v) {
$x_multi[$k] = $v * 0.8;
}
    $x_map = ($family === 'MULTI' || $family === 'multi') ? $x_multi : $x_uni;
    if (!isset($r_cu[$section]) || !isset($x_map[$section])) {
return null;
}
    $r = $r_cu[$section];
    $x = $x_map[$section];
    $u = ($phase === 3) ? 400 : 230;
    $k = ($phase === 3) ? sqrt(3) : 2;
    $l = ($phase === 1 ? $longitude * 2 : $longitude) / 1000;
    $dv_v = $k * $i * ($r * $cosphi + $x * sqrt(1 - $cosphi * $cosphi)) * $l;
    return ($dv_v * 100) / $u;
}
