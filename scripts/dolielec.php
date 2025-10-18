#!/usr/bin/env php
<?php
/* DoliElec – CLI commands (Dolibarr 21)
 * Ejecuta trabajos del módulo desde línea de comandos (sin HTTP/Cloudflare).
 * 
 * Ejemplos:
 *   php dolielec.php --job=refresh-travel-costs --limit=200 --budget-s=80
 *   php dolielec.php --job=rtc --reset-pointer
 *   php dolielec.php --all
 *   php dolielec.php --help
 */

$error = 0;

// -----------------------------------------------------------------------------
// 1) Bootstrap Dolibarr (buscar main.inc.php subiendo directorios)
// -----------------------------------------------------------------------------
$here = __DIR__;
$dir = $here;
$main = null;
for ($i = 0; $i < 6; $i++) {
    if (is_file($dir.'/main.inc.php')) { $main = $dir.'/main.inc.php'; break; }
    $parent = dirname($dir);
    if ($parent === $dir) break;
    $dir = $parent;
}
if (!$main && is_file($here.'/../../main.inc.php')) $main = $here.'/../../main.inc.php';
if (!$main) { fwrite(STDERR, "ERROR: No se encontró main.inc.php\n"); exit(2); }

require_once $main;

// -----------------------------------------------------------------------------
// 2) Cargar clases del módulo
// -----------------------------------------------------------------------------
if (file_exists(DOL_DOCUMENT_ROOT.'/custom/dolielec/class/geoloc.class.php')) {
    require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/class/geoloc.class.php';
} else {
    fwrite(STDERR, "ERROR: Falta custom/dolielec/class/geoloc.class.php\n");
    exit(2);
}

// -----------------------------------------------------------------------------
// 3) Parseo de argumentos
// -----------------------------------------------------------------------------
$longopts = array(
    "job::",            // nombre del job
    "all",              // ejecutar todos los jobs disponibles
    "limit::",          // tamaño de lote
    "budget-s::",       // presupuesto en segundos
    "entity::",         // id entidad (por defecto conf->entity)
    "reset-pointer",    // resetear punteros internos
    "help", "h",        // ayuda
    "verbose", "v"
);
$opts = getopt("", $longopts);

if (isset($opts['help']) || isset($opts['h'])) {
    echo "Uso: php dolielec.php [--job=refresh-travel-costs|rtc|--all] [--limit=N] [--budget-s=SEG] [--entity=E] [--reset-pointer]\n";
    exit(0);
}

// Verbose
$verbose = isset($opts['verbose']) || isset($opts['v']);

// Entidad
if (!empty($opts['entity'])) {
    $e = (int) $opts['entity'];
    if ($e > 0) $conf->entity = $e;
}

// Validar módulo activo
if (empty($conf->dolielec->enabled)) {
    fwrite(STDERR, "ERROR: El módulo dolielec no está activo en la entidad ".$conf->entity."\n");
    exit(3);
}

// -----------------------------------------------------------------------------
// 4) Helpers de ejecución
// -----------------------------------------------------------------------------
function run_refresh_travel_costs($limit, $budget_s, $reset, $verbose) {
    global $db, $conf;

    if (function_exists('dol_set_time_limit')) { dol_set_time_limit(0); } else { @set_time_limit(0); }
    @ignore_user_abort(true);

    $geo = new Geoloc($db);

    if ($reset) {
        dolibarr_set_const($db, 'DOLIELEC_CRON_LAST_SOCID', 0, 'integer', 0, '', $conf->entity);
        if ($verbose) echo "[RTC] Puntero reiniciado\n";
    }

    $params = array('limit'=>(int)$limit, 'budget_s'=>(int)$budget_s);
    if (!method_exists($geo, 'cronRefreshTravelCosts')) {
        echo "[RTC] Geoloc::cronRefreshTravelCosts no existe\n";
        return 1;
    }

    $rc = $geo->cronRefreshTravelCosts($params);

    $msg = '';
    if (property_exists($geo, 'lastresult')) $msg = $geo->lastresult;
    if (!$msg && property_exists($geo, 'output')) $msg = $geo->output;
    if ($verbose) echo "[RTC] rc=$rc ".($msg ? $msg : '')."\n";

    return (int)$rc;
}

// -----------------------------------------------------------------------------
// 5) Dispatcher de comandos
// -----------------------------------------------------------------------------
$job      = isset($opts['job']) ? $opts['job'] : null;
$runAll   = isset($opts['all']);
$limit    = isset($opts['limit']) ? (int)$opts['limit'] : 200;
$budget_s = isset($opts['budget-s']) ? (int)$opts['budget-s'] : 80;
$reset    = isset($opts['reset-pointer']);

$db->begin();

if ($runAll) {
    $rc = 0;
    $rc |= run_refresh_travel_costs($limit, $budget_s, $reset, $verbose);
    $error = $rc;
} else {
    if (!$job) {
        fwrite(STDERR, "ERROR: Debes indicar --job=... o --all (usa --help)\n");
        $error = 1;
    } else {
        switch ($job) {
            case 'refresh-travel-costs':
            case 'rtc':
                $error = run_refresh_travel_costs($limit, $budget_s, $reset, $verbose);
                break;
            default:
                fwrite(STDERR, "ERROR: Job desconocido: ".$job."\n");
                $error = 1;
        }
    }
}

// -------------------- END OF YOUR CODE --------------------

if (!$error) {
	$db->commit();
	print '--- end ok'."\n";
} else {
	print '--- end error code='.$error."\n";
	$db->rollback();
}

$db->close(); // Close $db database opened handler

exit($error);
