const RUT_ERROR = 'El RUT ingresado no es válido. Revisa los datos e inténtalo nuevamente.';

function cleanRut(value) {
  return String(value ?? '').replace(/\s/g, '').replace(/k/g, 'K');
}

function isGroupedBody(body) {
  if (!/^\d{1,3}(?:\.\d{3}){0,2}$/.test(body) && !/^\d{1,8}$/.test(body)) return false;
  if (!body.includes('.')) return true;
  return body.split('.').every((part, index) => index === 0 ? /^\d{1,3}$/.test(part) && part.length > 0 : /^\d{3}$/.test(part));
}

export function normalizeRut(value) {
  const raw = cleanRut(value);
  if (/^\d{8}$/.test(raw)) return '';
  if (!/^\d{1,8}(?:\.\d{3}){0,2}-?[0-9K]$/.test(raw) && !/^\d{1,3}(?:\.\d{3}){0,2}-?[0-9K]$/.test(raw)) return '';
  const compact = raw.replace(/\./g, '').replace(/-/g, '');
  const body = compact.slice(0, -1);
  const verifier = compact.slice(-1);
  const bodyText = raw.replace(/-?[0-9K]$/, '');
  if (!body || !/^\d{1,8}$/.test(body) || !isGroupedBody(bodyText) || /^0+$/.test(body)) return '';
  return body + verifier;
}

export function validateRut(value) {
  const compact = normalizeRut(value);
  if (!compact) return false;
  const body = compact.slice(0, -1);
  const expected = rutVerifier(body);
  return expected === compact.slice(-1);
}

// Alias compatible con consumidores existentes.
export const isValidRut = validateRut;

function rutVerifier(body) {
  let sum = 0, factor = 2;
  for (let i = body.length - 1; i >= 0; i--) {
    sum += Number(body[i]) * factor;
    factor = factor === 7 ? 2 : factor + 1;
  }
  const check = 11 - (sum % 11);
  return check === 11 ? '0' : check === 10 ? 'K' : String(check);
}

export function formatRut(value) {
  const compact = sanitizeRutInput(value);
  if (!compact) return '';
  const hasVerifier = compact.endsWith('K') || compact.length > 8;
  const body = hasVerifier ? compact.slice(0, -1) : compact;
  const verifier = hasVerifier ? compact.slice(-1) : '';
  const groups = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return groups + (verifier ? '-' + verifier : '');
}

export function sanitizeRutInput(value) {
  return cleanRut(value).replace(/[^0-9K-]/g, '').replace(/-/g, '').slice(0, 9);
}

export function rutError() { return RUT_ERROR; }
