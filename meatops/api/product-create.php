<?php
header('Content-Type: application/json');

function get_pdo() {
  static $pdo = null;
  if ($pdo) return $pdo;

  $host = '127.0.0.1';
  $db   = 'meat_inventory';
  $user = 'root';
  $pass = '';
  $charset = 'utf8mb4';

  $cfg = __DIR__ . '/config.php';
  if (file_exists($cfg)) {
    require_once $cfg;
    if (defined('DB_HOST')) { $host = DB_HOST; }
    if (defined('DB_NAME')) { $db   = DB_NAME; }
    if (defined('DB_USER')) { $user = DB_USER; }
    if (defined('DB_PASS')) { $pass = DB_PASS; }
  }

  $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
  $opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];
  return $pdo = new PDO($dsn, $user, $pass, $opt);
}

function read_payload() {
  $ct = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (is_array($j)) return $j;
  }
  // Fallback to POST form fields
  return $_POST;
}

try {
  $data = read_payload();
  $name       = trim($data['product_name'] ?? '');
  $price      = isset($data['price']) ? (float)$data['price'] : null;
  $quantity   = isset($data['quantity']) ? (int)$data['quantity'] : null;
  $sale       = isset($data['sale']) ? (int)$data['sale'] : 0;
  $shelf_life = isset($data['shelf_life']) ? (int)$data['shelf_life'] : 0;
  $start_date = trim($data['start_date'] ?? '');

  if ($name === '' || $price === null || $quantity === null || $start_date === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
  }

  $pdo = get_pdo();
  $stmt = $pdo->prepare("
    INSERT INTO product (product_name, price, quantity, sale, shelf_life, start_date)
    VALUES (:name, :price, :quantity, :sale, :shelf_life, :start_date)
  ");
  $stmt->execute([
    ':name'        => $name,
    ':price'       => $price,
    ':quantity'    => $quantity,
    ':sale'        => $sale ? 1 : 0,
    ':shelf_life'  => $shelf_life,
    ':start_date'  => $start_date,
  ]);

  $id = (int)$pdo->lastInsertId();

  $row = [
    'product_id'   => $id,
    'product_name' => $name,
    'price'        => (float)$price,
    'quantity'     => (int)$quantity,
    'sale'         => $sale ? 1 : 0,
    'shelf_life'   => (int)$shelf_life,
    'start_date'   => $start_date,
  ];

  echo json_encode(['ok' => true, 'data' => $row]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
