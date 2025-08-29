<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

/* db.php attach */
$root = dirname(__DIR__);
foreach ([$root.'/db.php', __DIR__.'/../db.php', __DIR__.'/db.php'] as $p) { if (is_file($p)) { require_once $p; break; } }
$pdo = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
$mysqli = null;
foreach (['mysqli','conn','con','db'] as $v) { if (isset($$v) && $$v instanceof mysqli) { $mysqli = $$v; break; } }
if (!$pdo && function_exists('pdo_conn')) { $pdo = pdo_conn(); }
if (!$pdo && function_exists('getPDO'))  { $pdo = getPDO(); }
if (!$pdo && function_exists('db'))      { $maybe = db(); if ($maybe instanceof PDO) $pdo=$maybe; elseif ($maybe instanceof mysqli) $mysqli=$maybe; }
if (!$pdo && !$mysqli && defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
  $pass = defined('DB_PASS') ? DB_PASS : '';
  try { $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); } catch(Throwable $e){}
}
if (!$pdo && !$mysqli) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'DB connect failed via db.php']); exit; }

function run_exec($sql,$params){
  global $pdo,$mysqli;
  if ($pdo){ $st=$pdo->prepare($sql); $st->execute($params); return $pdo->lastInsertId(); }
  $st=$mysqli->prepare($sql);
  $types=''; $bind=$params;
  foreach($bind as $k=>$v){ $types.=is_int($v)?'i':(is_float($v)?'d':'s'); $bind[$k]=$v; }
  $args=array_merge([$types], array_map(fn(&$x)=>$x,$bind));
  call_user_func_array([$st,'bind_param'],$args); $st->execute(); $id=$mysqli->insert_id; $st->close(); return $id;
}

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$ts   = !empty($in['timestamp']) ? date('Y-m-d H:i:s', strtotime($in['timestamp'])) : date('Y-m-d H:i:s');
$fac  = $in['facility']  ?? null;
$dev  = $in['device']    ?? null;
$metric= $in['metric']   ?? null;          // TEMP, RH, DOOR, POWER, CO2
$val  = isset($in['value']) ? (float)$in['value'] : null;
$units= $in['units']     ?? null;          // '°C','%','state','V','ppm'
$by   = $in['checked_by']?? null;
$status=$in['status']    ?? null;
$notes= $in['notes']     ?? null;

try {
  $sql = "INSERT INTO sensor_readings
          (timestamp, facility, device, metric, value, units, checked_by, status, notes, created_at)
          VALUES (?,?,?,?,?,?,?,?,?, NOW())";
  $id = run_exec($sql, [$ts,$fac,$dev,$metric,$val,$units,$by,$status,$notes]);
  echo json_encode(['ok'=>true,'id'=>$id]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
