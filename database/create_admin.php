<?php
// Uso: ADMIN_EMAIL=admin@vinastage.cl ADMIN_PASS=MiClave123 php database/create_admin.php
// O en hosting: crea el hash con password_hash() y ejecútalo como INSERT.
declare(strict_types=1);
$email = getenv('ADMIN_EMAIL') ?: ($argv[1] ?? '');
$password = getenv('ADMIN_PASS') ?: ($argv[2] ?? '');
if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
  fwrite(STDERR, "Uso: ADMIN_EMAIL=admin@... ADMIN_PASS=clave8+ php database/create_admin.php\n");
  exit(1);
}
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_NAME') ?: 'reservas_stage';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$id = sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO admins(id,email,password_hash,role,active) VALUES(?,?,?,'admin',1) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),active=1");
$stmt->execute([$id, $email, $hash]);
echo "Admin OK: {$email}\n";
