<?php require __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
  $s = $pdo->query("SELECT `key`,`value` FROM settings");
  $out = [];
  foreach (($s ? $s->fetchAll() : []) as $r) $out[$r['key']] = $r['value'];
  respond(['settings' => $out]);
} catch (Throwable $e) { respond(['settings' => []]); }
