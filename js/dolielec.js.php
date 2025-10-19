<?php
$res = 0;
if (!$res && file_exists("../../main.inc.php"))  $res = @include("../../main.inc.php");
if (!$res && file_exists("../../../main.inc.php")) $res = @include("../../../main.inc.php");
if (!$res && file_exists("../../../../main.inc.php")) $res = @include("../../../../main.inc.php");
if (!$res) die("Include of main fails");
header('Content-Type: application/javascript; charset=UTF-8');

$openaiJs = dol_buildpath('/custom/dolielec/js/openai.js.php', 1);
$googleJs = dol_buildpath('/custom/dolielec/js/google.js.php', 1);
$docJs = dol_buildpath('/custom/dolielec/js/doc.js.php', 1);
$anthropicJs = dol_buildpath('/custom/dolielec/js/anthropic.js.php', 1);
?>
jQuery(function($){
  function loadScriptOnce(src){
    if (document.querySelector('script[data-dle-src="'+src+'"]')) return;
    var s = document.createElement('script');
    s.src = src;
    s.async = false;
    s.setAttribute('data-dle-src', src);
    document.head.appendChild(s);
  }

  function needOpenAI(){ return !!(document.getElementById('openai_api_key') || document.getElementById('openai_model')); }
  function needGoogle(){ return !!(document.getElementById('google_api_key') || document.getElementById('google_model')); }
  function needAnthropic(){ return !!(document.getElementById('anthropic_api_key') || document.getElementById('anthropic_model')); }
  function needDocJS(){
    return !!(
      document.getElementById('signPdfBtn') ||
      document.getElementById('file_pdf')   ||
      document.getElementById('cups')        ||                    // BRIE form
      document.querySelector('input[name="form[ASCENSOR]"]') ||
      document.getElementById('pot_asc')     ||
      document.getElementById('tierra_val')  ||
      document.getElementById('aisla_val')
    );
  }

    if (needOpenAI()) loadScriptOnce('<?php echo $openaiJs; ?>');
	if (needAnthropic()) loadScriptOnce('<?php echo $anthropicJs; ?>');
  if (needGoogle()) loadScriptOnce('<?php echo $googleJs; ?>');
  if (needDocJS())  loadScriptOnce('<?php echo $docJs; ?>');

  $(document).on('click', '.tabBar a', function(){
    setTimeout(function(){
      if (needOpenAI()) loadScriptOnce('<?php echo $openaiJs; ?>');
	  if (needAnthropic()) loadScriptOnce('<?php echo $anthropicJs; ?>');
      if (needGoogle()) loadScriptOnce('<?php echo $googleJs; ?>');
      if (needDocJS())  loadScriptOnce('<?php echo $docJs; ?>');
    }, 50);
  });
});

