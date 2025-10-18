<?php
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/class/profit.class.php';
class InterfaceDolielec extends DolibarrTriggers {
    public $db;
    public function __construct($db) {
        $this->db = $db;
    }
//the first trigger, set the pvp price.
    public function runTrigger($action, $object, $user, $langs, $conf) {
        if (!in_array($action, array('PRODUCT_SUPPLIER_PRICE_CREATE', 'PRODUCT_SUPPLIER_PRICE_UPDATE'))) {
            return 0;
        }
        $refSupplier = $object->ref_supplier;
        $buyPriceHT = $object->fourn_pu;
        if (empty($refSupplier)) {
            dol_syslog(__METHOD__.': ref_supplier vacío, abort', LOG_WARNING);
            return 0;
        }
        $profit = new Profit($this->db);
        $result = $profit->getPrice($refSupplier, $buyPriceHT);
        if (!$result['success']) {
            dol_syslog(__METHOD__.': Profit->getPrice KO: '.$result['message'], LOG_WARNING);
        }
        return 0;
    }
}
