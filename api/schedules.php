<?php require __DIR__.'/config.php';
$admin = requireAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = trim((string)($_GET['path'] ?? ''));
$parts = $path === '' ? [] : explode('/', $path);
function validTime(string $t): bool { if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $t)) return false; [$h, $m] = array_map('intval', explode(':', $t)); return $h >= 0 && $h <= 23 && $m >= 0 && $m <= 59; }
if ($method === 'GET' && $path === '') {
  $days = $pdo->query("SELECT id,weekday,label,capacity,active FROM schedule_templates ORDER BY weekday")->fetchAll();
  foreach ($days as &$d) {
    $d['weekday'] = (int)$d['weekday']; $d['capacity'] = (int)$d['capacity']; $d['active'] = (int)$d['active'];
    $s = $pdo->prepare("SELECT id,TIME_FORMAT(start_time,'%H:%i') start,TIME_FORMAT(end_time,'%H:%i') end,capacity,active FROM time_slots WHERE template_id=? ORDER BY start_time");
    $s->execute([$d['id']]); $slots = $s->fetchAll();
    foreach ($slots as &$sl) { $sl['capacity'] = (int)$sl['capacity']; $sl['active'] = (int)$sl['active']; }
    unset($sl);
    $d['slots'] = $slots;
  }
  unset($d);
  respond(['days' => $days]);
}
if (($method === 'PATCH' || $method === 'PUT') && count($parts) === 1) {
  $x = body(); $id = $parts[0];
  if (mb_strlen($id) > 36) respond(['error' => 'Día no encontrado'], 404);
  $cur = $pdo->prepare("SELECT id,label,capacity,active FROM schedule_templates WHERE id=?"); $cur->execute([$id]); $row = $cur->fetch();
  if (!$row) respond(['error' => 'Día no encontrado'], 404);
  $label = array_key_exists('label', $x) ? trim((string)$x['label']) : $row['label'];
  $capacity = array_key_exists('capacity', $x) ? (int)$x['capacity'] : (int)$row['capacity'];
  $active = array_key_exists('active', $x) ? (!empty($x['active']) ? 1 : 0) : (int)$row['active'];
  if ($label === '' || mb_strlen($label) > 30) respond(['error' => 'Nombre inválido (1-30)'], 422);
  if ($capacity < 1 || $capacity > 500) respond(['error' => 'Capacidad 1-500'], 422);
  try { $pdo->prepare("UPDATE schedule_templates SET label=?,capacity=?,active=? WHERE id=?")->execute([$label, $capacity, $active, $id]); }
  catch (PDOException $e) { respond(['error' => 'Ese nombre ya existe'], 409); }
  auditLog($admin['id'], 'update', 'schedule_template', $id, ['label' => $label, 'capacity' => $capacity, 'active' => $active]);
  respond(['ok' => true]);
}
if ($method === 'POST' && count($parts) === 2 && $parts[1] === 'slots') {
  $x = body(); $tid = $parts[0];
  $t = $pdo->prepare("SELECT id FROM schedule_templates WHERE id=?"); $t->execute([$tid]);
  if (!$t->fetch()) respond(['error' => 'Día no encontrado'], 404);
  $start = trim((string)($x['start'] ?? '')); $end = trim((string)($x['end'] ?? ''));
  $capacity = (int)($x['capacity'] ?? 0);
  if (!validTime($start) || !validTime($end)) respond(['error' => 'Hora inválida (HH:MM)'], 422);
  if (strlen($start) === 5) $start .= ':00';
  if (strlen($end) === 5) $end .= ':00';
  if ($start >= $end) respond(['error' => 'Inicio menor al fin'], 422);
  if ($capacity < 1 || $capacity > 500) respond(['error' => 'Capacidad 1-500'], 422);
  $id = uuid();
  try { $pdo->prepare("INSERT INTO time_slots(id,template_id,start_time,end_time,capacity,active) VALUES(?,?,?,?,?,1)")->execute([$id, $tid, $start, $end, $capacity]); }
  catch (PDOException $e) { respond(['error' => 'Horario duplicado'], 409); }
  auditLog($admin['id'], 'create', 'time_slot', $id, ['t' => $tid, 's' => $start, 'e' => $end, 'c' => $capacity]);
  respond(['ok' => true, 'id' => $id], 201);
}
if (($method === 'PATCH' || $method === 'PUT') && count($parts) === 2 && $parts[0] === 'slots') {
  $x = body(); $id = $parts[1];
  $cur = $pdo->prepare("SELECT id,start_time,end_time,capacity,active FROM time_slots WHERE id=?"); $cur->execute([$id]); $row = $cur->fetch();
  if (!$row) respond(['error' => 'Horario no encontrado'], 404);
  $start = array_key_exists('start', $x) ? trim((string)$x['start']) : substr((string)$row['start_time'], 0, 5);
  $end = array_key_exists('end', $x) ? trim((string)$x['end']) : substr((string)$row['end_time'], 0, 5);
  $capacity = array_key_exists('capacity', $x) ? (int)$x['capacity'] : (int)$row['capacity'];
  $active = array_key_exists('active', $x) ? (!empty($x['active']) ? 1 : 0) : (int)$row['active'];
  if (!validTime($start) || !validTime($end)) respond(['error' => 'Hora inválida'], 422);
  if (strlen($start) === 5) $start .= ':00';
  if (strlen($end) === 5) $end .= ':00';
  if ($start >= $end) respond(['error' => 'Inicio menor al fin'], 422);
  if ($capacity < 1 || $capacity > 500) respond(['error' => 'Capacidad 1-500'], 422);
  try { $pdo->prepare("UPDATE time_slots SET start_time=?,end_time=?,capacity=?,active=? WHERE id=?")->execute([$start, $end, $capacity, $active, $id]); }
  catch (PDOException $e) { respond(['error' => 'Horario duplicado'], 409); }
  auditLog($admin['id'], 'update', 'time_slot', $id, ['s' => $start, 'e' => $end, 'c' => $capacity, 'a' => $active]);
  respond(['ok' => true]);
}
if ($method === 'DELETE' && count($parts) === 2 && $parts[0] === 'slots') {
  $pdo->prepare("UPDATE time_slots SET active=0 WHERE id=?")->execute([$parts[1]]);
  auditLog($admin['id'], 'deactivate', 'time_slot', $parts[1]);
  respond(['ok' => true]);
}
respond(['error' => 'Ruta no encontrada'], 404);
