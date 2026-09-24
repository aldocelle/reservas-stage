<?php
declare(strict_types=1);
require_once __DIR__ . '/db_env.php';
header('Content-Type: application/json; charset=utf-8');
function isHttps(): bool {
  if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
  return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}
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
$cfg = dbCredentials();
try {
  $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], dbOptions());
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => 'No se pudo conectar a la base de datos']); exit; }
function body(): array { $raw = file_get_contents('php://input'); $d = json_decode($raw ?: '', true); return is_array($d) ? $d : []; }
function respond($data, int $status = 200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function validDate(string $d): bool { if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false; [$y, $m, $day] = array_map('intval', explode('-', $d)); return checkdate($m, $day, $y); }
// ¿Navegador y API en dominios distintos? (p. ej. frontend en Vercel + API en Railway)
// SameSite se evalúa por "sitio", no por origen: los puertos no cuentan (localhost:5173 y
// localhost:8080 son el mismo sitio), el host sí.
function isCrossSiteRequest(): bool {
  $origin = getenv('ADMIN_ORIGIN') ?: '';
  if ($origin === '') return false;
  $host = strtolower((string)parse_url($origin, PHP_URL_HOST));
  $self = preg_replace('/:\d+$/', '', strtolower((string)($_SERVER['HTTP_HOST'] ?? '')));
  return $host !== '' && $host !== (string)$self;
}
function adminSession(): void {
  if (session_status() !== PHP_SESSION_NONE) return;
  // Cookies httpOnly + SameSite: el panel admin funciona igual en localhost y detrás del proxy HTTPS de Railway.
  // Cross-site (web en Vercel, API en Railway) necesita SameSite=None; Secure: con Lax el navegador no
  // manda la cookie en el fetch con credentials:'include' y el login del admin no persiste.
  // Ojo: los navegadores exigen HTTPS para SameSite=None (sobre http descartan la cookie).
  $cross = isCrossSiteRequest();
  session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'samesite' => $cross ? 'None' : 'Lax',
    'secure' => $cross ? true : isHttps(),
  ]);
  session_start();
}
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
// Primera fecha reservable (setting booking_start_date). Vacía o pasada = sin restricción.
function bookingStart(): string {
  $v = getSetting('booking_start_date');
  if ($v === '' || !validDate($v)) return '';
  return $v < date('Y-m-d') ? '' : $v;
}
// Última fecha reservable: 60 días hacia adelante.
function bookingMaxDate(): string { return date('Y-m-d', strtotime('+60 days')); }
