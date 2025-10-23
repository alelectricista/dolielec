<?php
/**
 *  DoliElec - Geolocalización y Costes de desplazamiento
 *  @package    dolielec
 */
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';
class Geoloc {
    private $db;
    private $endpoint;
    private $key;
    private $provider;
    public function __construct($db) {
        global $conf, $langs;
		$langs->loadLangs(array('dolielec@dolielec'));
        $this->db = $db;
        $this->key = !empty($GLOBALS['conf']->global->DOLIELEC_GEO_API_KEY) ? $GLOBALS['conf']->global->DOLIELEC_GEO_API_KEY : null;
        $this->provider = $conf->global->DOLIELEC_GEO_PROVIDER ?? '';
        $this->endpoint = '';
        if ($this->provider === 'google') {
            $this->endpoint = 'https://maps.googleapis.com/maps/api/directions/json';
        }
        if ($this->provider === 'ors') {
            $this->endpoint = 'https://api.openrouteservice.org/';
        }
    }

    private function call($path, $method = 'GET', $data = null) {
        global $langs;
        if (empty($this->key)) {
            return array('success' => false, 'message' => $langs->trans('MissingAPIKey'));
        }
        if (empty($this->provider)) {
            return array('success' => false, 'message' => $langs->trans('NoAPIProviderConfigured'));
        }

        $url = $this->endpoint;
        $opts = array(
            'method' => $method,
            'timeout' => 25,
            'accept_json' => 1,
            'decode_json' => 1,
        );

        if ($this->provider === 'google') {
            $url .= $path . (strpos($path, '?') === false ? '?' : '&') . 'key=' . rawurlencode($this->key);
        } elseif ($this->provider === 'ors') {
            $url .= ltrim($path, '/');
            $opts['bearer'] = $key;
            $opts['headers'] = array('Content-Type: application/json');
        } else {
            return array('success' => false, 'message' => $langs->trans('UnsupportedAPIProvider'));
        }

        if ($method === 'POST' && $data !== null) {
            $opts['body'] = $data;
            $opts['json_body'] = 1;
        }

        return apicall($url, $opts);
    }

    //geting the operational base address
    public function getAddrBase($origin = null): array {
        if ($origin === null) {
            global $mysoc;
            $origin = $mysoc ?? null;
        }        if (empty($origin)) {
            return array();
        }
        $address = (string)($origin->address ?? '');
        $zip = (string)($origin->zip ?? '');
        $town = (string)($origin->town ?? '');
        $state = (string)($origin->state ?? '');
        $country = (string)($origin->country_code ?? $origin->country ?? '');
        $build_address = array_filter(array($address, $zip, $town, $state, $country));
        $line = implode(', ', $build_address);
        return array('line' => $line);
    }
    //checking if is client thirdparty
    public function isClient($check = null): bool {
        if ($check === null) {
            global $object;
            $check = $object ?? null;
        }
        if (empty($check) || !isset($check->client)) {
            return false;
        }
        return ((int)$check->client === 1);
    }

    //get the client address and format it a line to API invoque
    public function getAddr($loc = null): array {
        if ($loc === null) {
            global $object;
            $loc = $object;
        }
        if (empty($loc)) {
            return array();
        }
        $address = (string)($loc->address ?? '');
        $zip = (string)($loc->zip ?? '');
        $town = (string)($loc->town ?? '');
        $state = (string)($loc->state ?? '');
        $fk_departement = isset($loc->fk_departement) ? (int)$loc->fk_departement : (isset($loc->state_id) ? (int)$loc->state_id : null);
        $fk_pays = isset($loc->fk_pays) ? (int)$loc->fk_pays : (isset($loc->country_code) ? (int)$loc->country_code : null);
        $country_code = (string)($loc->country_code ?? '');
        $country = (string)($loc->country ?? '');
        $build_address = array_filter(array($address, $zip, $town, $state, $country));
        $line = implode(', ', $build_address);
        return array('line' => $line);
    }

    //geting the mode (or modes) for traveling
    public function getTravelMode($mode = null): array {
        $modes = array();
        if (getDolGlobalInt('DOLIELEC_MODE_DRIVE', 0) === 1) $modes[] = 'driving';
        if (getDolGlobalInt('DOLIELEC_MODE_FOOT', 0) === 1) $modes[] = 'walking';
        if (getDolGlobalInt('DOLIELEC_MODE_TRANSIT_TRAIN', 0) === 1) $modes[] = 'train';
        if (getDolGlobalInt('DOLIELEC_MODE_TRANSIT_BUS', 0) === 1) $modes[] = 'bus';
        if (getDolGlobalInt('DOLIELEC_MODE_TRANSIT_METRO', 0) === 1) $modes[] = 'metro';
        if (getDolGlobalInt('DOLIELEC_MODE_BIKE', 0) === 1) $modes[] = 'bicycle';
        if ($mode !== null) {
            return in_array($mode, $modes, true) ? array($mode) : array();
        }
        return $modes;
    }

    //geting avoids to make routes
    public function getTravelAvoids($avoid = null): array {
        $avoids = array();
        if (getDolGlobalInt('DOLIELEC_GEO_AVOID_HIGHWAYS', 0) === 1) $avoids[] = 'highways';
        if (getDolGlobalInt('DOLIELEC_AVOID_TOLLS', 0) === 1) $avoids[] = 'tolls';
        if (getDolGlobalInt('DOLIELEC_AVOID_FERRIES', 0) === 1) $avoids[] = 'ferries';
        if (getDolGlobalInt('DOLIELEC_AVOID_STAIRS', 0) === 1) $avoids[] = 'stairs';
        if (getDolGlobalInt('DOLIELEC_AVOID_HILLS', 0) === 1) $avoids[] = 'hills';
        if (getDolGlobalInt('DOLIELEC_AVOID_CROWDS', 0) === 1) $avoids[] = 'crowds';
        if (getDolGlobalInt('DOLIELEC_AVOID_FORDS', 0) === 1) $avoids[] = 'fords';
        if ($avoid !== null) {
            return in_array($avoid, $avoids, true) ? array($avoid) : array();
        }
        return $avoids;
    }
    public function getCost(): array {
        global $langs;

        $provider = $this->provider;
        if ($provider !== 'google' && $provider !== 'ors') {
            return array('success' => false, 'message' => $langs->trans('NoAPIProviderConfigured'));
        }

        $data = ($provider === 'google') ? $this->google() : $this->ors();
        if (empty($data['success'])) {
            return $data;
        }

        $dist_km = (float) ($data['distance_km'] ?? 0);
        $cost_km = (float) getDolGlobalString('DOLIELEC_GEO_COST_KM');

        if ($dist_km <= 0 || $cost_km <= 0) {
            return array(
                'success' => false,
                'message' => $langs->trans('InvalidDistanceOrCost'),
                'distance_km' => $dist_km,
                'cost_km' => $cost_km
            );
        }

        $total = price2num($dist_km * $cost_km * 2, 2); // ida y vuelta
        $data['cost_eur'] = $total;

        return $data;
    }

    /**
     * Guardar coste de desplazamiento en el cliente
     */
    public function setToClient($client, array $data): array {
        global $langs;

        if (!$this->isClient($client)) {
            return array('success' => false, 'message' => $langs->trans('NotAClientObject'));
        }

        if (empty($data['cost_eur'])) {
            return array('success' => false, 'message' => $langs->trans('MissingTravelCost'));
        }

        $client->array_options['options_dolielec_travel_cost_eur'] = $data['cost_eur'];

        $res = $client->update($client->id, $this->db);
        if ($res <= 0) {
            return array('success' => false, 'message' => $langs->trans('ErrorSavingTravelCost'));
        }

        return array('success' => true, 'message' => $langs->trans('TravelCostSaved'), 'cost_eur' => $data['cost_eur']);
    }
}