<?php
// Create / Update / Delete delivery agent
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); exit;
    }

    require_once __DIR__ . '/db.php';

    // Find a PDO ($pdo) defined by db.php (supporting common patterns)
    if (!isset($pdo)) {
        if (function_exists('get_pdo'))       { $pdo = get_pdo(); }
        elseif (function_exists('db'))        { $pdo = db(); }
        elseif (function_exists('pdo'))       { $pdo = pdo(); }
        else throw new Exception('PDO instance $pdo not found in db.php');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];

    // Helper: read JSON body (or fallback to form)
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (!is_array($json)) $json = $_POST;

    if ($method === 'POST') {
        // Create
        $type        = trim($json['type']        ?? '');
        $driver_name = trim($json['driver_name'] ?? '');
        $driver_phone= trim($json['driver_phone']?? '');
        $car_number  = trim($json['car_number']  ?? '');

        if ($type === '' || $driver_name === '') {
            throw new Exception('type and driver_name are required');
        }

        $sql = "INSERT INTO delivery (type, driver_name, driver_phone, car_number)
                VALUES (:type, :driver_name, :driver_phone, :car_number)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':type' => $type,
            ':driver_name' => $driver_name,
            ':driver_phone' => $driver_phone !== '' ? $driver_phone : null,
            ':car_number'   => $car_number !== ''   ? $car_number   : null,
        ]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]); exit;
    }

    if ($method === 'PUT') {
        // Update
        $id          = isset($json['delivery_id']) ? (int)$json['delivery_id'] : 0;
        $type        = trim($json['type']        ?? '');
        $driver_name = trim($json['driver_name'] ?? '');
        $driver_phone= trim($json['driver_phone']?? '');
        $car_number  = trim($json['car_number']  ?? '');

        if ($id <= 0)                    throw new Exception('delivery_id required');
        if ($type === '' || $driver_name === '') throw new Exception('type and driver_name are required');

        $sql = "UPDATE delivery
                   SET type=:type, driver_name=:driver_name,
                       driver_phone=:driver_phone, car_number=:car_number
                 WHERE delivery_id=:id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':type' => $type,
            ':driver_name' => $driver_name,
            ':driver_phone' => $driver_phone !== '' ? $driver_phone : null,
            ':car_number'   => $car_number !== ''   ? $car_number   : null,
            ':id' => $id,
        ]);
        echo json_encode(['ok' => true, 'updated' => $stmt->rowCount()]); exit;
    }

    if ($method === 'DELETE') {
        // Delete
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) throw new Exception('id required');

        try {
            $stmt = $pdo->prepare("DELETE FROM delivery WHERE delivery_id=:id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['ok' => true, 'deleted' => $stmt->rowCount()]); exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                echo json_encode(['ok' => false, 'code' => 'FK_CONSTRAINT', 'error' => 'foreign key constraint']); exit;
            }
            throw $e;
        }
    }

    // If someone calls GET on this endpoint (not recommended)
    echo json_encode(['ok' => false, 'error' => 'Use POST/PUT/DELETE or get_delivery.php for listing']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
