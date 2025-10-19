<?php
/**
 * ile       openai.lib.php
 * \ingroup    dolielec
 * rief      Bibliotecas auxiliares del módulo DoliElec
 * uthor     Fran Torres Gallego
 * ersion    1.0
 */

defined('DOL_DOCUMENT_ROOT') || die('Acceso denegado');

/**
 * Devuelve las pestañas para la configuración del módulo
 *
 * @return array Pestañas para el setup
 */
function dolielecAdminPrepareHead()
{
    global $langs;

    $langs->load("dolielec@dolielec");

    $head = [];

    $head[] = [
        DOL_URL_ROOT."/admin/setup.php",
        $langs->trans("DoliElecSetup"),
        'settings'
    ];

    return $head;
}
