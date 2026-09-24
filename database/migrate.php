<?php
declare(strict_types=1);
// Aplica database/schema.sql + database/settings.sql (idempotentes) esperando a que MySQL esté listo.
// Se ejecuta en cada arranque del contenedor: seguro de repetir.
require __DIR__ . '/../api/db_env.php';

$cfg = dbCredentials();
$tries = max(1, (int)(getenv('DB_WAIT_TRIES') ?: 60));       // 60 * 2s = 2 min
$sleep = max(1, (int)(getenv('DB_WAIT_SLEEP') ?: 2));
$pdo = null;
$lastError = '';

for ($i = 1; $i <= $tries; $i++) {
  try {
    $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], dbOptions());
    break;
  } catch (Throwable $e) {
    $lastError = $e->getMessage();
    // Si la base no existe todavía, intentamos crearla (permite levantar en local/hosting propio).
    try {
      $root = new PDO($cfg['dsn_no_db'], $cfg['user'], $cfg['pass'], dbOptions());
      $root->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $cfg['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
      $root = null;
      $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], dbOptions());
      break;
    } catch (Throwable $e2) {
      $lastError = $e2->getMessage();
    }
    if ($i % 5 === 0) echo "[migrate] intento {$i}/{$tries}: {$lastError}\n";
    sleep($sleep);
  }
}
if ($pdo === null) {
  fwrite(STDERR, "[migrate] no se pudo conectar a MySQL ({$cfg['host']}:{$cfg['port']}/{$cfg['name']}): {$lastError}\n");
  exit(1);
}
echo "[migrate] conectado a {$cfg['host']}:{$cfg['port']}/{$cfg['name']}\n";

// Divide un archivo SQL en sentencias respetando comillas y comentarios "--".
function sqlStatements(string $sql): array {
  $stmts = [];
  $buf = '';
  $len = strlen($sql);
  $quote = '';
  for ($i = 0; $i < $len; $i++) {
    $ch = $sql[$i];
    $next = $i + 1 < $len ? $sql[$i + 1] : '';
    if ($quote === '') {
      if (($ch === '-' && $next === '-') || $ch === '#') {          // comentario de línea
        while ($i < $len && $sql[$i] !== "\n") $i++;
        $buf .= "\n";
        continue;
      }
      if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; $buf .= $ch; continue; }
      if ($ch === ';') { $stmts[] = $buf; $buf = ''; continue; }
      $buf .= $ch;
      continue;
    }
    $buf .= $ch;
    if ($ch === '\\' && $quote !== '`') { if ($i + 1 < $len) { $buf .= $sql[++$i]; } continue; }
    if ($ch === $quote) $quote = '';
  }
  if (trim($buf) !== '') $stmts[] = $buf;
  $out = [];
  foreach ($stmts as $s) {
    $s = trim($s);
    if ($s === '') continue;
    $upper = strtoupper($s);
    if (str_starts_with($upper, 'USE ') || str_starts_with($upper, 'CREATE DATABASE')) continue; // ya conectados a DB_NAME
    $out[] = $s;
  }
  return $out;
}

foreach (['schema.sql', 'settings.sql'] as $file) {
  $path = __DIR__ . '/' . $file;
  if (!is_file($path)) { fwrite(STDERR, "[migrate] falta {$file}\n"); exit(1); }
  $statements = sqlStatements((string)file_get_contents($path));
  $done = 0;
  foreach ($statements as $stmt) {
    try { $pdo->exec($stmt); $done++; }
    catch (Throwable $e) { fwrite(STDERR, "[migrate] error en {$file}: " . $e->getMessage() . "\n"); exit(1); }
  }
  echo "[migrate] {$file}: {$done} sentencias aplicadas\n";
}

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach (['admins', 'schedule_templates', 'time_slots', 'reservations', 'settings', 'audit_logs'] as $t) {
  if (!in_array($t, $tables, true)) { fwrite(STDERR, "[migrate] falta la tabla {$t}\n"); exit(1); }
}
$slots = (int)$pdo->query("SELECT COUNT(*) FROM time_slots WHERE active=1")->fetchColumn();
$admins = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
echo "[migrate] ok · horarios activos={$slots} · admins={$admins}\n";
