<?php
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';

class GoogleAI {
    private $db;
    private $api_key;
    private $endpoint;

    public function __construct($db) {
        global $conf, $langs;
		$langs->loadLangs('dolielec@dolielec');
        $this->db       = $db;
        $this->api_key  = !empty($conf->global->GOOGLE_API_KEY) ? $conf->global->GOOGLE_API_KEY : getDolGlobalString('GOOGLE_API_KEY', '');
        $this->endpoint = 'https://generativelanguage.googleapis.com/v1/';
    }
    private function call($path, $method = 'GET', $data = null, $api = '')
    {
        global $langs;
        $api_key = ($api !== '') ? $api : $this->api_key;
        if (empty($api_key)) {
            return array('success'=>false,'message'=>$langs->trans('MissingAPIKey'));
        }

        $url  = $this->endpoint . ltrim($path, '/');
        $opts = array(
            'method'      => $method,
            'query'       => array('key' => $api_key),
            'accept_json' => 1,
            'decode_json' => 1,
            'timeout'     => 30
        );

        if ($method === 'POST' && $data !== null) {
            $opts['body']      = $data;
            $opts['json_body'] = 1;
        }

        $resp = apicall($url, $opts);

        if (empty($resp) || empty($resp['success'])) {
            return array(
                'success'=>false,
                'message'=>isset($resp['message']) ? $resp['message'] : $langs->trans('ConnectionFailed'),
                'http'   => isset($resp['http']) ? $resp['http'] : null,
                'raw'    => isset($resp['raw'])  ? $resp['raw']  : null
            );
        }

        return array(
            'success'=>true,
            'http'   => isset($resp['http']) ? $resp['http'] : 200,
            'json'   => isset($resp['json']) ? $resp['json'] : null,
            'raw'    => isset($resp['raw'])  ? $resp['raw']  : null
        );
    }

    /** Devuelve lista de modelos Gemini filtrada igual que en OpenAI */
    public function getModels($api = '')
    {
        global $langs;

        $resp = $this->call('models', 'GET', null, $api);
        if (empty($resp['success'])) return $resp;

        $json = isset($resp['json']) ? $resp['json'] : null;
        if (!is_array($json) || empty($json['models']) || !is_array($json['models'])) {
            return array(
                'success'=>false,
                'message'=>$langs->trans('NoModels'),
                'http'=>$resp['http'] ?? null,
                'raw'=>$resp['raw']   ?? null
            );
        }

        /* --- Filtro + limpieza del prefijo "models/" ---------------- */
        $blocked_patterns = array();   // añade aquí si quieres vetar algo
        $filtered         = array();

        foreach ($json['models'] as $m) {
            $id = is_array($m) ? ($m['name'] ?? '') : (string)$m;
            if ($id === '') continue;

            // quita "models/" del inicio
            if (substr($id, 0, 7) === 'models/') {
                $id = substr($id, 7);
            }

            $blocked = false;
            foreach ($blocked_patterns as $p) {
                if (stripos($id, $p) !== false) { $blocked = true; break; }
            }
            if (!$blocked) $filtered[] = $id;
        }
        $filtered = array_values(array_unique($filtered));
        /* ------------------------------------------------------------ */

        if (empty($filtered)) {
            return array('success'=>false,'message'=>$langs->trans('NoModels'));
        }

        return array('success'=>true,'models'=>$filtered);
    }
}