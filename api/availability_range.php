<?php require __DIR__.'/config.php';
// Disponibilidad por rango (un mes completo) para el calendario: 1 request en vez de una por día.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') respond(['error' => 'Método no permitido'], 405);
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
if (!validDate($from) || !validDate($to)) respond(['error' => 'Rango inválido, usa YYYY-MM-DD'], 422);
if ($to < $from) respond(['error' => 'Rango inválido'], 422);
if ((int)round((strtotime($to) - strtotime($from)) / 86400) + 1 > 62) respond(['error' => 'Rango máximo de 62 días'], 422);

$today = date('Y-m-d');
$start = bookingStart();
$max = bookingMaxDate();

// Horarios activos agrupados por día de la semana
$rows = $pdo->query("SELECT st.weekday,ts.id slot_id,ts.capacity FROM schedule_templates st JOIN time_slots ts ON ts.template_id=st.id WHERE st.active=1 AND ts.active=1")->fetchAll();
$byWeekday = [];
foreach ($rows as $r) $byWeekday[(int)$r['weekday']][] = ['slot_id' => $r['slot_id'], 'capacity' => (int)$r['capacity']];

// Reservas vigentes del rango
$q = $pdo->prepare("SELECT reservation_date d,slot_id,COUNT(*) c FROM reservations WHERE reservation_date BETWEEN ? AND ? AND status IN ('confirmed','attended') GROUP BY reservation_date,slot_id");
$q->execute([$from, $to]);
$taken = [];
foreach ($q->fetchAll() as $r) $taken[$r['d']][$r['slot_id']] = (int)$r['c'];

$out = [];
$cursor = new DateTimeImmutable($from);
$end = new DateTimeImmutable($to);
while ($cursor <= $end) {
  $date = $cursor->format('Y-m-d');
  $weekday = (int)$cursor->format('N');
  $slots = $byWeekday[$weekday] ?? [];
  $total = 0; $used = 0;
  foreach ($slots as $s) {
    $total += $s['capacity'];
    $used += min($s['capacity'], $taken[$date][$s['slot_id']] ?? 0);
  }
  $out[] = [
    'date' => $date,
    'weekday' => $weekday,
    'slots' => count($slots),
    'total' => $total,
    'used' => $used,
    'available' => max(0, $total - $used),
    'bookable' => count($slots) > 0 && $date >= $today && $date <= $max && ($start === '' || $date >= $start),
  ];
  $cursor = $cursor->modify('+1 day');
}
respond(['from' => $from, 'to' => $to, 'today' => $today, 'starts_at' => $start, 'max_date' => $max, 'days' => $out]);
