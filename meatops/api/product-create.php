<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if($_SERVER['REQUEST_METHOD']==='OPTIONS') exit;

$PROD_TABLE = 'product';

$__paths=[__DIR__.'/../db.php', dirname(__DIR__).'/db.php', __DIR__.'/db.php', dirname(__DIR__,2).'/db.php'];
$__ok=false; foreach($__paths as $__p){ if(is_file($__p)){ require_once $__p; $__ok=true; break; } }
if(!$__ok){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db.php not found']); exit; }

$pdo=(isset($pdo)&&$pdo instanceof PDO)?$pdo:(function_exists('getPDO')?getPDO():(function_exists('pdo_conn')?pdo_conn():null));
$mysqli=null; foreach(['mysqli','conn','con','db','link','connection'] as $h){ if(isset($$h)&&$$h instanceof mysqli){ $mysqli=$$h; break; } }

function run_exec($s,$p=[]){ global $pdo,$mysqli;
  if($pdo){ $st=$pdo->prepare($s); $st->execute($p); return $pdo->lastInsertId(); }
  $st=$mysqli->prepare($s); if(!$st) throw new Exception($mysqli->error);
  if($p){ $t=''; $v=[]; foreach($p as $x){ $t.=is_int($x)?'i':(is_float($x)?'d':'s'); $v[]=$x; }
    $b=[$t]; foreach($v as $i=>$y){ $b[]=&$v[$i]; } call_user_func_array([$st,'bind_param'],$b); }
  if(!$st->execute()){ $e=$st->error; $st->close(); throw new Exception($e); }
  $id=$mysqli->insert_id ?: $mysqli->affected_rows; $st->close(); return $id;
}
function run_select($s,$p=[]){ global $pdo,$mysqli;
  if($pdo){ $st=$pdo->prepare($s); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
  $st=$mysqli->prepare($s); if(!$st) throw new Exception($mysqli->error);
  if($p){ $t=''; $v=[]; foreach($p as $x){ $t.=is_int($x)?'i':(is_float($x)?'d':'s'); $v[]=$x; }
    $b=[$t]; foreach($v as $i=>$y){ $b[]=&$v[$i]; } call_user_func_array([$st,'bind_param'],$b); }
  $st->execute(); $r=$st->get_result(); $rows=$r?$r->fetch_all(MYSQLI_ASSOC):[]; $st->close(); return $rows;
}

$in = json_decode(file_get_contents('php://input'), true);
if(!is_array($in)) $in = $_POST;

$animal   = strtolower(trim($in['animal_type'] ?? 'cow'));
if(!in_array($animal, ['cow','chicken','mutton','duck'])) $animal = 'cow';
$name     = trim($in['product_name'] ?? '');
$price    = isset($in['price']) ? (float)$in['price'] : 0;
$qty      = isset($in['quantity']) ? (int)$in['quantity'] : 0;

$sale     = (string)($in['sale'] ?? '0') === '1' ? 1 : 0;
$disc     = $sale ? (isset($in['sale_discount_percent']) ? (float)$in['sale_discount_percent'] : null) : null;
$sale_end = $sale ? (!empty($in['sale_end_date']) ? date('Y-m-d', strtotime($in['sale_end_date'])) : null) : null;

try{
  if($name==='') throw new Exception('product_name required');

  $id = run_exec("INSERT INTO `$PROD_TABLE`
    (`animal_type`,`product_name`,`price`,`quantity`,`sale`,`sale_discount_percent`,`sale_end_date`)
    VALUES (?,?,?,?,?,?,?)",
    [$animal,$name,$price,$qty,$sale,$disc,$sale_end]
  );

  $row = run_select("SELECT `product_id`,`animal_type`,`product_name`,`price`,`quantity`,`sale`,`sale_discount_percent`,`sale_end_date`
                     FROM `$PROD_TABLE` WHERE `product_id`=?", [(int)$id]);
  echo json_encode(['ok'=>true,'data'=>$row ? $row[0] : ['product_id'=>$id,'animal_type'=>$animal,'product_name'=>$name,'price'=>$price,'quantity'=>$qty,'sale'=>$sale,'sale_discount_percent'=>$disc,'sale_end_date'=>$sale_end]]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
