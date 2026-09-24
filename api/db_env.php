<?php
declare(strict_types=1);
// Resolución de credenciales MySQL en un solo lugar.
// Acepta: DB_* (hosting propio / .env), MYSQLHOST/MYSQLUSER/MYSQLPASSWORD/MYSQLDATABASE (Railway MySQL)
// y URLs tipo MYSQL_URL / DATABASE_URL (mysql://user:pass@host:port/db).
function envStr(string $key, string $default = ''): string {
  $v = getenv($key);
  return ($v === false || $v === '') ? $default : (string)$v;
}
function envFirst(array $keys, string $default = ''): string {
  foreach ($keys as $k) { $v = envStr($k); if ($v !== '') return $v; }
  return $default;
}
function parseDbUrl(string $url): array {
  $p = parse_url($url);
  if (!is_array($p)) return [];
  $out = [];
  if (!empty($p['host'])) $out['host'] = (string)$p['host'];
  if (!empty($p['port'])) $out['port'] = (string)$p['port'];
  if (isset($p['user'])) $out['user'] = urldecode((string)$p['user']);
  if (isset($p['pass'])) $out['pass'] = urldecode((string)$p['pass']);
  if (!empty($p['path'])) $out['name'] = ltrim((string)$p['path'], '/');
  return $out;
}
function dbCredentials(): array {
  $url = envFirst(['MYSQL_URL', 'DATABASE_URL', 'DB_URL', 'MYSQL_PUBLIC_URL']);
  $u = $url === '' ? [] : parseDbUrl($url);
  $host = envFirst(['DB_HOST', 'MYSQLHOST', 'MYSQL_HOST'], $u['host'] ?? 'localhost');
  $port = envFirst(['DB_PORT', 'MYSQLPORT', 'MYSQL_PORT'], $u['port'] ?? '3306');
  $name = envFirst(['DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE'], $u['name'] ?? 'reservas_stage');
  $user = envFirst(['DB_USER', 'MYSQLUSER', 'MYSQL_USER'], $u['user'] ?? 'root');
  $pass = envFirst(['DB_PASS', 'MYSQLPASSWORD', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD'], $u['pass'] ?? '');
  return [
    'host' => $host, 'port' => $port, 'name' => $name, 'user' => $user, 'pass' => $pass,
    'dsn' => "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
    'dsn_no_db' => "mysql:host={$host};port={$port};charset=utf8mb4",
  ];
}
function dbOptions(): array {
  return [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];
}
