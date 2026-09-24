<?php
declare(strict_types=1);
// Router del servidor embebido de PHP:
//  - /api/*.php  -> ejecuta el endpoint (whitelist, sin traversal)
//  - /health     -> alias de /api/health.php
//  - archivos    -> estáticos de dist/
//  - resto       -> index.html (SPA con hash routing)
$uri = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$root = __DIR__ . '/dist';

if ($uri === '/health' || $uri === '/health.php') {
  require __DIR__ . '/api/health.php';
  return true;
}
if (preg_match('#^/api/([a-z0-9_]+)\.php$#', $uri, $m)) {
  $name = $m[1];
  $file = __DIR__ . '/api/' . $name . '.php';
  if ($name !== 'config' && $name !== 'db_env' && is_file($file)) {
    require $file;
    return true;
  }
  http_response_code(404);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'Ruta no encontrada']);
  return true;
}
$path = realpath($root . $uri);
if ($path !== false && str_starts_with($path, (string)realpath($root)) && is_file($path)) {
  return false; // el servidor embebido entrega el estático tal cual
}
$index = $root . '/index.html';
http_response_code(200);
header('Content-Type: text/html; charset=utf-8');
readfile($index);
return true;
