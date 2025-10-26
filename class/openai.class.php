<?php
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';
class OpenAI {
    private $db;
    private $api_key;
    private $endpoint;
    public function __construct($db) {
        global $conf, $langs;
		$langs->loadLangs(array('dolielec@dolielec'));
        $this->db = $db;
        $this->api_key = !empty($conf->global->OPENAI_API_KEY) ? $conf->global->OPENAI_API_KEY : getDolGlobalString('OPENAI_API_KEY', '');
        $this->endpoint = 'https://api.openai.com/v1/';
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
            'bearer' => $api,
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
            foreach ($blocked_patterns as $patterns) {
                if (stripos($id, $patterns) !== false) {
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
		$temp = (!empty($temp) && $temp !== null) ? $temp : getDolGlobalFloat('OPENAI_TEMPERATURE');
$top = (!empty($top) && $top !== null) ? $top : getDolGlobalFloat('OPENAI_TOP_P');
$model = (!empty($model)) ? $model : getDolGlobalString('OPENAI_DEFAULT_MODEL');
$reason = (!empty($reason)) ? $reason : 'medium';
$maxt = (!empty($maxt) && $maxt !== null) ? $maxt : getDolGlobalInt('OPENAI_MAX_TOKENS');
														$data = array('temperature' => $temp, 'top_p' => $top, 'model' => $model, 'max_tokens' => $maxt, 'messages' => array(array('role' => 'system', 'content' => $system), array('role' => 'user', 'content' => $user)));
				if(is_array($tools) && !empty($tools)) {
					if(isset($tools['type']) || isset($tools['function'])) {
						$tools = array($tools);
								}
					$data['tools'] = $tools;
			$data['tool_choice'] = 'auto';
		}
		if(stripos($model, 'o1') !== false || stripos($model, 'o1pro') !== false || stripos($model, 'o3') !== false) {
			unset($data['temperature']);
			unset($data['top_p']);
			$data['reasoning_effort'] = $reason;
		}
		$resp = $this->call('chat/completions', 'POST', $data, $api);
return $resp;
	}		
}