// Modo demostración: datos locales cuando no hay backend PHP/MySQL.
// Activo por defecto salvo VITE_DEMO_MODE=0. No toca producción real.
export const DEMO_EMAIL = 'demo@vinastage.cl';
export const DEMO_PASS = 'demo1234';
export const DEMO_ON = (import.meta.env.VITE_DEMO_MODE ?? '1') !== '0';
const KEY = 'vs_demo_v3'; // v3: reset completo para mock cupos-casi-llenos + compactacion
function pad(n) { return String(n).padStart(2, '0'); }
function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
// Mock: "casi lleno" hasta el 5 de octubre (19/20 por bloque = 57/60 diarios).
// El 5 va a 20/20 para que el admin vea un día FULL. Desde el 6 en adelante: disponible (reservas variando).
export function demoReserved(date) {
  if (date >= '2026-10-01' && date <= '2026-10-04') return 19; // bloque con 1 cupo libre
  if (date === '2026-10-05') return 20; // día FULL (agotado visualmente)
  // 6 en adelante: cupos disponibles, variando para dar vida al calendario
  if (date === '2026-10-06') return 8;
  if (date === '2026-10-07') return 12;
  return 3;
}
function nextWeekdays() {
  const names = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES'];
  const out = [];
  let cursor = new Date('2026-10-01T12:00:00');
  while (out.length < 14) {
    const d = new Date(cursor);
    if (d.getDay() >= 1 && d.getDay() <= 5) out.push({ date: iso(d), name: names[d.getDay() - 1], weekday: d.getDay() });
    cursor = new Date(d); cursor.setDate(d.getDate() + 1);
  }
  return out.slice(0, 5);
}
function seed() {
  const wd = nextWeekdays();
  const labels = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
  const days = wd.map((w, i) => ({
    id: 'day' + (i + 1), weekday: w.weekday, label: labels[i],
    capacity: 60, active: 1, date: w.date, dayName: w.name,
    slots: [
            { id: 's' + (i + 1) + 'a', start: '14:00', end: '16:00', capacity: 20, active: 1, reserved: demoReserved(w.date) },
      { id: 's' + (i + 1) + 'b', start: '16:00', end: '18:00', capacity: 20, active: 1, reserved: demoReserved(w.date) },
      { id: 's' + (i + 1) + 'c', start: '18:00', end: '20:00', capacity: 20, active: 1, reserved: demoReserved(w.date) },
    ],
  }));
  const reservations = [
    { id: 1, reservation_code: 'VS-DEMO0001', reservation_date: wd[0].date, start_time: '16:00', end_time: '18:00', slot_id: 's1b', first_name: 'Camila', last_name: 'Rojas', whatsapp: '+56912345678', email: 'camila@demo.cl', status: 'confirmed', created_at: wd[0].date },
    { id: 2, reservation_code: 'VS-DEMO0002', reservation_date: wd[0].date, start_time: '14:00', end_time: '16:00', slot_id: 's1a', first_name: 'Diego', last_name: 'Paredes', whatsapp: '+56987654321', email: '', status: 'attended', created_at: wd[0].date },
    { id: 3, reservation_code: 'VS-DEMO0003', reservation_date: wd[1].date, start_time: '18:00', end_time: '20:00', slot_id: 's2c', first_name: 'Fernanda', last_name: 'Lagos', whatsapp: '+56911223344', email: '', status: 'confirmed', created_at: wd[1].date },
  ];
  const settings = { site_name: 'Viña Stage', hero_title: 'VIÑA STAGE', hero_subtitle: 'Centro de eventos paraancis y encuentros en Viña del Mar.', address: 'Av. Valparaíso 65, Viña del Mar', whatsapp: '+56912345678', instagram: '@vina.stage', booking_start_date: '2026-10-01', booking_enabled: '1', booking_notice: 'Reservas abiertas Lun–Vie 14–20h.' };
  return { days, reservations, settings, seq: 100 };
}
export function demoLoad() {
  try {
    const raw = localStorage.getItem(KEY);
    if (raw) { const d = JSON.parse(raw); if (d && d.days) return d; }
  } catch (e) {}
  const d = seed();
  try { localStorage.setItem(KEY, JSON.stringify(d)); } catch (e) {}
  return d;
}
export function demoSave(d) { try { localStorage.setItem(KEY, JSON.stringify(d)); } catch (e) {} }
export function demoReset() { try { localStorage.removeItem(KEY); } catch (e) {} return demoLoad(); }
export function demoCheck(email, pass) { return email.trim().toLowerCase() === DEMO_EMAIL && pass === DEMO_PASS; }
// Bloques oficiales (Lun–Vie) usados cuando el demo no tiene datos guardados para esa fecha.
export const DEMO_BLOQUES = [
  { n: 1, start: '14:00', end: '16:00', capacity: 20 },
  { n: 2, start: '16:00', end: '18:00', capacity: 20 },
  { n: 3, start: '18:00', end: '20:00', capacity: 20 },
];
function diaHabil(date) { const w = new Date(date + 'T12:00:00').getDay(); return w >= 1 && w <= 5; }
// Bloques de un día en formato API (como /api/availability.php).
export function demoDaySlots(date) {
  const d = demoLoad(), day = d.days.find(x => x.date === date);
  if (day && day.slots) return day.slots.filter(s => s.active !== 0).map(s => ({ slot_id: s.id, start_time: s.start, end_time: s.end, capacity: s.capacity, reserved: s.reserved || 0, available: Math.max(0, s.capacity - (s.reserved || 0)) }));
  if (!diaHabil(date)) return [];
    return DEMO_BLOQUES.map(b => ({ slot_id: `demo-${date}-${b.n}`, start_time: b.start, end_time: b.end, capacity: b.capacity, reserved: demoReserved(date), available: Math.max(0, b.capacity - demoReserved(date)) }));
}
// Disponibilidad por rango en formato API (como /api/availability_range.php).
export function demoRange(from, to, start, today) {
  const out = [];
  const end = new Date(to + 'T12:00:00');
  for (const cur = new Date(from + 'T12:00:00'); cur <= end; cur.setDate(cur.getDate() + 1)) {
    const date = iso(cur);
    if (!diaHabil(date)) continue;
    const slots = demoDaySlots(date);
    const total = slots.reduce((n, s) => n + (s.capacity || 0), 0);
    const used = slots.reduce((n, s) => n + (s.reserved || 0), 0);
    out.push({ date, weekday: cur.getDay(), slots: slots.length, total, used, available: Math.max(0, total - used), bookable: slots.length > 0 && date >= today && (!start || date >= start) });
  }
  return out;
}
function code() { const c = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; let s = ''; for (let i = 0; i < 6; i++) s += c[Math.floor(Math.random() * c.length)]; return 'VS-' + s; }
export function demoReserve(date, slotId, f, fromApi) {
  const d = demoLoad();
  const day = d.days.find(x => x.date === date);
  const found = day ? day.slots.find(s => s.id === slotId) : null;
  const times = found
    ? { start: found.start, end: found.end, capacity: found.capacity }
    : fromApi ? { start: fromApi.start_time, end: fromApi.end_time, capacity: fromApi.capacity }
      : { start: '14:00', end: '16:00', capacity: 20 };
  const c = code();
  d.seq += 1;
  d.reservations.unshift({ id: d.seq, reservation_code: c, reservation_date: date, start_time: times.start, end_time: times.end, slot_id: slotId, first_name: f.nombre, last_name: f.apellido, whatsapp: f.whatsapp, email: f.email || '', status: 'confirmed', created_at: date });
  if (found) found.reserved = Math.min(found.capacity, (found.reserved || 0) + 1);
  demoSave(d);
  return c;
}
