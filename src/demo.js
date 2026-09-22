// Modo demostración: datos locales cuando no hay backend PHP/MySQL.
// Activo por defecto salvo VITE_DEMO_MODE=0. No toca producción real.
export const DEMO_EMAIL = 'demo@vinastage.cl';
export const DEMO_PASS = 'demo1234';
export const DEMO_ON = (import.meta.env.VITE_DEMO_MODE ?? '1') !== '0';
const KEY = 'vs_demo_v1';
function pad(n) { return String(n).padStart(2, '0'); }
function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
function nextWeekdays() {
  const names = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES'];
  const out = [], now = new Date(); now.setHours(12, 0, 0, 0);
  for (let i = 0; i < 14 && out.length < 5; i++) {
    const d = new Date(now); d.setDate(now.getDate() + i);
    if (d.getDay() >= 1 && d.getDay() <= 5) out.push({ date: iso(d), name: names[d.getDay() - 1], weekday: d.getDay() });
  }
  return out;
}
function seed() {
  const wd = nextWeekdays();
  const labels = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
  const days = wd.map((w, i) => ({
    id: 'day' + (i + 1), weekday: w.weekday, label: labels[i],
    capacity: 40, active: 1, date: w.date, dayName: w.name,
    slots: [
      { id: 's' + (i + 1) + 'a', start: '14:00', end: '16:00', capacity: 14, active: 1, reserved: 5 + ((i * 2) % 5) },
      { id: 's' + (i + 1) + 'b', start: '16:00', end: '18:00', capacity: 14, active: 1, reserved: 7 + ((i * 3) % 4) },
      { id: 's' + (i + 1) + 'c', start: '18:00', end: '20:00', capacity: 12, active: 1, reserved: 4 + ((i * 2) % 6) },
    ],
  }));
  const reservations = [
    { id: 1, reservation_code: 'VS-DEMO0001', reservation_date: wd[0].date, start_time: '16:00', end_time: '18:00', slot_id: 's1b', first_name: 'Camila', last_name: 'Rojas', whatsapp: '+56912345678', email: 'camila@demo.cl', status: 'confirmed', created_at: wd[0].date },
    { id: 2, reservation_code: 'VS-DEMO0002', reservation_date: wd[0].date, start_time: '14:00', end_time: '16:00', slot_id: 's1a', first_name: 'Diego', last_name: 'Paredes', whatsapp: '+56987654321', email: '', status: 'attended', created_at: wd[0].date },
    { id: 3, reservation_code: 'VS-DEMO0003', reservation_date: wd[1].date, start_time: '18:00', end_time: '20:00', slot_id: 's2c', first_name: 'Fernanda', last_name: 'Lagos', whatsapp: '+56911223344', email: '', status: 'confirmed', created_at: wd[1].date },
  ];
  const settings = { site_name: 'Viña Stage', hero_title: 'VIÑA STAGE', hero_subtitle: 'Música, eventos y noches para vivir Viña desde el centro.', address: 'Av. Valparaíso 65, Viña del Mar', whatsapp: '+56912345678', instagram: '@vina.stage', booking_enabled: '1', booking_notice: 'Demo: reservas abiertas Lun–Vie 14–20h.' };
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
function code() { const c = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; let s = ''; for (let i = 0; i < 6; i++) s += c[Math.floor(Math.random() * c.length)]; return 'VS-' + s; }
export function demoReserve(date, slotId, f) {
  const d = demoLoad();
  const day = d.days.find(x => x.date === date) || d.days[0];
  const slot = day.slots.find(s => s.id === slotId) || day.slots[0];
  const c = code();
  d.seq += 1;
  d.reservations.unshift({ id: d.seq, reservation_code: c, reservation_date: day.date, start_time: slot.start, end_time: slot.end, slot_id: slot.id, first_name: f.nombre, last_name: f.apellido, whatsapp: f.whatsapp, email: f.email || '', status: 'confirmed', created_at: day.date });
  slot.reserved = Math.min(slot.capacity, (slot.reserved || 0) + 1);
  demoSave(d);
  return c;
}
