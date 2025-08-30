<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

/* Safe include of db.php */
$__paths=[__DIR__.'/../db.php', dirname(__DIR__).'/db.php', __DIR__.'/db.php', dirname(__DIR__,2).'/db.php'];
$__ok=false; foreach($__paths as $__p){ if(is_file($__p)){ require_once $__p; $__ok=true; break; } }
if(!$__ok){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db.php not found','tried'=>$__paths]); exit; }

/* Detect handles */
$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo
     : (function_exists('getPDO') ? getPDO() : (function_exists('pdo_conn') ? pdo_conn() : null));
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

/* Accept JSON or form */
$raw=file_get_contents('php://input'); $in=json_decode($raw,true); if(!is_array($in)) $in=$_POST;

$ts     = !empty($in['timestamp']) ? date('Y-m-d H:i:s', strtotime($in['timestamp'])) : date('Y-m-d H:i:s');
$fac    = $in['facility'] ?? null;
$dev    = $in['device'] ?? null;
$metric = $in['metric'] ?? null;     // TEMP, RH, POWER, DOOR, CO2
$val    = isset($in['value']) ? (float)$in['value'] : null;
$units  = $in['units'] ?? null;      // °C, %, V, state, ppm
$by     = $in['checked_by'] ?? null;
$status = $in['status'] ?? null;
$notes  = $in['notes'] ?? null;

try{
  $id = run_exec(
    "INSERT INTO `sensor_readings`
       (`timestamp`,`facility`,`device`,`metric`,`value`,`units`,`checked_by`,`status`,`notes`,`created_at`)
     VALUES (?,?,?,?,?,?,?,?,?, NOW())",
    [$ts,$fac,$dev,$metric,$val,$units,$by,$status,$notes]
  );
  echo json_encode(['ok'=>true,'id'=>$id]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
