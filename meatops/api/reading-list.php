<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

/* Safe include of db.php (same style as animals endpoints) */
$__paths=[__DIR__.'/../db.php', dirname(__DIR__).'/db.php', __DIR__.'/db.php', dirname(__DIR__,2).'/db.php'];
$__ok=false; foreach($__paths as $__p){ if(is_file($__p)){ require_once $__p; $__ok=true; break; } }
if(!$__ok){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db.php not found','tried'=>$__paths]); exit; }

/* Detect PDO/mysqli created by db.php */
$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo
     : (function_exists('getPDO') ? getPDO() : (function_exists('pdo_conn') ? pdo_conn() : null));
$mysqli = null; foreach(['mysqli','conn','con','db','link','connection'] as $h){ if(isset($$h) && $$h instanceof mysqli){ $mysqli=$$h; break; } }

/* Query helpers */
function run_select($sql,$params=[]){
  global $pdo,$mysqli;
  if($pdo){ $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC); }
  if($mysqli){
    $st=$mysqli->prepare($sql); if(!$st) throw new Exception('MySQLi prepare failed: '.$mysqli->error);
    if($params){ $types=''; $vals=[]; foreach($params as $v){ $types.=is_int($v)?'i':(is_float($v)?'d':'s'); $vals[]=$v; }
      $bind=[$types]; foreach($vals as $i=>$v){ $bind[]=&$vals[$i]; } call_user_func_array([$st,'bind_param'],$bind); }
    $st->execute(); $res=method_exists($st,'get_result')?$st->get_result():null; $rows=$res?$res->fetch_all(MYSQLI_ASSOC):[]; $st->close(); return $rows;
  }
  throw new Exception('db.php included but no PDO/mysqli connection found');
}

try{
  $hours = max(0, (int)($_GET['hours'] ?? 24));
  $limit = max(1, min(1000, (int)($_GET['limit'] ?? 200)));
  $where=''; $params=[];
  if($hours>0){ $where="WHERE `timestamp` >= ?"; $params[] = date('Y-m-d H:i:s', time()-$hours*3600); }
  $params[]=$limit;

  $rows = run_select(
    "SELECT `id`,`timestamp`,`facility`,`device`,`metric`,`value`,`units`,`checked_by`,`status`,`notes`
       FROM `sensor_readings`
       $where
     ORDER BY `timestamp` DESC, `id` DESC
     LIMIT ?", $params
  );

  echo json_encode(['ok'=>true,'data'=>$rows]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
