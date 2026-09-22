<?php require __DIR__.'/config.php';
$admin = requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = trim((string)($_GET['path'] ?? ''));
$parts = $path === '' ? [] : explode('/', $path);
$allowed = ['confirmed', 'cancelled', 'attended', 'no_show'];
if ($method === 'GET' && $path === '') {
  $date = trim((string)($_GET['date'] ?? ''));
  $status = trim((string)($_GET['status'] ?? ''));
  $q = trim((string)($_GET['q'] ?? ''));
  $where = []; $args = [];
  if ($date !== '') { if (!validDate($date)) respond(['error' => 'Fecha inválida'], 422); $where[] = 'r.reservation_date=?'; $args[] = $date; }
  if ($status !== '') { if (!in_array($status, $allowed, true)) respond(['error' => 'Estado inválido'], 422); $where[] = 'r.status=?'; $args[] = $status; }
  if ($q !== '') { $where[] = '(r.reservation_code LIKE ? OR r.first_name LIKE ? OR r.last_name LIKE ? OR r.whatsapp LIKE ?)'; $like = "%{$q}%"; array_push($args, $like, $like, $like, $like); }
  $sql = "SELECT r.id,r.reservation_code,r.reservation_date,TIME_FORMAT(ts.start_time,'%H:%i') start_time,TIME_FORMAT(ts.end_time,'%H:%i') end_time,r.first_name,r.last_name,r.whatsapp,r.email,r.status,r.created_at FROM reservations r JOIN time_slots ts ON ts.id=r.slot_id";
  if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
  $sql .= " ORDER BY r.reservation_date DESC,ts.start_time LIMIT 200";
  $s = $pdo->prepare($sql); $s->execute($args);
  respond(['reservations' => $s->fetchAll()]);
}
if (($method === 'PATCH' || $method === 'PUT') && count($parts) === 2 && $parts[1] === 'status') {
  $x = body(); $id = (int)$parts[0];
  $status = trim((string)($x['status'] ?? ''));
  if (!in_array($status, $allowed, true)) respond(['error' => 'Estado inválido'], 422);
  $cur = $pdo->prepare("SELECT id,status FROM reservations WHERE id=?"); $cur->execute([$id]); $row = $cur->fetch();
  if (!$row) respond(['error' => 'Reserva no encontrada'], 404);
  $pdo->prepare("UPDATE reservations SET status=?,cancelled_at=CASE WHEN ?='cancelled' THEN NOW() ELSE cancelled_at END WHERE id=?")->execute([$status, $status, $id]);
  auditLog($admin['id'], 'status:' . $status, 'reservation', (string)$id, ['from' => $row['status'], 'to' => $status]);
  respond(['ok' => true]);
}
respond(['error' => 'Ruta no encontrada'], 404);
