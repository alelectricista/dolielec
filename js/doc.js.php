<?php
// doc.js.php — dolielec documentation manager JavaScript file.
$res = 0;
if (!$res && file_exists("../../main.inc.php"))  $res = @include("../../main.inc.php");
if (!$res && file_exists("../../../main.inc.php")) $res = @include("../../../main.inc.php");
if (!$res && file_exists("../../../../main.inc.php")) $res = @include("../../../../main.inc.php");
if (!$res) die("Include of main fails");

header('Content-Type: application/javascript; charset=UTF-8');
global $langs;
$langs->load("dolielec@dolielec");
?>
jQuery(document).ready(function($) {
    if ($('#signPdfBtn').length) {
    $('#signPdfBtn').on('click', function(e) {
      e.preventDefault();
      var fileInput = $('#file_pdf')[0];
      if (!fileInput.files || !fileInput.files.length) {
        alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv('BadParams')); ?>");
        return;
      }
      var file = fileInput.files[0];
      var formData = new FormData();
      formData.append('action', 'signPdf');
      formData.append('file_pdf', file);
      $.ajax({
        url: "<?php echo dol_buildpath('/custom/dolielec/ajax/doc.ajax.php',1); ?>",
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhrFields: { responseType: 'blob' },
        success: function(blob, status, xhr) {
          var contentType = xhr.getResponseHeader('Content-Type');
          if (contentType === 'application/pdf') {
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            var origName = file.name;
            var newName = origName.replace(/\.pdf$/i, '') + '-signed.pdf';
            a.download = newName;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
          } else {
            var reader = new FileReader();
            reader.onload = function() {
              try {
                var json = JSON.parse(reader.result);
                alert(json.message || json.error || "<?php echo dol_escape_js($langs->transnoentitiesnoconv('ConnectionFailed')); ?>");
              } catch (err) {
                alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv('ConnectionFailed')); ?>");
              }
            };
            reader.readAsText(blob);
          }
        },
        error: function() {
          alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv('ConnectionFailed')); ?>");
        }
      });
    });
  }

    if (!$('#cups').length) return;

  function setDisabledWithHidden($input, disable, zeroValue) {
    if (!$input.length) return;

    var name   = $input.attr('name');
    var baseId = $input.attr('id') || ('fld_'+Math.random().toString(36).slice(2));
    $input.attr('id', baseId);

    var hidId  = baseId + '_hidden';
    var $hidden = $('#'+hidId);

    if (disable) {
      $input.prop('disabled', true)
            .prop('required', false)
            .addClass('opacitymedium');

      if (!$hidden.length) {
        $hidden = $('<input>', {type:'hidden', id:hidId, name:name, value:(zeroValue!=null?zeroValue:'0')});
        $input.after($hidden);
      } else {
        $hidden.val(zeroValue!=null?zeroValue:'0');
      }
    } else {
      $input.prop('disabled', false)
            .prop('required', true) // requerido solo cuando está habilitado
            .removeClass('opacitymedium');
      if ($hidden.length) $hidden.remove();
    }
  }

  function syncTierra() {
    var yes = $('input[name="form[TIERRA_EXISTE]"][value="SI"]').is(':checked');
    setDisabledWithHidden($('#tierra_val'), !yes, '0');
  }
  function syncAislamiento() {
    var yes = $('input[name="form[AISLAMIENTO]"][value="SI"]').is(':checked');
    setDisabledWithHidden($('#aisla_val'), !yes, '0');
  }
  function syncAscensor() {
    var yes = $('input[name="form[ASCENSOR]"][value="SI"]').is(':checked');
    setDisabledWithHidden($('#pot_asc'), !yes, '0');
  }

  $('input[name="form[TIERRA_EXISTE]"]').on('change', syncTierra);
  $('input[name="form[AISLAMIENTO]"]').on('change', syncAislamiento);
  $('input[name="form[ASCENSOR]"]').on('change', syncAscensor);

  
  syncTierra();
  syncAislamiento();
  syncAscensor();


  $('form').on('submit', function(){
    syncTierra(); syncAislamiento(); syncAscensor();
  });
});
