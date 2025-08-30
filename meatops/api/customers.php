<?php
// api/customers.php
declare(strict_types=1);

/* Ensure JSON-only output (prevent PHP warnings in output) */
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function respond(array $arr, int $code = 200){ http_response_code($code); echo json_encode($arr); exit; }
function fail(string $msg, int $code = 500, ?string $codeKey=null){ $p=['ok'=>false,'error'=>$msg]; if($codeKey) $p['code']=$codeKey; respond($p,$code); }

/* --- locate db.php in common places --- */
$tryPaths = [
  __DIR__ . '/../db.php',
  __DIR__ . '/../../db.php',
  __DIR__ . '/db.php',
  dirname(__DIR__) . '/db.php',
  (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'],'/') . '/db.php' : null),
];
$foundDb = false;
foreach ($tryPaths as $p) { if ($p && file_exists($p)) { require_once $p; $foundDb = true; break; } }
if (!$foundDb) fail('db.php not found. Place db.php at project root.', 500);

$pdo = $pdo ?? null;
$conn = $conn ?? ($con ?? null);

function input_json(): array { $raw=file_get_contents('php://input'); if(!$raw) return []; $d=json_decode($raw,true); return is_array($d)?$d:[]; }
function clean(?string $s, ?int $max=null): ?string { if($s===null) return null; $s=trim($s); if($s==='') return null; if($max!==null && mb_strlen($s)>$max) $s=mb_substr($s,0,$max); return $s; }

/* information_schema based existence checks (works with placeholders) */
function table_exists_pdo(PDO $pdo, string $t): bool {
  $st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
  $st->execute([$t]); return (int)$st->fetchColumn()>0;
}
function table_exists_mysqli(mysqli $c, string $t): bool {
  $st=$c->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
  $st->bind_param('s',$t); $st->execute(); $res=$st->get_result(); $row=$res?$res->fetch_assoc():['c'=>0]; $st->close(); return (int)($row['c']??0)>0;
}

$table='customer';
$hasOrders=false;

if ($pdo instanceof PDO) {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  if (!table_exists_pdo($pdo,$table)) { if (table_exists_pdo($pdo,'customers')) $table='customers'; else fail("Neither 'customer' nor 'customers' table exists.",500); }
  $hasOrders = table_exists_pdo($pdo,'orders');
} elseif ($conn instanceof mysqli) {
  @$conn->set_charset('utf8mb4');
  if (!table_exists_mysqli($conn,$table)) { if (table_exists_mysqli($conn,'customers')) $table='customers'; else fail("Neither 'customer' nor 'customers' table exists.",500); }
  $hasOrders = table_exists_mysqli($conn,'orders');
} else {
  fail('db.php must expose $pdo (PDO) or $conn/$con (mysqli).',500);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
  if ($method==='GET') {
    if ($pdo instanceof PDO) {
      if ($hasOrders) {
        $sql = "SELECT c.customer_id, c.name, c.address, c.phone, c.email, COALESCE(o.cnt,0) AS order_count
                FROM `$table` c
                LEFT JOIN (SELECT customer_id, COUNT(*) cnt FROM `orders` GROUP BY customer_id) o
                ON o.customer_id = c.customer_id
                ORDER BY c.customer_id DESC";
      } else {
        $sql = "SELECT customer_id, name, address, phone, email, 0 AS order_count FROM `$table` ORDER BY customer_id DESC";
      }
      $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
      if ($hasOrders) {
        $sql = "SELECT c.customer_id, c.name, c.address, c.phone, c.email, COALESCE(o.cnt,0) AS order_count
                FROM `$table` c
                LEFT JOIN (SELECT customer_id, COUNT(*) cnt FROM `orders` GROUP BY customer_id) o
                ON o.customer_id = c.customer_id
                ORDER BY c.customer_id DESC";
      } else {
        $sql = "SELECT customer_id, name, address, phone, email, 0 AS order_count FROM `$table` ORDER BY customer_id DESC";
      }
      $rows=[]; if($res=$conn->query($sql)){ while($row=$res->fetch_assoc()) $rows[]=$row; }
    }
    respond(['ok'=>true,'data'=>$rows]);
  }

  if ($method==='POST') {
    $in = input_json();
    $name = clean($in['name'] ?? '',150);
    $address = clean($in['address'] ?? null,255);
    $phone = clean($in['phone'] ?? null,30);
    $email = clean($in['email'] ?? null,120);
    if(!$name) fail('Name is required',400);
    if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Invalid email',400);

    if ($pdo instanceof PDO) {
      $st=$pdo->prepare("INSERT INTO `$table` (name,address,phone,email) VALUES (?,?,?,?)");
      $st->execute([$name,$address,$phone,$email]);
      $id=(int)$pdo->lastInsertId();
      $row=$pdo->query("SELECT customer_id, name, address, phone, email, 0 AS order_count FROM `$table` WHERE customer_id={$id}")->fetch(PDO::FETCH_ASSOC);
      respond(['ok'=>true,'data'=>$row],201);
    } else {
      $st=$conn->prepare("INSERT INTO `$table` (name,address,phone,email) VALUES (?,?,?,?)");
      $st->bind_param('ssss',$name,$address,$phone,$email);
      if(!$st->execute()) fail('Insert failed: '.$conn->error,500);
      $id=(int)$conn->insert_id;
      $res=$conn->query("SELECT customer_id, name, address, phone, email, 0 AS order_count FROM `$table` WHERE customer_id={$id}");
      $row=$res?$res->fetch_assoc():null;
      respond(['ok'=>true,'data'=>$row],201);
    }
  }

  if ($method==='PUT') {
    $in=input_json();
    $id=(int)($in['customer_id']??0); if($id<=0) fail('customer_id is required',400);
    $name = clean($in['name'] ?? '',150);
    $address = clean($in['address'] ?? null,255);
    $phone = clean($in['phone'] ?? null,30);
    $email = clean($in['email'] ?? null,120);
    if(!$name) fail('Name is required',400);
    if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Invalid email',400);

    if($pdo instanceof PDO){
      $st=$pdo->prepare("UPDATE `$table` SET name=?, address=?, phone=?, email=? WHERE customer_id=?");
      $st->execute([$name,$address,$phone,$email,$id]);
      $row=$pdo->query("SELECT customer_id, name, address, phone, email, 0 AS order_count FROM `$table` WHERE customer_id={$id}")->fetch(PDO::FETCH_ASSOC);
      respond(['ok'=>true,'data'=>$row]);
    } else {
      $st=$conn->prepare("UPDATE `$table` SET name=?, address=?, phone=?, email=? WHERE customer_id=?");
      $st->bind_param('ssssi',$name,$address,$phone,$email,$id);
      if(!$st->execute()) fail('Update failed: '.$conn->error,500);
      $res=$conn->query("SELECT customer_id, name, address, phone, email, 0 AS order_count FROM `$table` WHERE customer_id={$id}");
      $row=$res?$res->fetch_assoc():null;
      respond(['ok'=>true,'data'=>$row]);
    }
  }

  if ($method==='DELETE') {
    $id = (int)($_GET['id'] ?? 0); if($id<=0) fail('id is required',400);

    // optional pre-check if orders table exists
    if ($hasOrders) {
      if ($pdo instanceof PDO) {
        $st=$pdo->prepare("SELECT COUNT(*) FROM `orders` WHERE customer_id=?"); $st->execute([$id]);
        if((int)$st->fetchColumn()>0) fail('Cannot delete: this customer has related orders.',409,'FK_CONSTRAINT');
      } else {
        $st=$conn->prepare("SELECT COUNT(*) AS c FROM `orders` WHERE customer_id=?"); $st->bind_param('i',$id); $st->execute();
        $res=$st->get_result(); $row=$res?$res->fetch_assoc():['c'=>0]; $st->close();
        if((int)($row['c']??0)>0) fail('Cannot delete: this customer has related orders.',409,'FK_CONSTRAINT');
      }
    }

    if ($pdo instanceof PDO) {
      try{
        $st=$pdo->prepare("DELETE FROM `$table` WHERE customer_id=?"); $st->execute([$id]);
      } catch(PDOException $e){
        $info = $e->errorInfo;
        if(($info[0]??'')==='23000' && (int)($info[1]??0)===1451) fail('Cannot delete: this customer has related orders.',409,'FK_CONSTRAINT');
        fail('Delete failed: '.$e->getMessage(),500);
      }
    } else {
      $st=$conn->prepare("DELETE FROM `$table` WHERE customer_id=?"); $st->bind_param('i',$id);
      if(!$st->execute()){
        if((int)$conn->errno===1451) fail('Cannot delete: this customer has related orders.',409,'FK_CONSTRAINT');
        fail('Delete failed: '.$conn->error,500);
      }
    }
    respond(['ok'=>true,'data'=>['deleted'=>$id]]);
  }

  fail('Unsupported method',405);

} catch (Throwable $e) {
  fail($e->getMessage(),500);
}
