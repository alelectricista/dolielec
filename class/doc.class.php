<?php
// class for manage internal documentation for dolielec module.
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/lib/dolielec.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
use setasign\Fpdi\Tcpdf\Fpdi;
class Documentation {
    private $db;
    private $odtype = null;
    private $odpath = null;
    public function __construct($db) {
        global $langs;
        $langs->loadLangs(array('dolielec@dolielec'));
        $this->db = $db;
    }
    public function getInst() {
        global $conf, $mysoc;
        $legal     = (int)($mysoc->fk_forme_juridique ?? 0);
        $firstname = trim((string)($conf->global->MAIN_INFO_SOCIETE_FIRSTNAME ?? $mysoc->firstname ?? ''));
        $lastname  = trim((string)($conf->global->MAIN_INFO_SOCIETE_LASTNAME  ?? $mysoc->lastname  ?? ''));
        $company   = trim((string)($conf->global->MAIN_INFO_SOCIETE_NOM       ?? $mysoc->name      ?? ''));
        $nif       = trim((string)($conf->global->MAIN_INFO_SOCIETE_IDPROF1   ?? $mysoc->idprof1   ?? ''));
        $rasic     = trim((string)($mysoc->idprof4 ?? ''));
        $isPerson  = ($legal === 401);
        $display   = $isPerson
            ? ( ($firstname !== '' || $lastname !== '') ? trim($firstname.' '.$lastname) : $company )
            : $company;
        return array(
            'type'      => $isPerson ? 'person' : 'company',
            'display'   => $display,
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'company'   => $company,
            'nif'       => $nif,
            'rasic'     => $rasic
        );
    }

    private function runCmd($cmd, &$lastline, &$stdout, &$stderr) {
        $descriptorspec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
        $process = @proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            $stdout = ''; $stderr = 'proc_open failed';
            $lastline = '';
            return 127;
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $status = proc_close($process);
        $lines = preg_split('/\r\n|\r|\n/', trim($stdout));
        $lastline = end($lines);
        return $status;
    }

    private function getProjectRules() {
        return [
            ['group' => 'a', 'type' => 'GeneralIndustries', 'operator' => '>', 'value' => 20, 'unit' => 'kw'],
            ['group' => 'b', 'type' => 'RiscAndHumidityPlacesAndWaterEngines', 'operator' => '>', 'value' => 10, 'unit' => 'kw'],
            ['group' => 'c', 'type' => 'GeneratorsAndConvertersAndHeating', 'operator' => '>', 'value' => 10, 'unit' => 'kw'],
            ['group' => 'd', 'type' => 'temporally', 'operator' => '>', 'value' => 50, 'unit' => 'kw'],
            ['group' => 'e', 'type' => 'VerticalHomeOffices', 'operator' => '>', 'value' => 100, 'unit' => 'kw'],
            ['group' => 'f', 'type' => 'Houses', 'operator' => '>', 'value' => 50, 'unit' => 'kw'],
            ['group' => 'g', 'type' => 'ParkingWithForcedCooling', 'operator' => null, 'value' => null, 'unit' => null],
            ['group' => 'h', 'type' => 'parkingWithoutForcedCooling', 'operator' => '>', 'value' => 5, 'unit' => 'plazas'],
            ['group' => 'i', 'type' => 'PublicConcurrency', 'operator' => null, 'value' => null, 'unit' => null],
            ['group' => 'j', 'type' => 'SpecialUses', 'operator' => null, 'value' => null, 'unit' => null],
            ['group' => 'k', 'type' => 'ExternalLightning', 'operator' => '>', 'value' => 5, 'unit' => 'kw'],
            ['group' => 'l', 'type' => 'RiscExplodePlacesExcludingStations', 'operator' => null, 'value' => null, 'unit' => null],
            ['group' => 'm', 'type' => 'InterventionHalls', 'operator' => null, 'value' => null, 'unit' => null],
            ['group' => 'n', 'type' => 'SwimmingPools', 'operator' => '>', 'value' => 5, 'unit' => 'kw'],
            ['group' => 'z', 'type' => 'IRVE', 'operator' => '>', 'value' => 50, 'unit' => 'kw'],
            ['group' => 'z', 'type' => 'IRVEExternal', 'operator' => '>', 'value' => 10, 'unit' => 'kw'],
            ['group' => 'z', 'type' => 'IRVEMode4', 'operator' => null, 'value' => null, 'unit' => 'mode4'],
            ['group' => 'o', 'type' => 'OthersUnlistedAndDisposedByMinistry', 'operator' => null, 'value' => null, 'unit' => null]
        ];
    }

    private function getInspectionRules() {
        return array(
            ['group' => 'a', 'type' => 'IndustrialInstallations', 'operator' => '>', 'value' => 100, 'unit' => 'kw'],
            ['group' => 'b', 'type' => 'PublicConcurrency', 'operator' => null, 'value' => null, 'unit' => null],
            ['group' => 'c', 'type' => 'RiscExplosionExcludingStations', 'operator' => '<', 'value' => 25, 'unit' => 'plazas'],
            ['group' => 'd', 'type' => 'HidricPlacesWithInstalled', 'operator' => '>', 'value' => 25, 'unit' => 'kw'],
            ['group' => 'e', 'type' => 'SwimmingPools', 'operator' => '>', 'value' => 25, 'unit' => 'kw'],
            ['group' => 'f', 'type' => 'InterventionHalls', 'operator' => null, 'value' => null, 'unit' => null],
            ['group' => 'g', 'type' => 'ExternalLightning', 'operator' => '>', 'value' => 5, 'unit' => 'kw'],
            ['group' => 'h', 'type' => 'IRVE', 'operator' => null, 'value' => null, 'unit' => null]
        );
    }

    private function getICoef() {
        return [
            'a' => ['bare' => 0,   'bundle' => 0],
            'b' => ['bare' => 180, 'bundle' => 60],
            'c' => ['bare' => 360, 'bundle' => 120]
        ];
    }

    private function detectOdtConverter() {
        global $conf, $langs;
        if (!empty($this->odtype) && !empty($this->odpath)) {
            return array('success'=>true, 'type'=>$this->odtype, 'path'=>$this->odpath);
        }
        $candidates = array(
            'MAIN_PATH_UNOCONV','MAIN_PATH_SOFFICE','MAIN_UNOCONV_PATH',
            'MAIN_LO_CONVERTER','MAIN_ODT_TO_PDF','MAIN_ODT_TO_PDF_CMD','MAIN_OO_OFFICE_BINARY',
        );
        $resolve = function ($bin) {
            if (empty($bin)) return '';
            if (@is_file($bin) && @is_executable($bin)) return $bin;
            $finder = (stripos(PHP_OS, 'WIN') === 0) ? 'where' : 'which';
            $desc = array(1 => array('pipe','w'), 2 => array('pipe','w'));
            $proc = @proc_open($finder.' '.escapeshellarg($bin), $desc, $pipes);
            if (is_resource($proc)) {
                $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
                $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
                @proc_close($proc);
                $path = trim($out);
                if ($path !== '' && @is_file($path) && @is_executable($path)) return $path;
            }
            return '';
        };
        foreach ($candidates as $ck) {
            if (!empty($conf->global->$ck)) {
                $val = trim($conf->global->$ck);
                $v   = dol_strtolower($val);
                $typeHint = '';
                if (strpos($v, 'unoconv') !== false) $typeHint = 'unoconv';
                if (strpos($v, 'soffice') !== false || strpos($v, 'libreoffice') !== false) $typeHint = 'soffice';
                $path = $resolve($val);
                if (!$path && $typeHint === 'unoconv') $path = $resolve('unoconv');
                if (!$path && $typeHint === 'soffice') $path = $resolve('soffice');
                if ($path) {
                    $bn = dol_strtolower(basename($path));
                    $type = (strpos($bn, 'unoconv') !== false) ? 'unoconv' : ((strpos($bn, 'soffice') !== false) ? 'soffice' : $typeHint);
                    if ($type === 'unoconv' || $type === 'soffice') {
                        $this->odtype = $type;
                        $this->odpath = $path;
                        return array('success'=>true, 'type'=>$this->odtype, 'path'=>$this->odpath);
                    }
                }
            }
        }
        $path = $resolve('unoconv');
        if ($path) {
            $this->odtype = 'unoconv';
            $this->odpath = $path;
            return array('success'=>true, 'type'=>'unoconv', 'path'=>$path);
        }
        $path = $resolve('soffice');
        if ($path) {
            $this->odtype = 'soffice';
            $this->odpath = $path;
            return array('success'=>true, 'type'=>'soffice', 'path'=>$path);
        }
        return array('success'=>false, 'message'=>$langs->trans('NoODTPDFConverterFound'));
    }

    public function getProject($group, $kw) {
        global $langs;
        if ($kw < 0) return array('success' => false, 'message' => $langs->trans('NoKWValid'));
        if (empty($group)) return array('success' => false, 'message' => $langs->trans('CanNotDecide'));

        foreach ($this->getProjectRules() as $rule) {
            if ($rule['group'] === $group) {
                if ($rule['operator'] === null && $rule['value'] === null) {
                    return array('success' => true, 'message' => $langs->trans($rule['type']));
                }
                if ($rule['operator'] === '>' && $kw > $rule['value'] && $rule['unit'] === 'kw') {
                    return array('success' => true, 'message' => $langs->trans($rule['type']));
                }
                if ($rule['operator'] === '>' && $rule['unit'] === 'plazas' && $kw > $rule['value']) {
                    return array('success' => true, 'message' => $langs->trans($rule['type']));
                }
                if ($rule['group'] === 'z' && $rule['value'] === null && $rule['unit'] === 'mode4') {
                    return array('success' => true, 'message' => $langs->trans($rule['type']));
                }
            }
        }
        return array('success' => false, 'message' => $langs->trans('NoProjectRequired'));
    }

    public function getProjectExtense($proj, $power) {
        global $langs;
        if (empty($proj) || $power < 0) return array('success' => false, 'message' => $langs->trans('NoValuesAreValid'));
        $response = $this->getProject($proj, $power);
        if ($response['success'] === true) return array('success' => true, 'message' => $langs->trans('RequireProjectForExtend'));

        foreach ($this->getProjectRules() as $rule) {
            if ($rule['group'] === $proj && $rule['operator'] === '>' && $rule['unit'] === 'kw') {
                $limit = $rule['value'];
                if ($power > ($limit * 0.5)) {
                    return array('success' => true, 'message' => $langs->trans($rule['type']));
                }
            }
        }
        return array('success' => true, 'message' => $langs->trans('RequireMemo'));
    }

    public function getInspection($inst, $grp) {
        global $langs;
        if ($inst === null || $grp === null) {
            return array('success' => false, 'message' => $langs->trans('CantDetermine'));
        }
        foreach ($this->getInspectionRules() as $rule) {
            if ($rule['group'] === $inst && $rule['operator'] === '>' && $grp > $rule['value'] && $rule['unit'] === 'kw') {
                return array('success' => true, 'message' => $langs->trans('RequireInitialInspection'));
            }
            if ($rule['group'] === $inst && $rule['operator'] === '<' && $grp <= $rule['value'] && $rule['unit'] === 'plazas') {
                return array('success' => true, 'message' => $langs->trans('RequireInitialInspection'));
            }
            if ($rule['group'] === $inst && $rule['operator'] === null) {
                return array('success' => true, 'message' => $langs->trans('RequireInitialInspection'));
            }
        }
        return array('success' => false, 'message' => $langs->trans('DoNotRequireInitialInspection'));
    }

    public function getIceZone($altitude) {
        if ($altitude < 500) return 'a';
        if ($altitude <= 1000) return 'b';
        return 'c';
    }

    public function overload($diam, $bundle, $altitude) {
        $zone  = $this->getIceZone($altitude);
        $type  = $bundle ? 'bundle' : 'bare';
        $coef  = $this->getICoef()[$zone][$type];
        return $coef * sqrt($diam);
    }

    private function formatDateDMY($raw) {
        $s = is_string($raw) ? trim($raw) : (string)$raw;
        if ($s === '') return '';
        if (ctype_digit($s) && (strlen($s) >= 9 && strlen($s) <= 13)) {
            $ts = (int) substr($s, 0, 10);
            return dol_print_date($ts, '%d/%m/%Y', 'tzuserrel');
        }
        $ts = @dol_stringtotime($s, 0, 'tzuserrel');
        if (!empty($ts)) return dol_print_date($ts, '%d/%m/%Y', 'tzuserrel');

        $formats = array('d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'd.m.Y');
        foreach ($formats as $f) {
            $dt = \DateTime::createFromFormat($f, $s);
            if ($dt instanceof \DateTime) return $dt->format('d/m/Y');
        }
        return $s;
    }

    public function setSign($srcFile, $destFile = null, $coords = null) {
        global $conf, $langs, $mysoc;
        if ($destFile === null) {
            $pi       = pathinfo($srcFile);
            $destFile = $pi['dirname'].'/'.$pi['filename'].'_signed.'.$pi['extension'];
        }
        $x = 32; $y = 133; $w = 38; $h = 15;
        if (is_array($coords) && count($coords) === 4) {
            [$x, $y, $w, $h] = $coords;
        }
        $cert_file = $conf->global->DOLIELEC_CERT_FILE ?? '';
        $cert_pass = $conf->global->DOLIELEC_CERT_PASS ?? '';
        if (!is_readable($cert_file) || $cert_pass === '') {
            throw new Exception($langs->trans('CertificateOrPassFraseUndefined'));
        }
        $pdf = new Fpdi();
        $pdf->setSignature(
            'file://'.$cert_file,
            'file://'.$cert_file,
            $cert_pass,
            '',
            2,
            array(
                'Name'     => $conf->global->MAIN_INFO_SOCIETE_NOM ?? $mysoc->nom,
                'Reason'   => 'Certificat instal·lació elèctrica',
                'Location' => $conf->global->MAIN_INFO_SOCIETE_TOWN ?? $mysoc->town
            )
        );
        $pageCount = $pdf->setSourceFile($srcFile);
        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl  = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
            $pdf->useTemplate($tpl);
            if ($i === $pageCount) {
                $pdf->setSignatureAppearance($x, $y, $w, $h);
            }
        }
        $pdf->Output($destFile, 'F');
        return $destFile;
    }

    public function setBrie($form, $socid = 0) {
        global $mysoc, $object, $conf;

        $val = function ($v) {
            if (isset($v)) {
                $s = trim($v);
                if (dol_strlen($s) > 0) return $s;
            }
            return '';
        };
        $yn = function ($v) use ($val) {
            $s = dol_strtoupper($val($v));
            if ($s === 'SI' || $s === 'NO') return $s;
            return '';
        };
        $pickPhone = function ($e) use ($val) {
            if (!empty($e) && is_object($e)) {
                if (!empty($e->phone) && $val($e->phone) !== '') return $val($e->phone);
                if (!empty($e->phone_mobile) && $val($e->phone_mobile) !== '') return $val($e->phone_mobile);
                if (!empty($e->mobile) && $val($e->mobile) !== '') return $val($e->mobile);
            }
            return '';
        };

        $data = array();

        // Fecha
        $rawDate = $val(isset($form['BRIE_FECHA']) ? $form['BRIE_FECHA'] : '');
        if ($rawDate !== '') {
            $fmt = $this->formatDateDMY($rawDate);
            $data['BRIE_FECHA'] = $fmt;
            $data['FECHA']      = $fmt;
        }

        // Titular
        $data['CUPS'] = $val(isset($form['CUPS']) ? $form['CUPS'] : '');
        if (!empty($socid)) {
            $third = new Societe($this->db);
            if ($third->fetch($socid) > 0) {
                $data['TIT_NOMBRE']    = $val(isset($third->name) ? $third->name : '');
                $data['TIT_NIF']       = $val(isset($third->idprof1) ? $third->idprof1 : '');
                $data['TIT_TLF']       = $pickPhone($third);
                $data['TIT_EMAIL']     = $val(isset($third->email) ? $third->email : '');
                $data['TIT_DOM']       = $val(isset($third->address) ? $third->address : '');
                $data['TIT_CP']        = $val(isset($third->zip) ? $third->zip : '');
                $data['TIT_LOCALIDAD'] = $val(isset($third->town) ? $third->town : '');
            }
        }
        if (empty($data['TIT_NOMBRE'])    && !empty($object)) $data['TIT_NOMBRE']    = $val(isset($object->name) ? $object->name : '');
        if (empty($data['TIT_NIF'])       && !empty($object)) $data['TIT_NIF']       = $val(isset($object->idprof1) ? $object->idprof1 : '');
        if (empty($data['TIT_TLF'])       && !empty($object)) $data['TIT_TLF']       = $pickPhone($object);
        if (empty($data['TIT_EMAIL'])     && !empty($object)) $data['TIT_EMAIL']     = $val(isset($object->email) ? $object->email : '');
        if (empty($data['TIT_DOM'])       && !empty($object)) $data['TIT_DOM']       = $val(isset($object->address) ? $object->address : '');
        if (empty($data['TIT_CP'])        && !empty($object)) $data['TIT_CP']        = $val(isset($object->zip) ? $object->zip : '');
        if (empty($data['TIT_LOCALIDAD']) && !empty($object)) $data['TIT_LOCALIDAD'] = $val(isset($object->town) ? $object->town : '');

        // Dirección instalación
        $via_form  = $val(isset($form['VIA']) ? $form['VIA'] : '');
        $cp_form   = $val(isset($form['CP']) ? $form['CP'] : '');
        $loc_form  = $val(isset($form['LOCALIDAD']) ? $form['LOCALIDAD'] : '');
        $num_form  = $val(isset($form['NUM']) ? $form['NUM'] : '');
        $piso_form = $val(isset($form['PISO']) ? $form['PISO'] : '');
        $porta_form= $val(isset($form['PORTA']) ? $form['PORTA'] : '');

        if (!empty($socid) && !empty($object)) {
            $data['VIA']       = $val(isset($object->address) ? $object->address : '');
            $data['CP']        = $val(isset($object->zip) ? $object->zip : '');
            $data['LOCALIDAD'] = $val(isset($object->town) ? $object->town : '');
            $data['NUM']       = $num_form;
            $data['PISO']      = $piso_form;
            $data['PORTA']     = $porta_form;
        } else {
            $data['VIA']       = $via_form;
            $data['CP']        = $cp_form;
            $data['LOCALIDAD'] = $loc_form;
            $data['NUM']       = $num_form;
            $data['PISO']      = $piso_form;
            $data['PORTA']     = $porta_form;
        }

        // Datos técnicos
        $data['ACTIVIDAD_ANT'] = $val(isset($form['ACTIVIDAD_ANT']) ? $form['ACTIVIDAD_ANT'] : '');
        $data['ACTIVIDAD_ACT'] = $val(isset($form['ACTIVIDAD_ACT']) ? $form['ACTIVIDAD_ACT'] : '');
        $data['SUPERF']        = $val(isset($form['SUPERF']) ? $form['SUPERF'] : '');
        $data['TENSION_ANT']   = $val(isset($form['TENSION_ANT']) ? $form['TENSION_ANT'] : '');
        $data['TENSION_ACT']   = $val(isset($form['TENSION_ACT']) ? $form['TENSION_ACT'] : '');
        $data['POT_MAX']       = $val(isset($form['POT_MAX']) ? $form['POT_MAX'] : '');
        $data['POT_INICIAL']   = $val(isset($form['POT_INICIAL']) ? $form['POT_INICIAL'] : '');
        $data['POT_CONTRATAR'] = $val(isset($form['POT_CONTRATAR']) ? $form['POT_CONTRATAR'] : '');
        $data['DI_SECCION']    = $val(isset($form['DI_SECCION']) ? $form['DI_SECCION'] : '');
        $data['DIF_NUM']       = $val(isset($form['DIF_NUM']) ? $form['DIF_NUM'] : '');
        $data['DIF_IN']        = $val(isset($form['DIF_IN']) ? $form['DIF_IN'] : '');
        $data['DIF_SENS']      = $val(isset($form['DIF_SENS']) ? $form['DIF_SENS'] : '');

        $tipoProt = $val(isset($form['PROT_GEN_TIPO']) ? $form['PROT_GEN_TIPO'] : '');
        if ($tipoProt !== 'IGA' && $tipoProt !== 'ICPM') $tipoProt = '';
        $intProt  = $val(isset($form['PROT_GEN_INT']) ? $form['PROT_GEN_INT'] : '');
        $data['PROT_GEN_TIPO'] = $tipoProt;
        $data['PROT_GEN_INT']  = $intProt;
        $data['IGA']           = ($tipoProt === 'IGA')  ? $intProt : '';
        $data['ICPM']          = ($tipoProt === 'ICPM') ? $intProt : '';

        $data['TIERRA_EXISTE'] = $yn(isset($form['TIERRA_EXISTE']) ? $form['TIERRA_EXISTE'] : '');
        $data['TIERRA_VALOR']  = $val(isset($form['TIERRA_VALOR']) ? $form['TIERRA_VALOR'] : '');
        $data['AISLAMIENTO']   = $yn(isset($form['AISLAMIENTO']) ? $form['AISLAMIENTO'] : '');
        $data['ASCENSOR']      = $yn(isset($form['ASCENSOR']) ? $form['ASCENSOR'] : '');
        $data['POT_ASCENSOR']  = $val(isset($form['POT_ASCENSOR']) ? $form['POT_ASCENSOR'] : '');

        // Defectos (1..8)
        for ($i = 1; $i <= 8; $i++) {
            $k = 'DEF_'.$i;
            $data[$k] = $yn(isset($form[$k]) ? $form[$k] : '');
        }

        // Trabajo y comentarios
        $data['TRABAJO']     = $val(isset($form['TRABAJO']) ? $form['TRABAJO'] : '');
        $data['COMENTARIOS'] = $val(isset($form['COMENTARIOS']) ? $form['COMENTARIOS'] : '');

        // Instalador / firmante
        $inst = $this->getInst();
        $data['INST_EMPRESA'] = $val($inst['display']);     // Nombre comercial o nombre y apellidos si 401
        $data['INST_RASIC']   = $inst['rasic'];
        $data['INST_DNI']     = $val($inst['nif']);
        $isPerson             = (!empty($inst['type']) && $inst['type'] === 'person');

        // Si es persona (401), por defecto el firmante = nombre y apellidos + su DNI
        $defaultTechName = $isPerson ? trim(($inst['firstname'] ?? '').' '.($inst['lastname'] ?? '')) : '';
        $defaultTechDni  = $isPerson ? ($data['INST_DNI'] ?? '') : '';

        $data['CERT_NOMBRE'] = $val(isset($form['CERT_NOMBRE']) ? $form['CERT_NOMBRE'] : $defaultTechName);
        $data['CERT_DNI']    = $val(isset($form['CERT_DNI'])    ? $form['CERT_DNI']    : $defaultTechDni);
        $data['LUGAR']       = $val(isset($form['LUGAR']) ? $form['LUGAR'] : '');

        return $data;
    }

    public function renderODT($data, $template_odt, $out_odt) {
        global $langs;
        if (!is_readable($template_odt)) {
            return array('success'=>false, 'message'=>$langs->trans('ErrorFileNotFound').' (ODT template)');
        }
        if (!dol_copy($template_odt, $out_odt, 0, 0)) {
            return array('success'=>false, 'message'=>$langs->trans('ErrorFailedToWriteInDir'));
        }
        $zip = new ZipArchive();
        if ($zip->open($out_odt) !== true) {
            return array('success'=>false, 'message'=>$langs->trans('ErrorFailedToOpenFile'));
        }
        $content = $zip->getFromName('content.xml');
        if ($content === false) {
            $zip->close();
            return array('success'=>false, 'message'=>$langs->trans('ErrorFailedToReadFile').' (content.xml)');
        }

        // FECHA en dd/mm/aaaa si no viene
        if (!isset($data['FECHA']) || trim((string)$data['FECHA']) === '') {
            $data['FECHA'] = dol_print_date(dol_now(), '%d/%m/%Y', 'tzuserrel');
        }

        // Normaliza fechas si vienen
        if (isset($data['BRIE_FECHA']) && $data['BRIE_FECHA'] !== '') {
            $data['BRIE_FECHA'] = $this->formatDateDMY($data['BRIE_FECHA']);
        }
        if (isset($data['FECHA']) && $data['FECHA'] !== '') {
            $data['FECHA'] = $this->formatDateDMY($data['FECHA']);
        }

        $pairs = array();
        foreach ($data as $k => $v) {
            $key = strtoupper($k);
            $val = dol_string_nohtmltag((string) $v);
            $val = htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $pairs['__'.$key.'__']       = $val; // __CUPS__
            $pairs['__BRIE_'.$key.'__']  = $val; // __BRIE_CUPS__
        }
        $content = strtr($content, $pairs);
        $zip->addFromString('content.xml', $content);
        $zip->close();
        return array('success'=>true, 'message'=>'OK', 'file'=>$out_odt);
    }

    public function odtToPdf($odtFile, $pdfOut) {
        global $langs;
        if (empty($odtFile) || !is_readable($odtFile)) {
            return array('success'=>false, 'message'=>$langs->trans('ErrorFileNotFoundOrBadPermissions').' (ODT input)');
        }
        $outdir = dirname($pdfOut);
        if (!is_dir($outdir)) dol_mkdir($outdir);

        $det = $this->detectOdtConverter();
        if (empty($det['success'])) return $det;

        $type = $this->odtype;
        $bin  = $this->odpath;
        $last = $out = $err = '';

        if ($type === 'unoconv') {
            $cmd = escapeshellarg($bin).' -f pdf -o '.escapeshellarg($pdfOut).' '.escapeshellarg($odtFile);
            $rc = $this->runCmd($cmd, $last, $out, $err);
            clearstatcache();
            if ($rc !== 0 || !is_readable($pdfOut)) {
                return array('success'=>false, 'message'=>$langs->trans('ErrorFailToRunCommand').' unoconv', 'stderr'=>$err, 'stdout'=>$out);
            }
        } else {
            $cmd = escapeshellarg($bin).' --headless --convert-to pdf --outdir '.escapeshellarg($outdir).' '.escapeshellarg($odtFile);
            $rc = $this->runCmd($cmd, $last, $out, $err);
            if ($rc !== 0) {
                return array('success'=>false, 'message'=>$langs->trans('ErrorFailToRunCommand').' soffice', 'stderr'=>$err, 'stdout'=>$out);
            }
            $gen    = preg_replace('/\.odt$/i', '.pdf', basename($odtFile));
            $genAbs = $outdir.'/'.$gen;
            clearstatcache();
            if (!is_readable($genAbs) && !is_readable($pdfOut)) {
                return array('success'=>false, 'message'=>$langs->trans('ErrorFileNotFound').' (pdf result)');
            }
            if (is_readable($genAbs) && $genAbs !== $pdfOut) {
                dol_copy($genAbs, $pdfOut, 0, 0);
                @unlink($genAbs);
            }
        }
        return array('success'=>true, 'message'=>'OK', 'file'=>$pdfOut);
    }

    public function attachToThirdparty($socid, $absfile) {
        global $conf, $user;
        if (empty($socid) || !is_readable($absfile)) return '';

        if (!empty($conf->ecm) && !empty($conf->ecm->dir_output)) {
            $ecmroot = $conf->ecm->dir_output;
        } elseif (!empty($conf->ecm->multidir_output[$conf->entity])) {
            $ecmroot = $conf->ecm->multidir_output[$conf->entity];
        } else {
            $ecmroot = DOL_DATA_ROOT.'/ecm';
        }

        $rel     = 'dolielec/certs/'.((string) $socid);
        $destdir = rtrim($ecmroot, '/').'/'.$rel;
        dol_mkdir($destdir);
        if (!is_dir($destdir) || !is_writable($destdir)) {
            dol_syslog(__METHOD__.": ECM dest dir not writable: ".$destdir, LOG_ERR);
            return '';
        }

        $basename = basename($absfile);
        $final    = $destdir.'/'.$basename;
        if (!dol_copy($absfile, $final, 0, 0)) {
            dol_syslog(__METHOD__.": Failed copying file into ECM: ".$absfile." -> ".$final, LOG_ERR);
            return '';
        }

        $ef = new EcmFiles($this->db);
        $ef->filepath        = '/'.$rel;
        $ef->filename        = $basename;
        $ef->label           = 'BRIE '.$basename;
        $ef->fk_soc          = (int) $socid;
        $ef->gen_or_uploaded = 'uploaded';
        try {
            $resCreate = $ef->create($user);
            if ($resCreate <= 0) {
                $errmsg = (!empty($ef->error) ? $ef->error : 'unknown error');
                dol_syslog(__METHOD__.": ecm_files create() failed: ".$errmsg, LOG_WARNING);
            }
        } catch (\Throwable $e) {
            dol_syslog(__METHOD__.": ecm_files create() exception: ".$e->getMessage(), LOG_ERR);
        }
        return $rel.'/'.$basename;
    }
}
