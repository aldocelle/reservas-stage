<?php require __DIR__.'/config.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') respond(['error' => 'Método no permitido'], 405);
$date = $_GET['date'] ?? date('Y-m-d');
if (!validDate($date)) respond(['error' => 'Fecha inválida, usa YYYY-MM-DD'], 422);
$stmt = $pdo->prepare("SELECT st.id template_id,st.weekday,st.label,ts.id slot_id,TIME_FORMAT(ts.start_time,'%H:%i') start_time,TIME_FORMAT(ts.end_time,'%H:%i') end_time,ts.capacity,COALESCE(SUM(CASE WHEN r.status IN ('confirmed','attended') THEN 1 ELSE 0 END),0) reserved FROM schedule_templates st JOIN time_slots ts ON ts.template_id=st.id LEFT JOIN reservations r ON r.slot_id=ts.id AND r.reservation_date=? WHERE st.active=1 AND ts.active=1 AND st.weekday=WEEKDAY(?)+1 GROUP BY ts.id ORDER BY ts.start_time");
$stmt->execute([$date, $date]); $rows = $stmt->fetchAll();
foreach ($rows as &$r) { $r['weekday'] = (int)$r['weekday']; $r['reserved'] = (int)$r['reserved']; $r['capacity'] = (int)$r['capacity']; $r['available'] = max(0, $r['capacity'] - $r['reserved']); }
unset($r);
respond(['date' => $date, 'slots' => $rows]);
