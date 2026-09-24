<?php require __DIR__.'/config.php';
adminSession();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$x = $method === 'POST' ? body() : [];
$action = trim((string)($x['action'] ?? $_GET['action'] ?? 'status'));
if ($action === 'login') {
  if ($method !== 'POST') respond(['error' => 'Método no permitido'], 405);
  $email = trim((string)($x['email'] ?? ''));
  $password = (string)($x['password'] ?? '');
  if ($email === '' || $password === '') respond(['error' => 'Email y contraseña requeridos'], 422);
  $stmt = $pdo->prepare("SELECT id,email,password_hash,role,active FROM admins WHERE email=? LIMIT 1");
  $stmt->execute([$email]); $admin = $stmt->fetch();
  if (!$admin || (int)$admin['active'] !== 1 || !password_verify($password, $admin['password_hash'])) respond(['error' => 'Credenciales inválidas'], 401);
  session_regenerate_id(true);
  $_SESSION['admin_id'] = $admin['id']; $_SESSION['admin_email'] = $admin['email']; $_SESSION['admin_role'] = $admin['role'];
  auditLog($admin['id'], 'login', 'admin', $admin['id']);
  respond(['admin' => ['id' => $admin['id'], 'email' => $admin['email'], 'role' => $admin['role']]]);
}
if ($action === 'logout') {
  $a = currentAdmin();
  if ($a) auditLog($a['id'], 'logout', 'admin', $a['id']);
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    // Forma con array para borrar la cookie con los mismos atributos (incluido SameSite: None en cross-site).
    setcookie(session_name(), '', [
      'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
      'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?? 'Lax',
    ]);
  }
  session_destroy();
  respond(['ok' => true]);
}
$a = currentAdmin();
respond(['authenticated' => $a !== null, 'admin' => $a]);
