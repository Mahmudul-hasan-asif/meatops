<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$PROD_TABLE = 'product';

$__paths=[__DIR__.'/../db.php', dirname(__DIR__).'/db.php', __DIR__.'/db.php', dirname(__DIR__,2).'/db.php'];
$__ok=false; foreach($__paths as $__p){ if(is_file($__p)){ require_once $__p; $__ok=true; break; } }
if(!$__ok){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db.php not found']); exit; }

$pdo=(isset($pdo)&&$pdo instanceof PDO)?$pdo:(function_exists('getPDO')?getPDO():(function_exists('pdo_conn')?pdo_conn():null));
$mysqli=null; foreach(['mysqli','conn','con','db','link','connection'] as $h){ if(isset($$h)&&$$h instanceof mysqli){ $mysqli=$$h; break; } }

function run_select($s,$p=[]){ global $pdo,$mysqli;
  if($pdo){ $st=$pdo->prepare($s); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
  $st=$mysqli->prepare($s); if(!$st) throw new Exception($mysqli->error);
  if($p){ $t=''; $v=[]; foreach($p as $x){ $t.=is_int($x)?'i':(is_float($x)?'d':'s'); $v[]=$x; }
    $b=[$t]; foreach($v as $i=>$y){ $b[]=&$v[$i]; } call_user_func_array([$st,'bind_param'],$b); }
  $st->execute(); $r=$st->get_result(); $rows=$r?$r->fetch_all(MYSQLI_ASSOC):[]; $st->close(); return $rows;
}

try{
  $rows = run_select("
    SELECT
      `product_id`,`animal_type`,`product_name`,`price`,`quantity`,
      `sale`,`sale_discount_percent`,`sale_end_date`
    FROM `$PROD_TABLE`
    ORDER BY `product_id` DESC
    LIMIT 1000
  ");
  echo json_encode(['ok'=>true,'data'=>$rows]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
