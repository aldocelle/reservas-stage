<?php
declare(strict_types=1);
// Verifica el comportamiento de la cookie de sesión de api/config.php sin depender de la base de datos:
// evalúa las funciones isHttps/isCrossSiteRequest/adminSession, que es el código real del archivo.
// Se ejecuta un proceso por escenario porque en PHP los parámetros de sesión no se pueden
// cambiar después del primer output.
//
//   php scripts/test-session-cookie.php
//
// Escenarios: local, mismo dominio detrás del proxy HTTPS (Railway), cross-site (web en Vercel +
// API en Railway) y variantes donde el puerto no cambia el "site" para SameSite.
$escenarios = [
  ['local http, sin ADMIN_ORIGIN', ['ADMIN_ORIGIN' => null], ['HTTP_HOST' => 'localhost:8080'], 'Lax', false],
  ['mismo dominio https (Railway)', ['ADMIN_ORIGIN' => 'https://reservas-stage.up.railway.app'], ['HTTP_HOST' => 'reservas-stage.up.railway.app', 'HTTP_X_FORWARDED_PROTO' => 'https'], 'Lax', true],
  ['cross-site (web Vercel + API Railway)', ['ADMIN_ORIGIN' => 'https://reservas-stage.vercel.app'], ['HTTP_HOST' => 'app.up.railway.app', 'HTTP_X_FORWARDED_PROTO' => 'https'], 'None', true],
  ['same-site, otro puerto (5173 vs 8080)', ['ADMIN_ORIGIN' => 'http://localhost:5173'], ['HTTP_HOST' => 'localhost:8080'], 'Lax', false],
  ['mismo dominio con puerto explícito', ['ADMIN_ORIGIN' => 'https://x.cl:8443'], ['HTTP_HOST' => 'x.cl:8443', 'HTTPS' => 'on'], 'Lax', true],
  ['host distinto sobre http (SameSite=None exige https)', ['ADMIN_ORIGIN' => 'http://127.0.0.1:5173'], ['HTTP_HOST' => 'localhost:8080'], 'None', true],
];

$idx = $argv[1] ?? null;
if ($idx === null) { // driver: un hijo por escenario
  $fallas = 0;
  foreach (array_keys($escenarios) as $i) {
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . " $i 2>/dev/null", $out, $code);
    echo ($out ? implode("\n", $out) : "(sin salida, exit $code)"), "\n";
    if ($code !== 0) $fallas++;
  }
  echo $fallas === 0 ? "TODOS OK\n" : "HAY FALLAS: $fallas\n";
  exit($fallas === 0 ? 0 : 1);
}

$src = file_get_contents(__DIR__ . '/../api/config.php');
// Extrae una función completa (matching de llaves) sin ejecutar el resto del archivo
// (api/config.php abre la conexión PDO al cargarse y aquí no hace falta base de datos).
function extraerFuncion(string $src, string $nombre): string {
  $i = strpos($src, "function $nombre(");
  if ($i === false) { fwrite(STDERR, "no se encontró $nombre\n"); exit(2); }
  $j = strpos($src, '{', $i);
  $depth = 0;
  for ($k = $j; $k < strlen($src); $k++) {
    if ($src[$k] === '{') $depth++;
    elseif ($src[$k] === '}') { $depth--; if ($depth === 0) return substr($src, $i, $k - $i + 1); }
  }
  fwrite(STDERR, "llaves desbalanceadas en $nombre\n"); exit(2);
}
eval(extraerFuncion($src, 'isHttps') . "\n" . extraerFuncion($src, 'isCrossSiteRequest') . "\n" . extraerFuncion($src, 'adminSession'));
if (!function_exists('adminSession')) { fwrite(STDERR, "adminSession no disponible\n"); exit(2); }

$e = $escenarios[(int)$idx];
foreach ($e[1] as $k => $v) { if ($v === null) putenv($k); else putenv("$k=$v"); }
$_SERVER = ['REQUEST_METHOD' => 'GET'] + $e[2];
adminSession();
$p = session_get_cookie_params();
$ok = $p['samesite'] === $e[3] && (bool)$p['secure'] === $e[4];
printf("%-52s samesite=%-6s secure=%-6s %s\n", $e[0], var_export($p['samesite'], true), var_export((bool)$p['secure'], true), $ok ? 'OK' : 'FALLA (esperado ' . $e[3] . '/' . var_export($e[4], true) . ')');
exit($ok ? 0 : 1);
