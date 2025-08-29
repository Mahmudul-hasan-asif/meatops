<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

function run_select($sql,$params){
  global $pdo,$mysqli;
  if ($pdo){ $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC); }
  $st=$mysqli->prepare($sql);
  if ($params){
    $types=''; $bind=$params;
    foreach($bind as $k=>$v){ $types.=is_int($v)?'i':(is_float($v)?'d':'s'); $bind[$k]=$v; }
    $args=array_merge([$types], array_map(fn(&$x)=>$x,$bind));
    call_user_func_array([$st,'bind_param'],$args);
  }
  $st->execute(); $res=$st->get_result(); $rows=$res->fetch_all(MYSQLI_ASSOC); $st->close(); return $rows;
}

$hours = max(0, (int)($_GET['hours'] ?? 0));
$limit = max(1, min(1000, (int)($_GET['limit'] ?? 100)));
$params=[]; $where='';
if ($hours > 0) { $since = date('Y-m-d H:i:s', time() - $hours*3600); $where = "WHERE timestamp >= ?"; $params[] = $since; }
$params[] = $limit;

try {
  $rows = run_select(
    "SELECT id, timestamp, facility, device, metric, value, units, checked_by, status, notes
     FROM sensor_readings $where
     ORDER BY timestamp DESC, id DESC
     LIMIT ?", $params
  );
  echo json_encode(['ok'=>true,'data'=>$rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
