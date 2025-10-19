<?php
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
require_once 'calculate.lib.php';
// dolielec.lib.php - Funciones utilitarias para Biel y Dolielec (accesibilidad bloque 17)
//gestionar pestañas
function dolielecTabsConfig () {
	return array(
	'openai' => array('OpenaiSettings', 'openai.php'),
	'google' => array('GoogleAISettings', 'google.php'),
	'geoloc' => array('GeolocalizationSettings', 'geoloc.php'),
	'documentation' => array('DocumentationSettings', 'doc.php'),
	);
}
function dolielecAdminPrepareHead () {
	global $langs;
	$langs->load("dolielec@dolielec");
	$self = DOL_URL_ROOT.'/custom/dolielec/admin/setup.php';
	$cfg = dolielecTabsConfig();
$head =array();
$h = 0;
foreach ($cfg as $id => $t) {
	$label = $langs->trans($t[0]);
	$head[$h][0] = $self.'?tab='.$id;
	$head[$h][1] = $label;
	$head[$h][2] = $id;
	$h++;
}
return $head;
}
//accesibilidad
function dolielec_print_textfield($label, $name, $value = '', $required = false, $helptext = '')
{
    global $langs;

    $fieldid = 'field_' . $name;
    $asterisk = $required ? '<span class="fieldrequired" title="Campo obligatorio" aria-label="Campo obligatorio">*</span>' : '';
    $required_attr = $required ? 'required aria-required="true"' : '';
    $aria_label = $required ? 'aria-label="' . dol_escape_htmltag($label) . ' (requerido)"' : 'aria-label="' . dol_escape_htmltag($label) . '"';
    print '<tr class="oddeven">';
    print '<td><label for="' . $fieldid . '">' . $asterisk . $label . '</label></td>';
    print '<td><input type="text" id="' . $fieldid . '" name="' . $name . '" value="' . dol_escape_htmltag($value) . '" ' . $required_attr . ' ' . $aria_label . ' class="minwidth200"/>';
    if (!empty($helptext)) print ' <span class="opacitymedium">' . $helptext . '</span>';
    print '</td>';
    print '</tr>';
}
function dolielec_a11y_print_status($message, $type='ok') {
    print '<div class="dolielec-status '.($type==='ok'?'ok':'err').'" role="status" aria-live="polite">'
        .dol_escape_htmltag($message).'</div>';
}
function dolielec_a11y_region_start($title, $level=2, $id='') {
    $h = max(1, min(6, (int)$level));
    $idattr = $id ? ' id="'.dol_escape_htmltag($id).'"' : '';
    print '<section class="dolielec-a11y-region" role="region"'.$idattr
        .' aria-labelledby="dolielec-'.$h.'-'.md5($title).'">';
    print '<h'.$h.' id="dolielec-'.$h.'-'.md5($title).'">'
        .dol_escape_htmltag($title).'</h'.$h.'>';
}
function dolielec_a11y_region_end() {
 print '</section>';
 }

//identidad de biel, bloque 1
function dolielec_get_biel_identity()
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $path = DOL_DATA_ROOT . '/dolielec/identity/biel_identity.md';
    if (!file_exists($path)) $path = DOL_DOCUMENT_ROOT . '/custom/dolielec/identity/biel_identity.md';

    $cache = file_exists($path) ? file_get_contents($path) : '';
    return $cache;
}
//saludo
function dolielec_get_biel_greeting()
{
    $id = dolielec_get_biel_identity();
    foreach (preg_split('/\R/', $id) as $l) {
        $t = trim($l);
        if ($t === '' || preg_match('/^(===|título\\s*:)/i', $t)) continue;
        if (stripos($t, 'soy biel') !== false) return $t;
        return $t;
    }
    return '';
}


/**
 * Render accesible (WCAG 2.2 AA) para markdown básico.
 * Soporta: títulos "=== BLOQUE X ===" -> h2, "título:" -> h3, listas "- ", **negritas**, `code`,
 * enlaces [txt](url), imágenes ![alt](src). Incluye landmarks y respeta sr-only/skip-link via CSS.
 *
 * @param string $raw Markdown plano
 * @param string $pageTitle Título accesible de la página
 * @return string HTML seguro
 */
function dolielec_md_render_accessible($raw, $pageTitle = 'Identidad de Biel')
{
    $raw = str_replace(array("\r\n","\r"), "\n", $raw);
    $raw = str_replace("\\n", "\n", $raw);
    $lines = explode("\n", $raw);
    $html = '';

    // Wrapper semántico. Las clases se definen en css/dolielec.css.php
    $html .= '<a class="skip-link" href="#main-content">'.htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').' – Saltar al contenido</a>';
    $html .= '<main id="main-content" role="main" tabindex="-1" aria-labelledby="page-title" class="biel-identity" lang="es">';
    $html .= '<h1 id="page-title" class="visually-hidden">'.htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</h1>';

    $in_list = false;

    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '') { if ($in_list) { $html .= "</ul>\n"; $in_list = false; } continue; }

        // H2 por bloque
        if (preg_match('/^===\s*BLOQUE\s+\d+\s*===/i', $t)) {
            if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
            $html .= '<h2>'.htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</h2>\n";
            continue;
        }
        // H3 por "título: x"
        if (preg_match('/^t[ií]tulo\s*:\s*(.+)$/i', $t, $m)) {
            if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
            $html .= '<h3>'.htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</h3>\n";
            continue;
        }
        // Imagen
        if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)/', $t, $m)) {
            if ($in_list) { $html .= "</ul>\n"; $in_list = false; }
            $alt = htmlspecialchars($m[1] !== '' ? $m[1] : 'Imagen', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $src = dolielec_sanitize_url($m[2]);
            $html .= '<figure><img src="'.$src.'" alt="'.$alt.'" /></figure>'."\n";
            continue;
        }
        // Lista
        if (preg_match('/^-\s+(.+)/', $t, $m)) {
            if (!$in_list) { $html .= "<ul>\n"; $in_list = true; }
            $item = $m[1];
            // Links [text](url)
            $item = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function($mm){
                $txt = htmlspecialchars($mm[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $href = dolielec_sanitize_url($mm[2]);
                return '<a href="'.$href.'">'.$txt.'</a>';
            }, $item);
            // Código y negritas
            $item = preg_replace('/`(.+?)`/s', '<code>$1</code>', $item);
            $item = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $item);
            $item = htmlspecialchars_decode(htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $html .= "<li>$item</li>\n";
            continue;
        }
        // Párrafo
        $p = $t;
        $p = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function($mm){
            $txt = htmlspecialchars($mm[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $href = dolielec_sanitize_url($mm[2]);
            return '<a href="'.$href.'">'.$txt.'</a>';
        }, $p);
        $p = preg_replace('/`(.+?)`/s', '<code>$1</code>', $p);
        $p = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $p);
        $p = htmlspecialchars_decode(htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $html .= "<p>$p</p>\n";
    }
    if ($in_list) $html .= "</ul>\n";
    $html .= '</main>';
    return $html;
}

/**
 * Sanitiza URL a http(s)/mailto únicamente (para renderer accesible).
 */
function dolielec_sanitize_url($url) {
    $url = trim($url);
    if (preg_match('#^(https?://|mailto:)#i', $url)) {
        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return '#';
}
//calling to apis
/**
 * Llamada HTTP (GET / POST / PUT / PATCH / HEAD) usando getURLContent() de Dolibarr 22.
 * Devuelve un array estándar:
 *   [ 'success'=>bool, 'http'=>int|null, 'json'=>mixed|null, 'raw'=>string|null, 'message'=>string|null ]
 *
 * @param string $url
 * @param array  $opts
 * @return array
 */
function apicall($url, $opts = array())
{
    // ----- Lectura de opciones -------------------------------------------------
    $method      = !empty($opts['method'])      ? $opts['method']      : 'GET';
    $query       = !empty($opts['query'])       ? $opts['query']       : '';
    $body        = array_key_exists('body',   $opts) ? $opts['body']   : null;
    $json_body   = array_key_exists('json_body',$opts) ? (int)$opts['json_body'] : 1;
	    $multipart   = array_key_exists('multipart',$opts) ? (int)$opts['multipart'] : 0;
    $bearer      = !empty($opts['bearer'])      ? $opts['bearer']      : '';
    $headers_in  = !empty($opts['headers']) && is_array($opts['headers']) ? $opts['headers'] : array();
    $timeout     = !empty($opts['timeout'])     ? intval($opts['timeout']) : 25;
    $maxredirect = array_key_exists('maxredirect',$opts) ? intval($opts['maxredirect']) : 3;
    $referer     = !empty($opts['referer'])     ? $opts['referer']     : '';
    $noproxy     = !empty($opts['noproxy'])     ? intval($opts['noproxy']) : 0;
    $useragent   = !empty($opts['useragent'])   ? $opts['useragent']   : ('Dolibarr/'.DOL_VERSION.' dolielec/1.0');
    $accept_json = array_key_exists('accept_json',$opts) ? intval($opts['accept_json']) : 1;
    $decode_json = array_key_exists('decode_json',$opts) ? intval($opts['decode_json']) : 1;
	$save_to     = !empty($opts['save_to']) ? $opts['save_to'] : '';

        if (is_array($query)) {
        $qs = http_build_query($query);
    } else {
        $qs = (string) $query;
    }
    if ($qs !== '') {
        $url .= (strpos($url, '?') === false ? '?' : '&') . ltrim($qs, '?&');
    }
        $headers = array();
    if ($accept_json)  $headers[] = 'Accept: application/json';
    if ($bearer !== '') $headers[] = 'Authorization: Bearer '.$bearer;
    if ($referer !== '') $headers[] = 'Referer: '.$referer;
    if ($useragent !== '') $headers[] = 'User-Agent: '.$useragent;
    if (!empty($headers_in)) {
        foreach ($headers_in as $h) $headers[] = $h;
    }

    // ----- Cuerpo para métodos con payload ------------------------------------
    $post_data = '';
    if (in_array($method, array('POST','PUT','PATCH'))) {
        if ($body !== null) {
			if ($multipart) {
				    $post_data = $body;
			}
            elseif ($json_body && is_array($body)) {
                $post_data = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $has_ct = 0;
                foreach ($headers as $h) {
                    if (stripos($h, 'content-type:') === 0) { $has_ct = 1; break; }
                }
                if (!$has_ct) $headers[] = 'Content-Type: application/json';
            } else {
                $post_data = is_array($body) ? http_build_query($body) : (string) $body;
            }
        }
    }

    // ----- Llamada a getURLContent() ------------------------------------------
    $r = getURLContent(
	        $url,                    // 1
        $method,                 // 2
        $post_data,              // 3
        1,                       // 4 followlocation
        $headers,                // 5
        array('http','https'),   // 6 allowedschemes
        $noproxy ? 1 : 0,        // 7 localurl
        -1,                      // 8 ssl_verifypeer
        $timeout,                // 9 connect timeout
        $timeout                 //10 response timeout
    );

    // ----- Procesado de respuesta ---------------------------------------------
	$ctype = !empty($r['content_type']) ? $r['content_type'] : '';
    $http = !empty($r['http_code']) ? intval($r['http_code']) : null;

    if (!empty($r['curl_error_no'])) {
        return array(
            'success' => false,
            'http'    => $http,
            'json'    => null,
            'raw'     => isset($r['content']) ? $r['content'] : null,
			'content_type' => $ctype,
            'message' => !empty($r['curl_error_msg']) ? $r['curl_error_msg'] : 'Transport error'
        );
    }

    $raw = isset($r['content']) ? $r['content'] : '';

    if ($http === null || $http < 200 || $http >= 300) {
        $msg = 'HTTP '.$http;
        if ($decode_json && $raw !== '') {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                if (isset($j['error']['message']))       $msg = $j['error']['message'];
                elseif (isset($j['error']) && is_string($j['error']))  $msg = $j['error'];
                elseif (isset($j['message']))            $msg = $j['message'];
                elseif (isset($j['detail']) && is_string($j['detail'])) $msg = $j['detail'];
            }
        }
        return array(
            'success' => false,
            'http'    => $http,
            'json'    => null,            'raw'     => $raw,
			'content_type' => $ctype,
            'message' => $msg
        );
    }
if ($save_to !== '') {
    $dir = dirname($save_to);
    if (!is_dir($dir)) { dol_mkdir($dir); } // helper Dolibarr
    file_put_contents($save_to, $raw);
    return array(
        'success' => true,
        'http' => $http,
        'json' => null,
        'raw' => null,
        'file' => $save_to,
        'content_type' => $ctype,
        'message' => null
    );
}
    if ($decode_json) {
        if ($raw === '' || $raw === 'null') {
            return array('success'=>true,'http'=>$http,'json'=>null,'raw'=>$raw,'content_type'=>$ctype,'message'=>null);
        }
        $j = json_decode($raw, true);
        if (is_array($j)) {
            return array('success'=>true,'http'=>$http,'json'=>$j,'raw'=>$raw,'content_type'=>$ctype,'message'=>null);
        }
        return array(
            'success' => false,
            'http'    => $http,
            'json'    => null,
            'raw'     => (dol_strlen($raw) > 512 ? dol_substr($raw,0,512).'…' : $raw),
			'content_type' => $ctype,
            'message' => 'InvalidJSONResponse'
        );
    }

    return array('success'=>true,'http'=>$http,'json'=>null,'raw'=>$raw,'content_type'=>$ctype,'message'=>null);
}
