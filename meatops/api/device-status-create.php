<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

// ---- safe include of db.php ----
$__paths=[__DIR__.'/../db.php',dirname(__DIR__).'/db.php',__DIR__.'/db.php',dirname(__DIR__,2).'/db.php'];
$__ok=false; foreach($__paths as $__p){ if(is_file($__p)){ require_once $__p; $__ok=true; break; } }
if(!$__ok){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db.php not found','tried'=>$__paths]); exit; }

// ---- detect handles ----
$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : (function_exists('getPDO') ? getPDO() : (function_exists('pdo_conn') ? pdo_conn() : null));
$mysqli=null; foreach(['mysqli','conn','con','db','link','connection'] as $h){ if(isset($$h) && $$h instanceof mysqli){ $mysqli=$$h; break; } }

function run_exec($sql,$params=[]){
  global $pdo,$mysqli;
  if($pdo){ $st=$pdo->prepare($sql); $st->execute($params); return $pdo->lastInsertId(); }
  if($mysqli){
    $st=$mysqli->prepare($sql); if(!$st) throw new Exception('MySQLi prepare failed: '.$mysqli->error);
    if($params){ $types=''; $vals=[]; foreach($params as $v){ $types.=is_int($v)?'i':(is_float($v)?'d':'s'); $vals[]=$v; }
      $bind=[$types]; foreach($vals as $i=>$v){ $bind[]=&$vals[$i]; } call_user_func_array([$st,'bind_param'],$bind); }
    if(!$st->execute()){ $err=$st->error; $st->close(); throw new Exception($err); }
    $id=$mysqli->insert_id ?: $mysqli->affected_rows; $st->close(); return $id;
  }
  throw new Exception('db.php included but no PDO/mysqli connection found');
}

// JSON or form
$raw = file_get_contents('php://input'); $in = json_decode($raw, true); if(!is_array($in)) $in = $_POST;

$facility = $in['facility'] ?? null;
$device   = $in['device'] ?? null;
$type     = $in['type'] ?? null;
$humidity = ($in['humidity'] ?? '') === '' ? null : (float)$in['humidity'];
$temp     = ($in['temp'] ?? '') === '' ? null : (float)$in['temp'];
$signal   = ($in['signal'] ?? '') === '' ? null : (int)$in['signal'];
$status   = $in['status'] ?? null;
$lastSeen = !empty($in['last_seen']) ? date('Y-m-d H:i:s', strtotime($in['last_seen'])) : null;

try{
  $id = run_exec(
    "INSERT INTO device_status (facility, device, type, humidity, temp, `signal`, status, last_seen, created_at)
     VALUES (?,?,?,?,?,?,?, ?, NOW())",
    [$facility,$device,$type,$humidity,$temp,$signal,$status,$lastSeen]
  );
  echo json_encode(['ok'=>true,'id'=>$id]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
