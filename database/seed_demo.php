<?php
declare(strict_types=1);
// Crea reservas de ejemplo (solo si la tabla está vacía) para ver el panel admin con datos.
// Uso: SEED_DEMO=1 php database/seed_demo.php
// Los datos coinciden con el mock del frontend (src/demo.js): "casi lleno" hasta el 5 de oct, FULL el 5, disponible desde el 6.
require __DIR__ . '/../api/db_env.php';
$cfg = dbCredentials();
$pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], dbOptions());

$existing = (int)$pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
if ($existing > 0) { echo "[seed] ya existen {$existing} reservas, no se toca nada\n"; exit(0); }

// Fechas del mock (weekday REAL ISO 1=Lun..7=Dom). Se salta el finde
// porque schedule_templates solo tiene Lun-Vie (1-5).
// Coincide con src/demo.js: Thu 1 + Fri 2 casi llenos, Mon 5 FULL,
// Tue 6 y Wed 7 disponibles.
$dates = [
    '2026-10-01' => 4, // Jueves
    '2026-10-02' => 5, // Viernes
    '2026-10-05' => 1, // Lunes - FULL
    '2026-10-06' => 2, // Martes
    '2026-10-07' => 3, // Miércoles
];

// Reservas reservadas por fecha (según demo.js, solo días hábiles reales)
$reservedByDate = [
    '2026-10-01' => 19, // Jue casi lleno (1 cupo/bloque)
    '2026-10-02' => 19, // Vie casi lleno
    '2026-10-05' => 20, // Lun FULL
    '2026-10-06' => 8, // Mar disponible
    '2026-10-07' => 12, // Mié disponible
];

// Personas para las reservas demo
$people = [
    ['Camila', 'Rojas', '+56912345678', 'camila@demo.cl'],
    ['Diego', 'Paredes', '+56987654321', ''],
    ['Fernanda', 'Lagos', '+56911223344', ''],
    ['Ignacio', 'Vera', '+56922334455', 'ignacio@demo.cl'],
    ['Javiera', 'Soto', '+56933445566', ''],
    ['Matías', 'Fuentes', '+56944556677', ''],
    ['Antonia', 'Silva', '+56955667788', ''],
];
$statuses = ['confirmed', 'confirmed', 'confirmed', 'attended'];

// Obtener slots por weekday
$slotStmt = $pdo->prepare("SELECT ts.id, ts.capacity, ts.start_time, ts.end_time FROM time_slots ts JOIN schedule_templates st ON st.id=ts.template_id WHERE st.active=1 AND ts.active=1 AND st.weekday=? ORDER BY ts.start_time");
$insStmt = $pdo->prepare("INSERT INTO reservations(reservation_code,reservation_date,slot_id,first_name,last_name,whatsapp,email,whatsapp_consent,status,source) VALUES(?,?,?,?,?,?,?,1,?,'seed')");

$created = 0;
$personIdx = 0;
$demoCodeIdx = 1;

// Crear reservas para cada fecha
foreach ($dates as $date => $weekday) {
    $reserved = $reservedByDate[$date] ?? 0;
    $slotStmt->execute([$weekday]);
    $slots = $slotStmt->fetchAll();
    if (!$slots) continue;

    $slotsCount = count($slots);
    $reservedPerSlot = (int)floor($reserved / $slotsCount);
    $extra = $reserved % $slotsCount;

    $slotIdx = 0;
    foreach ($slots as $slot) {
        // Distribuir las reservas entre los slots
        $count = $reservedPerSlot + ($slotIdx < $extra ? 1 : 0);
        
        for ($n = 0; $n < $count; $n++) {
            $p = $people[$personIdx % count($people)];
            $status = $statuses[$personIdx % count($statuses)];
            
            // Primeras 3 reservas con código VS-DEMO000X (coinciden con mock frontend)
            if ($demoCodeIdx <= 3) {
                $code = sprintf('VS-DEMO%04d', $demoCodeIdx++);
            } else {
                $code = 'VS-' . strtoupper(bin2hex(random_bytes(4)));
            }
            
            $insStmt->execute([$code, $date, $slot['id'], $p[0], $p[1], $p[2], $p[3] === '' ? null : $p[3], $status]);
            $created++;
            $personIdx++;
        }
        $slotIdx++;
    }
}

echo "[seed] {$created} reservas de ejemplo creadas (coinciden con mock frontend)\n";
