<?php require __DIR__.'/config.php';
// Healthcheck (Railway) — sin autenticación, verifica app + base de datos.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') respond(['error' => 'Método no permitido'], 405);
try { $pdo->query('SELECT 1'); } catch (Throwable $e) { respond(['ok' => false, 'db' => false], 503); }
respond(['ok' => true, 'db' => true, 'service' => 'reservas-stage', 'time' => date('c')]);
