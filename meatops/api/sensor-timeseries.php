<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

/* Safe include of db.php */
$__paths=[__DIR__.'/../db.php', dirname(__DIR__).'/db.php', __DIR__.'/db.php', dirname(__DIR__,2).'/db.php'];
$__ok=false; foreach($__paths as $__p){ if(is_file($__p)){ require_once $__p; $__ok=true; break; } }
if(!$__ok){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db.php not found','tried'=>$__paths]); exit; }

/* Detect handles */
$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo
     : (function_exists('getPDO') ? getPDO() : (function_exists('pdo_conn') ? pdo_conn() : null));
$mysqli=null; foreach(['mysqli','conn','con','db','link','connection'] as $h){ if(isset($$h) && $$h instanceof mysqli){ $mysqli=$$h; break; } }

/* Query helper */
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
  $facility = $_GET['facility'] ?? '';
  $device   = $_GET['device'] ?? '';
  $hours    = max(1, min(72, (int)($_GET['hours'] ?? 12)));
  $since    = date('Y-m-d H:i:s', time() - $hours*3600);

  // 1) Primary source: sensor_readings (TEMP / RH)
  $r1 = run_select(
    "SELECT `timestamp`, `metric`, `value`
       FROM `sensor_readings`
      WHERE `timestamp` >= ?
        AND (? = '' OR `facility` = ?)
        AND (? = '' OR `device`   = ?)
        AND `metric` IN ('TEMP','RH')
      ORDER BY `timestamp` ASC",
    [$since, $facility,$facility, $device,$device]
  );

  // 2) Fallback/augment: device_status recent snapshots → map to TEMP/RH
  $r2 = run_select(
    "SELECT `last_seen` AS `timestamp`, `humidity`, `temp`
       FROM `device_status`
      WHERE `last_seen` >= ?
        AND (? = '' OR `facility` = ?)
        AND (? = '' OR `device`   = ?)
      ORDER BY `last_seen` ASC",
    [$since, $facility,$facility, $device,$device]
  );

  // Merge into minute-buckets; prefer sensor_readings when both exist
  $bucket = []; // key: 'Y-m-d H:i' => ['t'=>?, 'h'=>?]
  foreach ($r2 as $row) {
    $k = date('Y-m-d H:i', strtotime($row['timestamp']));
    if (!isset($bucket[$k])) $bucket[$k] = ['t'=>null,'h'=>null];
    if ($row['temp']     !== null && $bucket[$k]['t']===null) $bucket[$k]['t'] = (float)$row['temp'];
    if ($row['humidity'] !== null && $bucket[$k]['h']===null) $bucket[$k]['h'] = (float)$row['humidity'];
  }
  foreach ($r1 as $row) {
    $k = date('Y-m-d H:i', strtotime($row['timestamp']));
    if (!isset($bucket[$k])) $bucket[$k] = ['t'=>null,'h'=>null];
    if ($row['metric']==='TEMP') $bucket[$k]['t'] = (float)$row['value'];
    if ($row['metric']==='RH')   $bucket[$k]['h'] = (float)$row['value'];
  }

  // Build arrays for Chart.js
  ksort($bucket);
  $labels=[]; $temp=[]; $rh=[];
  foreach ($bucket as $k=>$v) {
    $labels[] = substr($k, 11);    // 'H:i'
    $temp[]   = $v['t'];
    $rh[]     = $v['h'];
  }

  echo json_encode(['ok'=>true,'labels'=>$labels,'temp'=>$temp,'rh'=>$rh]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
