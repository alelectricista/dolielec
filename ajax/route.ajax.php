<?php
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) { header('Content-Type: application/json'); print json_encode(['success'=>false,'message'=>'main.inc.php not found']); exit; }
require_once DOL_DOCUMENT_ROOT.'/custom/dolielec/class/geoloc.class.php';
header('Content-Type: application/json; charset=UTF-8');
global $user, $db;
if (!$user->admin && (empty($user->rights->dolielec) || empty($user->rights->dolielec->read))) { print json_encode(['success'=>false,'message'=>'Access denied']); exit; }
$action = GETPOST('action','aZ09');
if ($action!=='route_best'){ print json_encode(['success'=>false,'message'=>'Unknown action']); exit; }
$q = trim(GETPOST('q','restricthtml'));
if ($q===''){ print json_encode(['success'=>false,'message'=>'Destino vacío']); exit; }
$geo = new Geoloc($db);
$best = $geo->routeBest($q);
if (!$best){ print json_encode(['success'=>false,'message'=>'Sin rutas (config ORS/API/dirección base?)']); exit; }
print json_encode(['success'=>true,'result'=>$best]); exit;
