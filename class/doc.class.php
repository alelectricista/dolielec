<?php
// custom/dolielec/class/doc.class.php
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

class Documentation {
	private $db;

	public function __construct($db) {
		global $langs;
		$langs->loadLangs(array('main','companies','dolielec@dolielec'));
		$this->db = $db;
	}

	/**
	 * Devuelve info de la empresa instaladora / firmante (mysoc + user si procede)
	 * @return array
	 */
	public function getInst() {
		global $mysoc, $user;
		$out = array(
			'type'      => 'company',
			'display'   => $mysoc->name,
			'nif'       => $mysoc->idprof1,
			'rasic'     => $mysoc->idprof4,
			'firstname' => '',
			'lastname'  => '',
			'niftech'   => ''
		);
		if (!empty($user->firstname) || !empty($user->lastname)) {
			$out['type']      = 'person';
			$out['firstname'] = $user->firstname;
			$out['lastname']  = $user->lastname;
			$out['display']   = trim(($user->firstname!=''?$user->firstname.' ':'').$user->lastname);
			$out['niftech']   = $user->user_mobile ? $user->user_mobile : '';
		}
		return $out;
	}

	/**
	 * Construye array de datos para BRIE a partir del form y un tercero opcional
	 * @param array $form
	 * @param int   $socid
	 * @return array
	 */
	public function setBrie($form, $socid) {
		global $db, $conf;

		$data = is_array($form) ? $form : array();

		if (!empty($socid)) {
			$soc = new Societe($db);
			if ($soc->fetch($socid) > 0) {
				if (empty($data['TIT_NOMBRE']))    $data['TIT_NOMBRE']   = $soc->name;
				if (empty($data['TIT_NIF']))       $data['TIT_NIF']      = $soc->idprof1;
				if (empty($data['TIT_TLF']))       $data['TIT_TLF']      = $soc->phone;
				if (empty($data['TIT_DOM']))       $data['TIT_DOM']      = $soc->address;
				if (empty($data['TIT_CP']))        $data['TIT_CP']       = $soc->zip;
				if (empty($data['TIT_LOCALIDAD'])) $data['TIT_LOCALIDAD']= $soc->town;
			}
		}

		// defaults mínimos
		if (empty($data['BRIE_FECHA'])) $data['BRIE_FECHA'] = dol_print_date(dol_now(), '%Y-%m-%d');
		if (!isset($data['ASCENSOR'])) $data['ASCENSOR'] = 'NO';
		if (!isset($data['TIERRA_EXISTE'])) $data['TIERRA_EXISTE'] = 'NO';
		if (!isset($data['AISLAMIENTO'])) $data['AISLAMIENTO'] = 'NO';

		return $data;
	}

	/**
	 * Genera un ODT a partir de una plantilla.
	 * Aquí no inventamos motor: si no tienes sustitución de marcadores, se copia tal cual.
	 * @param string $template Ruta a la plantilla .odt
	 * @param string $output   Ruta al ODT de salida
	 * @param array  $data     Datos del formulario (si conectas un motor, úsalo aquí)
	 * @return array ['success'=>bool,'file'=>string|false,'message'=>string]
	 */
	public function renderODT($template, $output, $data=array()) {
		global $langs;
		$dir = dirname($output);
		if (!is_dir($dir)) dol_mkdir($dir);
		if (!is_readable($template)) {
			return array('success'=>false,'file'=>false,'message'=>$langs->trans('TemplateNotFound'));
		}

		// TODO: conectar tu motor ODT si lo tienes (TBS/OpenDocument). Por ahora, copia simple.
		if (!dol_copy($template, $output, 0, 1)) {
			return array('success'=>false,'file'=>false,'message'=>'CopyFail');
		}
		return array('success'=>true,'file'=>$output,'message'=>'OK');
	}

	/**
	 * Convierte ODT?PDF usando soffice/unoconv si están disponibles
	 * @param string $odt
	 * @param string $pdf
	 * @return array
	 */
	public function odtToPdf($odt, $pdf) {
		if (!is_readable($odt)) return array('success'=>false,'file'=>false,'message'=>'ODTMissing');

		if (file_exists($pdf)) @unlink($pdf);

		global $conf;

		$bin = !empty($conf->global->MAIN_PATH_SOFFICE) ? $conf->global->MAIN_PATH_SOFFICE : '';
		if ($bin == '') $bin = trim(@shell_exec('command -v soffice 2>/dev/null'));
		if ($bin != '') {
			$cmd = escapeshellcmd($bin).' --headless --convert-to pdf --outdir '.escapeshellarg(dirname($pdf)).' '.escapeshellarg($odt).' 2>/dev/null';
			@shell_exec($cmd);
			if (is_readable($pdf)) return array('success'=>true,'file'=>$pdf,'message'=>'OK');
		}

		$unoconv = trim(@shell_exec('command -v unoconv 2>/dev/null'));
		if ($unoconv != '') {
			$cmd = escapeshellcmd($unoconv).' -f pdf -o '.escapeshellarg($pdf).' '.escapeshellarg($odt).' 2>/dev/null';
			@shell_exec($cmd);
			if (is_readable($pdf)) return array('success'=>true,'file'=>$pdf,'message'=>'OK');
		}

		return array('success'=>false,'file'=>false,'message'=>'NoConverter');
	}

	/**
	 * Firma un PDF. Si no hay firmador configurado, copia tal cual.
	 * Puedes definir DOLIELEC_CERT_FILE, DOLIELEC_CERT_PASS y/o DOLIELEC_SIGN_CMD.
	 * SIGN_CMD admite tokens: %in %out %cert %pass
	 * @param string $srcPdf
	 * @param string $destPdf
	 * @return bool
	 */
	public function setSign($srcPdf, $destPdf) {
		global $conf;

		$dir = dirname($destPdf);
		if (!is_dir($dir)) dol_mkdir($dir);

		$cert = !empty($conf->global->DOLIELEC_CERT_FILE) ? $conf->global->DOLIELEC_CERT_FILE : '';
		$pass = !empty($conf->global->DOLIELEC_CERT_PASS) ? $conf->global->DOLIELEC_CERT_PASS : '';
		$cmdt = !empty($conf->global->DOLIELEC_SIGN_CMD) ? $conf->global->DOLIELEC_SIGN_CMD : '';

		// ruta rápida: comando externo si está definido
		if ($cmdt != '') {
			$cmd = strtr($cmdt, array(
				'%in'   => escapeshellarg($srcPdf),
				'%out'  => escapeshellarg($destPdf),
				'%cert' => escapeshellarg($cert),
				'%pass' => escapeshellarg($pass)
			));
			@shell_exec($cmd.' 2>/dev/null');
			return is_readable($destPdf);
		}

		// fallback: sin firmar (copia)
		if (!dol_copy($srcPdf, $destPdf, 0, 1)) return false;
		return true;
	}

	/**
	 * Adjunta un fichero al ECM del tercero
	 * @param int    $socid
	 * @param string $absfile
	 * @return string Ruta destino o '' si falla
	 */
	public function attachToThirdparty($socid, $absfile) {
		global $conf, $db;

		$soc = new Societe($db);
		if ($soc->fetch($socid) <= 0) return '';

		$upload_dir = $conf->societe->dir_output.'/'.$soc->id.'/documents';
		if (!is_dir($upload_dir)) dol_mkdir($upload_dir);

		$dest = $upload_dir.'/'.basename($absfile);
		if (!dol_copy($absfile, $dest, 0, 1)) return '';

		return $dest;
	}
}
