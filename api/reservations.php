<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/rut.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') respond(['error' => 'Método no permitido'], 405);
$x = body();
$date = trim((string)($x['date'] ?? ''));
$slotId = trim((string)($x['slotId'] ?? ''));
$firstName = trim((string)($x['firstName'] ?? ''));
$lastName = trim((string)($x['lastName'] ?? ''));
$whatsappRaw = trim((string)($x['whatsapp'] ?? ''));
$rutRaw = $x['rut'] ?? '';
$rut = normalizeRut($rutRaw);
$emailRaw = trim((string)($x['email'] ?? ''));
$consent = !empty($x['whatsappConsent']) ? 1 : 0;
if ($date === '' || $slotId === '' || $firstName === '' || $lastName === '' || $whatsappRaw === '') respond(['error' => 'Faltan datos obligatorios'], 422);
if (!validateRut($rut)) respond(['error' => 'El RUT ingresado no es válido. Revisa los datos e inténtalo nuevamente.'], 422);
if (!validDate($date)) respond(['error' => 'Fecha inválida, usa YYYY-MM-DD'], 422);
$today = date('Y-m-d');
if ($date < $today) respond(['error' => 'La fecha ya pasó'], 422);
if ($date > bookingMaxDate()) respond(['error' => 'Solo se puede reservar hasta 60 días adelante'], 422);
$start = bookingStart();
if ($start !== '' && $date < $start) respond(['error' => 'Las reservas comienzan el ' . date('d-m-Y', strtotime($start))], 422);
if (mb_strlen($firstName) < 2 || mb_strlen($firstName) > 80 || mb_strlen($lastName) < 2 || mb_strlen($lastName) > 80) respond(['error' => 'Nombre y apellido deben tener entre 2 y 80 caracteres'], 422);
$whatsapp = preg_replace('/[\s\-\.\(\)]/', '', $whatsappRaw);
if (!preg_match('/^\+?\d{8,15}$/', $whatsapp)) respond(['error' => 'WhatsApp inválido (8 a 15 dígitos)'], 422);
$email = $emailRaw === '' ? null : $emailRaw;
if ($email !== null && (mb_strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL))) respond(['error' => 'Email inválido'], 422);
if (mb_strlen($slotId) > 36) respond(['error' => 'Horario no disponible'], 422);
$pdo->beginTransaction();
try {
  $q = $pdo->prepare("SELECT ts.id,ts.capacity,st.weekday FROM time_slots ts JOIN schedule_templates st ON st.id=ts.template_id WHERE ts.id=? AND ts.active=1 AND st.active=1 FOR UPDATE");
  $q->execute([$slotId]); $slot = $q->fetch();
  if (!$slot) throw new Exception('Horario no disponible');
  $phpWeekday = (int)date('N', strtotime($date));
  if ((int)$slot['weekday'] !== $phpWeekday) throw new Exception('El horario no corresponde a ese día');
  $dup = $pdo->prepare("SELECT id FROM reservations WHERE rut_normalizado=? AND reservation_date=? AND status <> 'cancelled' LIMIT 1");
  $dup->execute([$rut, $date]);
  if ($dup->fetch()) throw new Exception('Ya existe una reserva asociada a este RUT para esta fecha. Solo se permite una reserva por persona al día');
  $count = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE reservation_date=? AND slot_id=? AND status IN ('confirmed','attended')");
  $count->execute([$date, $slotId]);
  if ((int)$count->fetchColumn() >= (int)$slot['capacity']) throw new Exception('No quedan cupos disponibles');
  $code = '';
  $id = null;
  for ($i = 0; $i < 5; $i++) {
    $code = 'VS-' . strtoupper(bin2hex(random_bytes(4)));
    try {
      $ins = $pdo->prepare("INSERT INTO reservations(reservation_code,reservation_date,slot_id,first_name,last_name,rut_normalizado,whatsapp,email,whatsapp_consent) VALUES(?,?,?,?,?,?,?,?,?)");
      $ins->execute([$code, $date, $slotId, $firstName, $lastName, $rut, $whatsapp, $email, $consent]);
      $id = $pdo->lastInsertId();
      break;
    } catch (PDOException $e) {
      if (($e->errorInfo[1] ?? 0) === 1062 && str_contains((string)($e->errorInfo[2] ?? ''), 'uq_active_rut_date')) throw new Exception('Ya existe una reserva asociada a este RUT para esta fecha. Solo se permite una reserva por persona al día');
      if (($e->errorInfo[0] ?? '') === '23000' && $i < 4) continue;
      throw $e;
    }
  }
  $pdo->commit();
  respond(['reservation' => ['id' => $id, 'reservation_code' => $code, 'status' => 'confirmed']], 201);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $msg = $e instanceof PDOException ? 'No fue posible crear la reserva' : $e->getMessage();
  respond(['error' => $msg], 409);
}
