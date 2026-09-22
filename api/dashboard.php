<?php require __DIR__.'/config.php';
$admin = requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = trim((string)($_GET['path'] ?? ''));
if ($path === '' && $method === 'GET') {
  $today = date('Y-m-d');
  $totals = ['today' => 0, 'upcoming' => 0, 'total' => 0, 'attended' => 0, 'cancelled' => 0];
  try {
    $q = $pdo->query("SELECT status,COUNT(*) c,SUM(reservation_date=CURDATE()) today,SUM(reservation_date>=CURDATE() AND status IN ('confirmed','attended')) upcoming FROM reservations GROUP BY status");
    foreach ($q->fetchAll() as $r) { $totals['total'] += (int)$r['c']; $totals['today'] += (int)$r['today']; $totals['upcoming'] += (int)$r['upcoming']; if ($r['status'] === 'attended') $totals['attended'] += (int)$r['c']; if ($r['status'] === 'cancelled') $totals['cancelled'] += (int)$r['c']; }
  } catch (Throwable $e) {}
  $next = [];
  try {
    $s = $pdo->prepare("SELECT r.id,r.reservation_code,r.reservation_date,TIME_FORMAT(ts.start_time,'%H:%i') start_time,TIME_FORMAT(ts.end_time,'%H:%i') end_time,r.first_name,r.last_name,r.whatsapp,r.status FROM reservations r JOIN time_slots ts ON ts.id=r.slot_id WHERE r.reservation_date>=? ORDER BY r.reservation_date,ts.start_time LIMIT 8");
    $s->execute([$today]); $next = $s->fetchAll();
  } catch (Throwable $e) {}
  respond(['today' => $today, 'totals' => $totals, 'next' => $next, 'admin' => ['email' => $admin['email'], 'role' => $admin['role']]]);
}
respond(['error' => 'Ruta no encontrada'], 404);
