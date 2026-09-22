<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$origin = getenv('ADMIN_ORIGIN') ?: '';
if ($origin !== '') {
  header("Access-Control-Allow-Origin: {$origin}");
  header('Access-Control-Allow-Credentials: true');
  header('Vary: Origin');
} else {
  header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
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
function adminSession(): void { if (session_status() === PHP_SESSION_NONE) session_start(); }
function currentAdmin(): ?array { adminSession(); return isset($_SESSION['admin_id']) ? ['id' => $_SESSION['admin_id'], 'email' => $_SESSION['admin_email'] ?? '', 'role' => $_SESSION['admin_role'] ?? 'admin'] : null; }
function requireAdmin(): array { $a = currentAdmin(); if (!$a) respond(['error' => 'No autenticado'], 401); return $a; }
function auditLog(?string $adminId, string $action, string $entity, string $entityId = '', $metadata = null): void {
  global $pdo;
  try { $pdo->prepare("INSERT INTO audit_logs(admin_id,action,entity,entity_id,metadata) VALUES(?,?,?,?,?)")->execute([$adminId, $action, $entity, $entityId, $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE)]); } catch (Throwable $e) {}
}
function getSetting(string $key, string $default = ''): string {
  global $pdo;
  try { $s = $pdo->prepare("SELECT `value` FROM settings WHERE `key`=?"); $s->execute([$key]); $v = $s->fetchColumn(); return $v === false ? $default : (string)$v; } catch (Throwable $e) { return $default; }
}
function uuid(): string { $d = random_bytes(16); $d[6] = chr(ord($d[6]) & 0x0f | 0x40); $d[8] = chr(ord($d[8]) & 0x3f | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4)); }
