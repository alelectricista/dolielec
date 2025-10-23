<?php
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/class/profit.class.php';

class InterfaceProfitTriggers extends DolibarrTriggers {
    public $db;
    public function __construct($db) {
        parent::__construct($db);
        $this->db = $db;
        $this->family = 'dolielec';
        $this->description = 'Profitability/Prices/RPO triggers';
        $this->version = self::VERSIONS['dev'];
    }
    public function runTrigger($action, $object, $user, $langs, $conf) {
        if (!in_array($action, array('PRODUCT_SUPPLIER_PRICE_CREATE', 'PRODUCT_SUPPLIER_PRICE_MODIFY'))) {
            return 0;
        }
       
        $refSupplier = isset($object->ref_supplier) ? $object->ref_supplier : '';
        $buyPriceHT = isset($object->fourn_price) ? $object->fourn_price : (isset($object->fourn_pu) ? $object->fourn_pu : 0);
        
        if (empty($refSupplier)) {
            dol_syslog(__METHOD__.': ref_supplier vacío, abortando', LOG_WARNING);
            return 0;
        }
        if ($buyPriceHT <= 0) {
            dol_syslog(__METHOD__.': Precio compra inválido, abortando', LOG_WARNING);
            return 0;
        }
                $profit = new Profit($this->db);
        $result = $profit->getPrice($refSupplier, $buyPriceHT);
              if (!$result['success']) {
            dol_syslog(__METHOD__.': Profit->getPrice KO: '.$result['message'], LOG_WARNING);
            return -1;
        }
             dol_syslog(__METHOD__.': Precio calculado automáticamente: '.$result['price'].' EUR', LOG_INFO);
        return 0;
    }
}