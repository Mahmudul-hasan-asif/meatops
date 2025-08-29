<?php
header('Content-Type: application/json');

function get_pdo(){ static $pdo=null; if($pdo) return $pdo;
  $host='127.0.0.1'; $db='meat_inventory'; $user='root'; $pass=''; $charset='utf8mb4';
  $cfg=__DIR__.'/config.php';
  if(file_exists($cfg)){ require_once $cfg;
    $host=defined('DB_HOST')?DB_HOST:$host; $db=defined('DB_NAME')?DB_NAME:$db;
    $user=defined('DB_USER')?DB_USER:$user; $pass=defined('DB_PASS')?DB_PASS:$pass;
  }
  return $pdo=new PDO("mysql:host=$host;dbname=$db;charset=$charset",$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false
  ]);
}
function payload(){
  $ct=$_SERVER['CONTENT_TYPE']??'';
  if(stripos($ct,'application/json')!==false){
    $p=json_decode(file_get_contents('php://input'),true);
    if(is_array($p)) return $p;
  }
  return $_POST;
}

try{
  $d = payload();
  $source_id = (int)($d['source_id']??0);
  $qty       = (float)($d['qty']??0);
  $target_facility_id = (int)($d['target_facility_id']??0);
  $target_location_bin= trim($d['target_location_bin']??'');

  if($source_id<=0 || $qty<=0 || $target_facility_id<=0){
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing fields']); exit;
  }

  $pdo = get_pdo(); $pdo->beginTransaction();

  // Load source
  $src = $pdo->prepare("SELECT * FROM batch_facility WHERE id=:id FOR UPDATE");
  $src->execute([':id'=>$source_id]);
  $source = $src->fetch();
  if(!$source){ throw new Exception('Source lot not found'); }
  if($qty > (float)$source['qty_on_hand_kg']){ throw new Exception('Qty exceeds on-hand'); }

  // Move-all case: update facility/bin in-place
  if(abs($qty - (float)$source['qty_on_hand_kg']) < 1e-9){
    $upd = $pdo->prepare("UPDATE batch_facility SET facility_id=:f, location_bin=:b WHERE id=:id");
    $upd->execute([':f'=>$target_facility_id, ':b'=>$target_location_bin, ':id'=>$source_id]);
  } else {
    // Split: decrement source
    $upd = $pdo->prepare("UPDATE batch_facility SET qty_on_hand_kg=qty_on_hand_kg-:q WHERE id=:id");
    $upd->execute([':q'=>$qty, ':id'=>$source_id]);

    // Try merge into an existing target row (same batch/product + target facility/bin + same lot_code)
    $find = $pdo->prepare("SELECT id FROM batch_facility
      WHERE batch_id=:b AND product_id=:p AND facility_id=:f AND COALESCE(location_bin,'') = COALESCE(:bin,'') AND lot_code=:lc
      LIMIT 1");
    $find->execute([
      ':b'=>$source['batch_id'],
      ':p'=>$source['product_id'],
      ':f'=>$target_facility_id,
      ':bin'=>$target_location_bin===''?null:$target_location_bin,
      ':lc'=>$source['lot_code'],
    ]);
    $target = $find->fetch();

    if($target){
      $inc = $pdo->prepare("UPDATE batch_facility SET qty_on_hand_kg=qty_on_hand_kg+:q WHERE id=:id");
      $inc->execute([':q'=>$qty, ':id'=>$target['id']]);
    } else {
      // Insert new target lot copy
      $ins = $pdo->prepare("INSERT INTO batch_facility
        (batch_id, product_id, facility_id, lot_code, location_bin, qty_on_hand_kg, qty_reserved_kg, expiry_date, status)
        VALUES (:b,:p,:f,:lc,:bin,:onh,0,:exp,:st)");
      $ins->execute([
        ':b'=>$source['batch_id'],
        ':p'=>$source['product_id'],
        ':f'=>$target_facility_id,
        ':lc'=>$source['lot_code'],
        ':bin'=>$target_location_bin,
        ':onh'=>$qty,
        ':exp'=>$source['expiry_date'],
        ':st'=>$source['status'],
      ]);
    }
  }

  $pdo->commit();
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){
  if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
