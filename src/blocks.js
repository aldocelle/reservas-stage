// Bloques oficiales de reserva (lunes a viernes). Fuente única de verdad para web y panel admin.
export const BLOQUES = [
  { n: 1, start: '14:00', end: '16:00' },
  { n: 2, start: '16:00', end: '18:00' },
  { n: 3, start: '18:00', end: '20:00' },
];
const DIAS = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES'];

function hhmm(t) { return String(t || '').slice(0, 5); }
// Devuelve { n, nombre, rango } para un horario concreto ("14:00" -> Bloque 1 · 14:00 a 16:00 Hrs.)
export function bloqueInfo(start, end) {
  const s = hhmm(start), e = hhmm(end);
  const b = BLOQUES.find(x => x.start === s);
  return { n: b ? b.n : 0, nombre: b ? `Bloque ${b.n}` : '', rango: e ? `${s} a ${e} Hrs.` : `${s} Hrs.` };
}
export function bloqueNombre(start) { return bloqueInfo(start, '').nombre; }
export function esDiaHabil(name) { return DIAS.includes(String(name || '').toUpperCase()); }
