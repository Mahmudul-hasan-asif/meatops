<?php
function json_out($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  // If serving from a different path/domain and you need CORS, uncomment:
  // header('Access-Control-Allow-Origin: *');
  echo json_encode($data);
  exit;
}

function require_post(): array {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'Method not allowed'], 405);
  }
  // Accept JSON or form-encoded
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data) || !count($data)) {
    $data = $_POST;
  }
  return $data;
}

function sanitize_bool($v): int {
  return (in_array($v, [1, '1', true, 'true', 'yes', 'on'], true)) ? 1 : 0;
}
