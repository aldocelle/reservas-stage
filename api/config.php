<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Max-Age: 86400');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') exit;
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_NAME') ?: 'reservas_stage';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
try {
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => 'No se pudo conectar a la base de datos']); exit; }
function body(): array { $raw = file_get_contents('php://input'); $d = json_decode($raw ?: '', true); return is_array($d) ? $d : []; }
function respond($data, int $status = 200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function validDate(string $d): bool { if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false; [$y, $m, $day] = array_map('intval', explode('-', $d)); return checkdate($m, $day, $y); }
