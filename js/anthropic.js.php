<?php
$res = 0;
if (! $res && file_exists("../../main.inc.php")) $res = @include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res = @include("../../../main.inc.php");
if (! $res && file_exists("../../../../main.inc.php")) $res = @include("../../../../main.inc.php");
if (! $res) die("Include of main fails");

header('Content-Type: application/javascript; charset=UTF-8');
global $langs;
$langs->load("dolielec@dolielec");

?>

jQuery(document).ready(function($) {
if (!$('#anthropic_api_key').length) return;
// Botón: comprobar conexión

    $('#anthropic_check_api').click(function(e) {

        e.preventDefault();

        let api_key = $('#anthropic_api_key').val();

        if (!api_key || api_key.trim() === '') {

            alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv("MissingAPIKey")); ?>");

            return;

        }

        $.ajax({

            url: "<?php echo dol_buildpath('/custom/dolielec/ajax/anthropic.ajax.php', 1); ?>",

            method: "POST",

            dataType: "json",

            data: {

                action: "check_api_conn",

                api_key: api_key

            },

            success: function(response) {

                if (response.success) {

                    if (response.models && Array.isArray(response.models)) {

                        let $modelSelect = $('#anthropic_model');

                        $modelSelect.empty();

                        response.models.forEach(function(model) {

                            $modelSelect.append($('<option>', {

                                value: model,

                                text: model

                            }));

                        });

                        if (response.default_model) {

                            $modelSelect.val(response.default_model);

                        }

                        alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv("ConnectionSuccess")); ?>");

                    } else {

                        alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv("NoModels")); ?>");

                    }

                } else {

                    alert(response.message || "<?php echo dol_escape_js($langs->transnoentitiesnoconv("ConnectionFailed")); ?>");

                }

            },

            error: function(xhr, status, error) {

                alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv("ConnectionFailed")); ?>");

            }

        });

    });

    // Botón: comprobar saldo

    $('#check_api_balance').click(function(e) {

        e.preventDefault();

        $.ajax({

            url: "<?php echo dol_buildpath('/custom/dolielec/ajax/openai.ajax.php', 1); ?>",

            method: "POST",

            dataType: "json",

            data: {

                action: "check_api_balance"

            },

            success: function(response) {

                if (response.success && typeof response.saldo_eur !== "undefined") {

                    alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv("DoliElec_Balance")); ?>: " + response.saldo_eur + " €");

                } else {

                    alert(response.message || "<?php echo dol_escape_js($langs->transnoentitiesnoconv("DoliElec_ErrorBalance")); ?>");

                }

            },

            error: function(xhr, status, error) {

                alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv("DoliElec_ErrorBalance")); ?>");

            }

        });

    });

});
