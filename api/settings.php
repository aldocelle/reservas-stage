<?php require __DIR__.'/config.php';
$admin = requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
  $s = $pdo->query("SELECT `key`,`value`,updated_at FROM settings ORDER BY `key`");
  $rows = $s ? $s->fetchAll() : [];
  $out = [];
  foreach ($rows as $r) $out[$r['key']] = $r['value'];
  respond(['settings' => $out]);
}
if ($method === 'PUT' || $method === 'PATCH' || $method === 'POST') {
  $x = body();
  $allowed = ['site_name', 'hero_title', 'hero_subtitle', 'address', 'whatsapp', 'instagram', 'booking_enabled', 'booking_notice'];
  $saved = [];
  foreach ($allowed as $k) {
    if (!array_key_exists($k, $x)) continue;
    $v = trim((string)$x[$k]);
    if (mb_strlen($v) > 500) respond(['error' => "Valor muy largo: {$k}"], 422);
    $pdo->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$k, $v]);
    $saved[] = $k;
  }
  auditLog($admin['id'], 'update', 'settings', implode(',', $saved), $x);
  respond(['ok' => true, 'saved' => $saved]);
}
respond(['error' => 'Método no permitido'], 405);
