<?php
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) {
    header('Content-Type: application/json');
    die(json_encode(array('error' => 'main.inc.php not found')));
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/class/openai.class.php';
global $user, $conf, $langs, $db;
$langs->loadLangs(array('admin','dolielec'));
if (!$user->admin && empty($user->rights->dolielec->read)) {
    header('Content-Type: application/json');
    echo json_encode(array('error' => 'Access denied'));
    exit;
}
$action  = GETPOST('action', 'aZ09');
$api_key = GETPOST('api_key', 'alphanohtml');
if (!empty($api_key)) {
    $conf->global->OPENAI_API_KEY = $api_key;
}
header('Content-Type: application/json');
switch ($action) {
    case 'check_api_conn':
        $openai = new OpenAI($db);
        $result = $openai->getModels($api_key);
        if (!empty($result['success'])) {
                        echo json_encode(array(
                'success' => true,
                'models' => $result['models']));
            exit;
        }
                echo json_encode(array(
            'success' => false,
            'message' => $result['message'] ?? $langs->trans('ConnectionFailed'),
            'http' => $result['http']    ?? null,
            'raw' => isset($result['raw']) ? (is_string($result['raw']) ? substr($result['raw'],0,2048) : null) : null
        ));
        exit;
    default:
        echo json_encode(array('error' => 'Unknown action'));
        exit;
}
