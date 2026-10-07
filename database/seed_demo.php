<?php
declare(strict_types=1);

// Reservas de demostración: 2 bloques por día (14:00–17:00 y 17:00–20:00), 20 cupos cada uno.
// Uso: SEED_DEMO=1 php database/seed_demo.php
// No afecta reservas reales: solo elimina y regenera filas source='seed'.

require __DIR__ . '/../api/db_env.php';

$cfg = dbCredentials();
$pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], dbOptions());

$dates = [
    '2026-10-01' => [4, 19],
    '2026-10-02' => [5, 19],
    '2026-10-05' => [1, 20],
    '2026-10-06' => [2, 8],
    '2026-10-07' => [3, 12],
];

$people = [
    ['Camila', 'Rojas', '+56912345678', 'camila@demo.cl'],
    ['Diego', 'Paredes', '+56987654321', 'diego@demo.cl'],
    ['Fernanda', 'Lagos', '+56911223344', 'fernanda@demo.cl'],
    ['Ignacio', 'Vera', '+56922334455', 'ignacio@demo.cl'],
    ['Javiera', 'Soto', '+56933445566', 'javiera@demo.cl'],
    ['Matías', 'Fuentes', '+56944556677', 'matias@demo.cl'],
    ['Antonia', 'Silva', '+56955667788', 'antonia@demo.cl'],
    ['Sebastián', 'Reyes', '+56966778899', 'sebastian@demo.cl'],
    ['Valentina', 'Contreras', '+56977889900', 'valentina@demo.cl'],
    ['Gabriel', 'Morales', '+56988990011', 'gabriel@demo.cl'],
];

$slotStmt = $pdo->prepare(
    "SELECT ts.id, ts.capacity
       FROM time_slots ts
       JOIN schedule_templates st ON st.id = ts.template_id
      WHERE st.weekday = ? AND st.active = 1 AND ts.active = 1
      ORDER BY ts.start_time"
);
$insert = $pdo->prepare(
    "INSERT INTO reservations
       (reservation_code, reservation_date, slot_id, first_name, last_name, rut_normalizado,
        whatsapp, email, whatsapp_consent, status, source)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 'seed')"
);
function seedRut(int $number): string {
    $body = (string) (10000000 + $number);
    $sum = 0; $factor = 2;
    for ($i = strlen($body) - 1; $i >= 0; $i--) { $sum += ((int) $body[$i]) * $factor; $factor = $factor === 7 ? 2 : $factor + 1; }
    $check = 11 - ($sum % 11);
    return $body . ($check === 11 ? '0' : ($check === 10 ? 'K' : (string) $check));
}

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM reservations WHERE source = 'seed'");
    $person = 0;
    $code = 1;
    $created = 0;

    foreach ($dates as $date => [$weekday, $reservedPerSlot]) {
        $slotStmt->execute([$weekday]);
        $slots = $slotStmt->fetchAll();
        if (count($slots) !== 2) {
            throw new RuntimeException("La fecha {$date} no tiene exactamente 2 bloques activos");
        }

        foreach ($slots as $slot) {
            if ($reservedPerSlot > (int) $slot['capacity']) {
                throw new RuntimeException("La cantidad demo supera la capacidad del bloque {$slot['id']}");
            }

            for ($i = 0; $i < $reservedPerSlot; $i++) {
                $personData = $people[$person++ % count($people)];
                $reservationCode = sprintf('VS-DEMO%04d', $code++);
                $status = $person % 8 === 0 ? 'attended' : 'confirmed';
                $insert->execute([
                    $reservationCode,
                    $date,
                    $slot['id'],
                    $personData[0],
                    $personData[1],
                    seedRut($person - 1),
                    $personData[2],
                    $personData[3] === '' ? null : $personData[3],
                    $status,
                ]);
                $created++;
            }
        }
    }

    $pdo->commit();
    echo "[seed] {$created} reservas demo creadas\n";
    echo "[seed] reservas por bloque: 01-10=19, 02-10=19, 05-10=20, 06-10=8, 07-10=12\n";
    echo "[seed] totales por día: 01-10=38/40, 02-10=38/40, 05-10=40/40, 06-10=16/40, 07-10=24/40\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, '[seed] error: ' . $e->getMessage() . "\n");
    exit(1);
}
