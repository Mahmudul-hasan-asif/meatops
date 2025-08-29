<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

/* === bring in your existing db.php and detect connection === */
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

/* === helpers that work with PDO or mysqli === */
function run_exec($sql, $params) {
  global $pdo,$mysqli;
  if ($pdo) { $st=$pdo->prepare($sql); $st->execute($params); return $pdo->lastInsertId(); }
  $st = $mysqli->prepare($sql);
  $types=''; $bind=$params;
  foreach ($bind as $k=>$v) { $types .= is_int($v)?'i' : (is_float($v)?'d' : 's'); $bind[$k]=$v; }
  $args = array_merge([$types], array_map(fn(&$x)=>$x, $bind));
  call_user_func_array([$st,'bind_param'],$args); $st->execute();
  $id = $mysqli->insert_id; $st->close(); return $id;
}

/* === endpoint === */
$in = json_decode(file_get_contents('php://input'), true) ?? [];
$facility = $in['facility'] ?? null;
$device   = $in['device']   ?? null;
$type     = $in['type']     ?? null;
$humidity = isset($in['humidity']) && $in['humidity'] !== '' ? (float)$in['humidity'] : null;
$temp     = isset($in['temp'])     && $in['temp']     !== '' ? (float)$in['temp']     : null;
$signal   = isset($in['signal'])   && $in['signal']   !== '' ? (int)$in['signal']     : null;
$status   = $in['status'] ?? null;
$lastSeen = !empty($in['last_seen']) ? date('Y-m-d H:i:s', strtotime($in['last_seen'])) : null;

try {
  $sql = "INSERT INTO device_status (facility, device, type, humidity, temp, `signal`, status, last_seen, created_at)
          VALUES (?,?,?,?,?,?,?, ?, NOW())";
  $id = run_exec($sql, [$facility,$device,$type,$humidity,$temp,$signal,$status,$lastSeen]);
  echo json_encode(['ok'=>true,'id'=>$id]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
