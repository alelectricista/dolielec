<?php
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';

class ClaudeAI {
    private $db;
    private $api_key;
    private $endpoint;

    public function __construct($db) {
        global $conf, $langs;
        $langs->loadLangs(array('dolielec@dolielec'));
        $this->db = $db;
        $this->api_key = !empty($conf->global->ANTHROPIC_API_KEY) ? $conf->global->ANTHROPIC_API_KEY : getDolGlobalString('ANTHROPIC_API_KEY', '');
        $this->endpoint = 'https://api.anthropic.com/v1/';
    }

    private function call($path, $method = 'GET', $data = null, $api = '') {
        global $langs;
        $api = ($api !== '') ? $api : $this->api_key;
        if (empty($api)) {
            return array('success' => false, 'message' => $langs->trans('MissingAPIKey'));
        }
        $url = $this->endpoint . ltrim($path, '/');
        $opts = array(
            'method' => $method,
            'headers' => array(
                'x-api-key: '.$api,
                'anthropic-version: 2023-06-01',
                'content-type: application/json'
            ),
            'accept_json' => 1,
            'decode_json' => 1,
            'timeout' => 30,
        );
        if ($method === 'POST' && $data !== null) {
            $opts['body'] = $data;
            $opts['json_body'] = 1;
        }
        $resp = apicall($url, $opts);
        if (empty($resp) || empty($resp['success'])) {
            return array(
                'success' => false,
                'message' => isset($resp['message']) ? $resp['message'] : $langs->trans('ConnectionFailed'),
                'http' => isset($resp['http']) ? $resp['http'] : null,
                'raw' => isset($resp['raw']) ? $resp['raw'] : null,
            );
        }
        return array(
            'success' => true,
            'http' => isset($resp['http']) ? $resp['http'] : 200,
            'json' => isset($resp['json']) ? $resp['json'] : null,
            'raw' => isset($resp['raw']) ? $resp['raw'] : null,
        );
    }

    public function getModels($api = '') {
        global $langs;
        $resp = $this->call('models', 'GET', null, $api);
        if (empty($resp['success'])) {
            return $resp;
        }
        $json = isset($resp['json']) ? $resp['json'] : null;
        if (!is_array($json) || empty($json['data']) || !is_array($json['data'])) {
            return array('success' => false, 'message' => $langs->trans('NoModels'), 'http' => $resp['http'] ?? null, 'raw' => $resp['raw'] ?? null);
        }
        $blocked_patterns = array();
        $filtered = array();
        foreach ($json['data'] as $m) {
            $id = is_array($m) ? ($m['id'] ?? '') : (string)$m;
            if ($id === '') {
                continue;
            }
            $blocked = false;
            foreach ($blocked_patterns as $pattern) {
                if (stripos($id, $pattern) !== false) {
                    $blocked = true;
                    break;
                }
            }
            if (!$blocked) {
                $filtered[] = $id;
            }
        }
        $filtered = array_values(array_unique($filtered));
        if (empty($filtered)) {
            return array('success' => false, 'message' => $langs->trans('NoModels'));
        }
        return array('success' => true, 'models' => $filtered);
    }

    public function setPrompt($temp = null, $top = null, $model = null, $reason = null, $maxt = null, $system = '', $user = '', $tools = null, $api = '') {
        $api = ($api !== '') ? $api : $this->api_key;
        $temp = ($temp !== null) ? $temp : getDolGlobalFloat('ANTHROPIC_TEMPERATURE', 1.0);
        $model = (!empty($model)) ? $model : getDolGlobalString('ANTHROPIC_DEFAULT_MODEL');
        $maxt = ($maxt !== null) ? $maxt : getDolGlobalInt('ANTHROPIC_MAX_TOKENS', 4096);
                $data = array(
            'model' => $model,
            'max_tokens' => $maxt,
            'system' => $system,
            'messages' => array(array('role' => 'user', 'content' => $user))
        );
        
        if (is_array($tools) && !empty($tools)) {
            if (isset($tools['type']) || isset($tools['function'])) {
                $tools = array($tools);
            }
            $data['tools'] = $tools;
            $data['tool_choice'] = 'auto';
        }
        
        if ($temp !== null) {
            $data['temperature'] = $temp;
        }
        if ($top !== null) {
            $data['top_p'] = $top;
        }
        
        $resp = $this->call('messages', 'POST', $data, $api);
        return $resp;
    }
}