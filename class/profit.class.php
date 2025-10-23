<?php
/**file profit.class.php
file dedicated to RPO, prices and profitability engine of dolielec
*/
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
include DOL_DOCUMENT_ROOT.'/custom/dolielec/class/anthropic.class.php';
include DOL_DOCUMENT_ROOT.'/custom/dolielec/class/geoloc.class.php';
include DOL_DOCUMENT_ROOT.'/custom/dolielec/class/google.class.php';
include DOL_DOCUMENT_ROOT.'/custom/dolielec/class/openai.class.php';
include DOL_DOCUMENT_ROOT.'/custom/dolielec/class/doc.class.php';
//Class for profitability, pricing and RPO AI engine.
//Uses a openai engines, gemini and claude APIs for take automatic decissions.
//Also can use another system human memories.
class Profit {
	private $db;
public function __construct($db) {
	global $langs, $user;
	$langs->loadLangs(array('dolielec@dolielec'));
		$this->db = $db;
}
		public function getPrice($ref_supplier, $fourn_pu) {
			global $langs, $user;
			$cai=new ClaudeAI($this->db);
			$google = new GoogleAI($this->db);
						$openai = new OpenAI($this->db);
			$product = new Product ($this->db);
			$pf = new ProductFournisseur($this->db);
						if(empty($api_key)) {
				return array('success' => false, 'message' => $langs->trans('MissingAPIKey'));
			}
			if(empty($ref_supplier)) {
				return array('success' => false, 'message' => $langs->trans('MissingSupplier'));
			}
			if(empty($fourn_pu) || $fourn_pu < 0) {
				return array('success' => false, 'message' => $langs->trans('MissingPriceSupplierOrPriceInvalid'));
			}
			$res = $pf->fetch(0, $ref_supplier);
			if ($res <= 0) {
				return array('success' => false, 'message' => $langs->trans('ProductSupplierNotFound'));
			}
			if ($product->fetch($pf->fk_product) <= 0) {
	return array('success' => false, 'message' => $langs->trans('ProductNotFound'));
}
$cleanPrice = price2num($fourn_pu, 2);
						$rsystem= 'Eres Biel, un comercial y contable experto en el mercado del sector eléctrico. Tu misión es calcular el precio de venta más óptimo con la máxima rentabilidad neutralizando cualquier tipo de competencia. Debes buscar el fabricante y el precio de venta recomendado y el precio de venta al público según la referencia del fabricante dada.';
			$ruser = 'calcula el mejor precio de venta para  el producto con Referencia '.$ref_supplier.' que tiene un precio unitario de '.$cleanPrice.'. Debes analizar el mercado en profundidad, revisando: Grandes superficies como leroy merlin, obramat, amazon, aliexpress, etc; pequeñas superficies como bazares, ferreterías de barrio; y devolver un  precio que garantice ganancias, rentabilidad y el mayor margen posible, neutralizando cualquier tipo de competencia; según lo indicado. Debes devolver un json válido y la respuesta debe estar formada por solo números. Debe tener una forma como esta: {"price":valor}';
			try {
			$data = $openai->setPrompt(0.0, 0.9, 'gpt5-thinking', '', 800, $rsystem, $ruser, $api_key);
			}
			catch(Exception $e) {
				return array('success' => false, 'message' => $langs->trans('OpenAIError').': '.$e->getMessage());
			}
						$decode = json_decode($data, true);
						if(!$decode || !isset($decode['price'])) {
															return array('success' => false, 'message' => $langs->trans('ResponseInvalid'));
			}
			$price = price2num($decode['price'], 2);
						if ($price < 0) {
				return array('success' => false, 'message' => $langs->trans('PriceInvalid'));
			}
						$update = $product->updatePrice($price, 'HT', $user, 0, 0, 0);
			if ($update >= 0) {
				return array('success' => true, 'message' => $langs->trans('PriceUpdated'));
			}
			else {
				return array('success' => false, 'message' => $langs->trans('UpdateFailed'));
			}
			}
		}					