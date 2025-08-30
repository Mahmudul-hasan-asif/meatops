<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$out = ['ok' => false];
try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) throw new Exception('Missing id');

    $st = $pdo->prepare("DELETE FROM waste WHERE id = :id");
    $st->execute([':id' => $id]);
    $out['ok'] = true;
    $out['deleted'] = $st->rowCount();
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}
echo json_encode($out);
