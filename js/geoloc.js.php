<?php
// geoloc.js.php — JS específico de geolocalización (sin jQuery), Dolibarr-friendly
$res = 0;
if (!$res && file_exists("../../main.inc.php"))       $res = @include("../../main.inc.php");
if (!$res && file_exists("../../../main.inc.php"))    $res = @include("../../../main.inc.php");
if (!$res && file_exists("../../../../main.inc.php")) $res = @include("../../../../main.inc.php");
if (!$res) die("Include of main fails");
header('Content-Type: application/javascript; charset=UTF-8');
global $langs; $langs->loadLangs(array('dolielec@dolielec'));
?>
(function(){
  function $(id){ return document.getElementById(id); }
  function on(el, ev, fn){ if(el) el.addEventListener(ev, fn, false); }

  // Mantener SIEMPRE visible la fila de API key; alternar sólo readonly/required y el asterisco
  function syncApiKey(){
    var prov = $('DOLIELEC_GEO_PROVIDER');
    var key  = $('DOLIELEC_GEO_API_KEY');
    var mark = $('req_apikey');
    var row  = document.getElementById('row_api_key') || (key && key.closest ? key.closest('tr') : null);
    if(!prov || !key) return;

    var needs = (prov.value === 'google' || prov.value === 'ors');

    // Nunca ocultar
    if(row){ row.style.display=''; row.removeAttribute('hidden'); row.style.removeProperty('visibility'); }
    key.style.display = ''; key.removeAttribute('hidden'); key.style.removeProperty('visibility');

    // No usar disabled (algunos temas ocultan). Usar readonly cuando no se requiere
    key.disabled = false;
    key.readOnly = !needs;

    if(needs){
      key.required = true;
      key.setAttribute('aria-required','true');
      key.removeAttribute('aria-readonly');
      key.removeAttribute('aria-disabled');
      if(mark) mark.style.display = 'inline';
    } else {
      key.required = false;
      key.removeAttribute('aria-required');
      key.setAttribute('aria-readonly','true');
      key.setAttribute('aria-disabled','true');
      if(mark) mark.style.display = 'none';
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    var prov = $('DOLIELEC_GEO_PROVIDER');
    on(prov, 'change', syncApiKey);
    syncApiKey();
  });
})();