<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { bloqueNombre } from './blocks';
const API = import.meta.env.VITE_API_BASE_URL || '/api';
const authed = ref(false), admin = ref(null);
const loginForm = ref({ email: '', password: '' });
const loginError = ref(''), loginBusy = ref(false);
const tab = ref('overview');
const loading = ref(true), error = ref('');
const dash = ref({ totals: {}, today_reservations: [], reservation_days: [], next: [] });
const selectedSummaryDate = ref('');
const summaryCalendarRef = ref(null);
const summaryDetailRef = ref(null);
const adminFiltersRef = ref(null);
const summaryMonth = ref({ year: new Date().getFullYear(), month: new Date().getMonth() });
const monthNames = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
const pad = value => String(value).padStart(2, '0');
const isoDate = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const summaryMonthLabel = computed(() => `${monthNames[summaryMonth.value.month]} ${summaryMonth.value.year}`);
const summaryCalendar = computed(() => {
  const year = summaryMonth.value.year;
  const month = summaryMonth.value.month;
  const first = new Date(year, month, 1);
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const lead = (first.getDay() + 6) % 7;
  const totals = Object.fromEntries((dash.value.reservation_days || []).map(d => [d.reservation_date, Number(d.total) || 0]));
  const cells = Array.from({ length: lead }, () => null);
  for (let day = 1; day <= daysInMonth; day++) {
    const date = isoDate(new Date(year, month, day));
    cells.push({ date, day, total: totals[date] || 0 });
  }
  while (cells.length % 7) cells.push(null);
  return cells;
});
function moveSummaryMonth(step) {
  const d = new Date(summaryMonth.value.year, summaryMonth.value.month + step, 1);
  summaryMonth.value = { year: d.getFullYear(), month: d.getMonth() };
}
const days = ref([]);
const editDay = ref({});
const newSlots = ref({});
const reservations = ref([]);
const sortKey = ref('reservation_date');
const sortDirection = ref('desc');
const sortedReservations = computed(() => {
  const key = sortKey.value;
  const direction = sortDirection.value === 'asc' ? 1 : -1;
  return [...reservations.value].sort((a, b) => {
    const av = key === 'name' ? `${a.first_name} ${a.last_name}` : a[key];
    const bv = key === 'name' ? `${b.first_name} ${b.last_name}` : b[key];
    return String(av ?? '').localeCompare(String(bv ?? ''), 'es', { numeric: true, sensitivity: 'base' }) * direction;
  });
});
function sortBy(key) {
  if (sortKey.value === key) sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  else { sortKey.value = key; sortDirection.value = 'asc'; }
}
const sortLabel = key => sortKey.value === key ? (sortDirection.value === 'asc' ? '↑' : '↓') : '↕';
const filters = ref({ date: '', status: '', q: '' });
const resBusy = ref(false);
let searchTimer = null;
let searchSeq = 0;
const slotBusy = ref('');
const pendingSlot = ref('');
const settings = ref({});
const settingsBusy = ref(false), settingsMsg = ref('');
const tabs = [['overview','Resumen'],['bookings','Reservas'],['config','Config']];
const STATUS_LABELS = { confirmed: 'Confirmada', attended: 'Asistió', cancelled: 'Cancelada', no_show: 'No show' };
const STATUS_COLORS = { confirmed: 'confirmada', attended: 'atendida', cancelled: 'cancelada', no_show: 'noshow' };
async function api(path, opts) {
  const r = await fetch(API + path, Object.assign({ credentials: 'include', headers: { 'Content-Type': 'application/json' } }, opts || {}));
  const x = await r.json().catch(() => null);
  if (x === null) throw new Error('Respuesta inválida del servidor');
  if (!r.ok) throw new Error(x.error || 'Error de red');
  return x;
}
async function checkAuth() {
  try { const x = await api('/auth.php?action=status'); authed.value = !!x.authenticated; admin.value = x.admin || null; }
  catch (e) { authed.value = false; }
}
async function doLogin() {
  loginBusy.value = true; loginError.value = '';
  try {
    const x = await api('/auth.php?action=login', { method: 'POST', body: JSON.stringify({ email: loginForm.value.email, password: loginForm.value.password }) });
    authed.value = true; admin.value = x.admin; loginForm.value.password = ''; await loadAll();
  } catch (e) { loginError.value = e.message; }
  finally { loginBusy.value = false; }
}
async function doLogout() {
  try { await api('/auth.php?action=logout', { method: 'POST', body: '{}' }); } catch (e) {}
  authed.value = false; admin.value = null;
}
async function loadSummaryDate(date) {
  if (!date) return;
  selectedSummaryDate.value = date;
  filters.value.date = date;
  await loadReservations();
}
async function loadAll() {
  loading.value = true; error.value = '';
  try {
    dash.value = await api('/dashboard.php');
    if (dash.value.reservation_days?.length) {
      const firstDate = new Date(`${dash.value.reservation_days[0].reservation_date}T12:00:00`);
      summaryMonth.value = { year: firstDate.getFullYear(), month: firstDate.getMonth() };
      await loadSummaryDate(dash.value.reservation_days[0].reservation_date);
    }
    const s = await api('/schedules.php'); days.value = s.days || [];
    editDay.value = {}; newSlots.value = {};
    for (const d of days.value) {
      editDay.value[d.id] = { label: d.label, capacity: d.capacity, active: !!d.active };
            newSlots.value[d.id] = { start: '', end: '', capacity: 20 };
    }
    await loadReservations(); await loadSettings();
  } catch (e) { error.value = e.message; }
  finally { loading.value = false; }
}
async function saveDay(id) {
  const e = editDay.value[id];
  await api('/schedules.php?path=' + encodeURIComponent(id), { method: 'PATCH', body: JSON.stringify(e) });
  await loadAll();
}
async function saveSlot(id, p) {
  error.value = '';
  if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(p.start) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(p.end) || p.end <= p.start) { error.value = 'Revisa que el inicio y fin sean horarios válidos y que el fin sea posterior.'; return; }
  if (Number(p.capacity) < 1 || Number(p.capacity) > 500) { error.value = 'Los cupos deben estar entre 1 y 500.'; return; }
  slotBusy.value = id;
  try {
    await api('/schedules.php?path=slots/' + encodeURIComponent(id), { method: 'PATCH', body: JSON.stringify(p) });
    await loadAll();
  } catch (e) { error.value = e.message || 'No fue posible guardar el bloque.'; }
  finally { slotBusy.value = ''; }
}
async function createSlot(tid) {
  const n = newSlots.value[tid];
  if (!n || !/^([01]\d|2[0-3]):[0-5]\d$/.test(n.start) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(n.end) || n.end <= n.start) { error.value = 'Completa inicio y fin con horarios válidos (fin posterior al inicio).'; return; }
  if (Number(n.capacity) < 1 || Number(n.capacity) > 500) { error.value = 'Los cupos deben estar entre 1 y 500.'; return; }
  error.value = ''; slotBusy.value = tid;
  try {
    await api('/schedules.php?path=' + encodeURIComponent(tid) + '/slots', { method: 'POST', body: JSON.stringify(n) });
    await loadAll();
  } catch (e) { error.value = e.message || 'No fue posible crear el bloque.'; }
  finally { slotBusy.value = ''; }
}
async function removeSlot(id) {
  if (pendingSlot.value !== id) { pendingSlot.value = id; return; }
  pendingSlot.value = ''; error.value = ''; slotBusy.value = id;
  try {
    await api('/schedules.php?path=slots/' + encodeURIComponent(id), { method: 'DELETE' });
    await loadAll();
  } catch (e) { error.value = e.message || 'No fue posible desactivar el bloque.'; }
  finally { slotBusy.value = ''; }
}
async function loadReservations() {
  const seq = ++searchSeq;
  resBusy.value = true;
  const p = new URLSearchParams();
  if (filters.value.date) p.set('date', filters.value.date);
  if (filters.value.status) p.set('status', filters.value.status);
  if (filters.value.q) p.set('q', filters.value.q);
  try {
    const x = await api('/admin_reservations.php?' + p.toString());
    if (seq === searchSeq) reservations.value = x.reservations || [];
  } catch (e) { if (seq === searchSeq) error.value = e.message; }
  finally { if (seq === searchSeq) resBusy.value = false; }
}
async function setStatus(id, status) {
  resBusy.value = true;
  try { await api('/admin_reservations.php?path=' + id + '/status', { method: 'PATCH', body: JSON.stringify({ status }) }); await loadReservations(); }
  catch (e) { error.value = e.message; }
  finally { resBusy.value = false; }
}
async function loadSettings() { const x = await api('/settings.php'); settings.value = x.settings || {}; }
async function saveSettings() {
  settingsBusy.value = true; settingsMsg.value = '';
  try { await api('/settings.php', { method: 'PUT', body: JSON.stringify(settings.value) }); settingsMsg.value = 'Guardado ✓'; }
  catch (e) { settingsMsg.value = e.message; }
  finally { settingsBusy.value = false; }
}
watch(() => filters.value.q, value => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadReservations(), 250);
});
watch([() => filters.value.date, () => filters.value.status], () => loadReservations());
function clearSummaryDate() {
  selectedSummaryDate.value = '';
  if (filters.value.date) filters.value.date = '';
}
async function clearReservationFilters() {
  selectedSummaryDate.value = '';
  filters.value = { date: '', status: '', q: '' };
  await loadReservations();
}
function onDocumentPointerDown(event) {
  if (summaryCalendarRef.value
    && !summaryCalendarRef.value.contains(event.target)
    && (!summaryDetailRef.value || !summaryDetailRef.value.contains(event.target))
    && (!adminFiltersRef.value || !adminFiltersRef.value.contains(event.target))) clearSummaryDate();
}
onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer); document.removeEventListener('pointerdown', onDocumentPointerDown); });
onMounted(async () => { document.addEventListener('pointerdown', onDocumentPointerDown); await checkAuth(); if (authed.value) await loadAll(); else loading.value = false; });
</script>
<template>
<div class="admin-wrap">
<header class="admin-top">
<div><a href="#eventos" class="admin-back">Volver al sitio</a>
<h1>Panel Admin - Vina Stage</h1>
<small v-if="admin">{{ admin.email }} - {{ admin.role }}</small></div>
<button v-if="authed" type="button" class="ghost-btn small" @click="doLogout">Salir</button>
</header>
<section v-if="!authed" class="panel form-panel admin-login">
<div class="section-title">INGRESO ADMINISTRADOR</div>
<form @submit.prevent="doLogin">
<div class="form-grid">
<label class="field"><span aria-hidden="true">E</span><span class="field-content"><span class="field-label">Email *</span><input v-model.trim="loginForm.email" type="email" required autocomplete="username" placeholder="tu@email.com"></span></label>
<label class="field"><span aria-hidden="true">*</span><span class="field-content"><span class="field-label">Contraseña *</span><input v-model="loginForm.password" type="password" required autocomplete="current-password" placeholder="••••••••"></span></label>
</div>
<p v-if="loginError" class="admin-error" role="alert">{{ loginError }}</p>
<button class="reserve-btn" :class="{saving:loginBusy}" type="submit" :disabled="loginBusy">{{ loginBusy ? 'INGRESANDO...' : 'INGRESAR' }}</button>
</form>
    <p class="admin-hint">Usa las credenciales administrativas configuradas en el backend.</p>
</section>
<template v-else>
<nav class="admin-tabs" role="tablist">
<button v-for="t in tabs" :id="'tab-'+t[0]" :key="t[0]" type="button" role="tab" :aria-selected="tab === t[0]" :aria-controls="'panel-'+t[0]" :tabindex="tab === t[0] ? 0 : -1" :class="['admin-tab', { active: tab === t[0] }]" @click="tab = t[0]; pendingSlot = ''">{{ t[1] }}</button>
</nav>
<p v-if="error" class="notice" role="alert">{{ error }}</p>
<div v-if="loading" class="loading-row">Cargando panel...</div>
<section v-if="authed && !loading && tab === 'overview'" id="panel-overview" class="admin-grid" role="tabpanel" aria-labelledby="tab-overview">
<div class="panel stat"><b>{{ (dash.totals && dash.totals.today) || 0 }}</b><span>Reservas hoy</span></div>
<div class="panel stat"><b>{{ (dash.totals && dash.totals.upcoming) || 0 }}</b><span>Proximas vigentes</span></div>
<div class="panel stat"><b>{{ (dash.totals && dash.totals.total) || 0 }}</b><span>Total historico</span></div>
<div class="panel stat"><b>{{ (dash.totals && dash.totals.cancelled) || 0 }}</b><span>Canceladas</span></div>

<div class="panel wide">
<div class="section-title">RESERVAS DE HOY · {{ dash.today || 'HOY' }}</div>
<div class="table-wrap"><table class="admin-table">
<thead><tr><th>Hora</th><th>Nombre</th><th>Código</th><th>Estado</th></tr></thead>
<tbody><tr v-for="r in dash.today_reservations" :key="r.id"><td><span class="bloque-tag">{{ bloqueNombre(r.start_time) || '—' }}</span><br><span class="muted">{{ r.start_time }} - {{ r.end_time }}</span></td><td>{{ r.first_name }} {{ r.last_name }}</td><td><code>{{ r.reservation_code }}</code></td><td><span :class="['pill', r.status]">{{ STATUS_LABELS[r.status] || r.status }}</span></td></tr></tbody>
</table></div>
<p v-if="!dash.today_reservations || !dash.today_reservations.length" class="empty-note">No hay reservas para hoy.</p>
</div>
<div class="panel wide">
<div class="section-title">PROXIMAS 8 RESERVAS</div>
<div class="table-wrap"><table class="admin-table">
<thead><tr><th>Fecha</th><th>Hora</th><th>Nombre</th><th>Codigo</th><th>Estado</th></tr></thead>
<tbody><tr v-for="r in dash.next" :key="r.id"><td>{{ r.reservation_date }}</td><td><span class="bloque-tag">{{ bloqueNombre(r.start_time) || '—' }}</span><br><span class="muted">{{ r.start_time }} - {{ r.end_time }}</span></td><td>{{ r.first_name }} {{ r.last_name }}</td><td><code>{{ r.reservation_code }}</code></td><td><span :class="['pill', r.status]">{{ r.status }}</span></td></tr></tbody>
</table></div>
<p class="day-note">Los cambios en los bloques se guardan automáticamente al actualizar la página. Los cupos del día se reparten entre los bloques activos.</p>
</div>
</section>
<section v-if="authed && !loading && tab === 'config'" id="panel-config-days" class="admin-stack" role="tabpanel" aria-labelledby="tab-config">
<div class="section-title">DÍAS Y HORARIOS</div>
<div v-for="d in days" :key="d.id" class="panel day-card">
<div class="day-card-head">
<strong>{{ d.label }} <small>(día {{ d.weekday }} · {{ d.slots.filter(s => s.active !== 0).length }} bloques)</small></strong>
<label class="switch"><input v-model="editDay[d.id].active" type="checkbox"><span>Activo</span></label>
</div>
<div class="day-edit">
  <label>Nombre<input v-model="editDay[d.id].label" maxlength="30"></label>
  <label>Capacidad día<input v-model.number="editDay[d.id].capacity" type="number" min="1" max="500"></label>
  <button type="button" class="reserve-btn small" :class="{saving:slotBusy === d.id}" @click="saveDay(d.id)" :disabled="loading || slotBusy === d.id">Guardar día</button>
</div>
<div class="table-wrap"><table class="admin-table">
<thead><tr><th>Bloque</th><th>Inicio</th><th>Fin</th><th>Cupos</th><th>Activo</th><th>Acciones</th></tr></thead>
<tbody>
<tr v-for="s in d.slots" :key="s.id">
<td><span class="bloque-tag">{{ bloqueNombre(s.start) || '—' }}</span></td>
<td><input v-model="s.start" class="mini" maxlength="5" placeholder="14:00" :aria-label="'Inicio del bloque ' + (bloqueNombre(s.start) || s.id)"></td>
<td><input v-model="s.end" class="mini" maxlength="5" placeholder="16:00" :aria-label="'Fin del bloque ' + (bloqueNombre(s.start) || s.id)"></td>
<td><input v-model.number="s.capacity" class="mini" type="number" min="1" max="500" :aria-label="'Cupos del bloque ' + (bloqueNombre(s.start) || s.id)"></td>
<td><input v-model="s.active" type="checkbox" :aria-label="'Activar bloque ' + (bloqueNombre(s.start) || s.id)"></td>
  <td class="row-actions"><button type="button" class="ghost-btn small" :disabled="slotBusy === s.id" @click="saveSlot(s.id, s)">Guardar</button><button type="button" class="danger small" :disabled="slotBusy === s.id" :aria-label="pendingSlot === s.id ? 'Confirmar desactivación del bloque' : 'Desactivar bloque'" @click="removeSlot(s.id)">{{ pendingSlot === s.id ? '¿Desactivar?' : 'Desactivar' }}</button></td>
</tr>
</tbody>
</table></div>
<div class="new-slot">
  <strong>AGREGAR BLOQUE</strong>
  <label>Inicio<input v-model="newSlots[d.id].start" type="time" required></label>
  <label>Fin<input v-model="newSlots[d.id].end" type="time" required></label>
  <label>Cupos<input v-model.number="newSlots[d.id].capacity" type="number" min="1" max="500" required></label>
  <button type="button" class="ghost-btn small" :disabled="slotBusy === d.id" @click="createSlot(d.id)">Agregar</button>
</div>
<p class="day-note">Guarda cada cambio para aplicarlo. Los cupos del día se reparten entre los bloques activos.</p>
</div>
</section>
<section v-if="authed && !loading && tab === 'bookings'" id="panel-bookings" class="panel wide" role="tabpanel" aria-labelledby="tab-bookings">
<div class="section-title">RESERVAS</div>
 <div ref="summaryCalendarRef" class="summary-calendar" role="grid" aria-label="Calendario mensual de reservas"><div class="summary-calendar-head"><button type="button" class="calendar-nav" aria-label="Mes anterior" @click="moveSummaryMonth(-1)">‹</button><strong>{{ summaryMonthLabel }}</strong><button type="button" class="calendar-nav" aria-label="Mes siguiente" @click="moveSummaryMonth(1)">›</button></div><div class="summary-calendar-week"><span v-for="d in ['LU','MA','MI','JU','VI','SÁ','DO']" :key="d">{{ d }}</span></div><div class="summary-calendar-grid"><template v-for="(cell, i) in summaryCalendar" :key="cell?.date || `empty-${i}`"><span v-if="!cell" class="summary-calendar-empty" aria-hidden="true"></span><button v-else type="button" :class="['summary-calendar-day', { active: selectedSummaryDate === cell.date, booked: !!cell.total }]" :aria-label="`${cell.date}, ${cell.total} reservas`" @click="loadSummaryDate(cell.date)"><strong>{{ cell.day }}</strong><span v-if="cell.total">{{ cell.total }}</span></button></template></div></div>
<form ref="adminFiltersRef" class="admin-filters" aria-label="Filtros de reservas" @submit.prevent="loadReservations">
<label>Fecha<input v-model="filters.date" type="date"></label>
<label>Estado<select v-model="filters.status"><option value="">Todos</option><option value="confirmed">Confirmada</option><option value="attended">Asistió</option><option value="cancelled">Cancelada</option><option value="no_show">No show</option></select></label>
<label class="filter-search">Búsqueda<input v-model.trim="filters.q" autocomplete="off" placeholder="Código, nombre o WhatsApp" aria-label="Buscar reservas"></label>
<div class="filter-actions">
  <span v-if="resBusy" class="filter-loading" role="status">Buscando…</span>
  <button type="submit" class="ghost-btn small" :disabled="resBusy">Filtrar</button>
  <button type="button" class="ghost-btn small" :disabled="resBusy || (!filters.date && !filters.status && !filters.q)" @click="clearReservationFilters">Limpiar</button>
</div>
</form>
<div ref="summaryDetailRef" class="summary-detail unified-reservations">
<div class="section-title summary-detail-title">{{ selectedSummaryDate ? 'RESERVAS DEL DÍA SELECCIONADO' : 'TODAS LAS RESERVAS' }}<span v-if="selectedSummaryDate"> · {{ selectedSummaryDate }}</span></div>
<div v-if="resBusy" class="loading-row">Actualizando reservas...</div>
<div class="table-wrap"><table class="admin-table">
<thead><tr>
<th><button type="button" class="admin-sort" :class="{ active: sortKey === 'reservation_date' }" @click="sortBy('reservation_date')">Fecha <span>{{ sortLabel('reservation_date') }}</span></button></th>
<th><button type="button" class="admin-sort" :class="{ active: sortKey === 'start_time' }" @click="sortBy('start_time')">Hora <span>{{ sortLabel('start_time') }}</span></button></th>
<th><button type="button" class="admin-sort" :class="{ active: sortKey === 'name' }" @click="sortBy('name')">Nombre <span>{{ sortLabel('name') }}</span></button></th>
<th><button type="button" class="admin-sort" :class="{ active: sortKey === 'whatsapp' }" @click="sortBy('whatsapp')">WhatsApp <span>{{ sortLabel('whatsapp') }}</span></button></th>
<th><button type="button" class="admin-sort" :class="{ active: sortKey === 'reservation_code' }" @click="sortBy('reservation_code')">Código <span>{{ sortLabel('reservation_code') }}</span></button></th>
<th><button type="button" class="admin-sort" :class="{ active: sortKey === 'status' }" @click="sortBy('status')">Estado <span>{{ sortLabel('status') }}</span></button></th>
<th></th>
</tr></thead>
<tbody>
<tr v-for="(r,rIdx) in sortedReservations" :key="r.id" :style="{'--i':rIdx}">
<td>{{ r.reservation_date }}</td><td><span class="bloque-tag">{{ bloqueNombre(r.start_time) || '—' }}</span><br><span class="muted">{{ r.start_time }} - {{ r.end_time }}</span></td>
<td>{{ r.first_name }} {{ r.last_name }}</td><td><a :href="'https://wa.me/' + String(r.whatsapp).replace(/[^0-9]/g, '')" target="_blank" rel="noreferrer">{{ r.whatsapp }}</a></td>
<td><code>{{ r.reservation_code }}</code></td>
<td><span :class="['pill', r.status]">{{ STATUS_LABELS[r.status] || r.status }}</span></td>
<td class="row-actions">
<button type="button" class="ghost-btn small" :disabled="resBusy" @click="setStatus(r.id, 'attended')">Asistió</button>
<button type="button" class="ghost-btn small" :disabled="resBusy" @click="setStatus(r.id, 'confirmed')">Confirmar</button>
<button type="button" class="danger small" :disabled="resBusy" @click="setStatus(r.id, 'cancelled')">Cancelar</button>
</td>
</tr>
</tbody>
</table></div>
</div>
<p v-if="!reservations.length" class="empty-note">Sin reservas para esos filtros.</p>
</section>
<section v-if="authed && !loading && tab === 'config'" id="panel-config" class="panel form-panel" role="tabpanel" aria-labelledby="tab-config">
<div class="section-title">CONFIGURACION DEL SITIO</div>
<div class="admin-form">
<label>Nombre del sitio<input v-model="settings.site_name" maxlength="80"></label>
<label>Dirección<input v-model="settings.address" maxlength="200"></label>
<label>WhatsApp contacto<input v-model="settings.whatsapp" maxlength="30"></label>
<label>Instagram<input v-model="settings.instagram" maxlength="120"></label>
<label>Reservas desde (inicio del calendario)<input v-model="settings.booking_start_date" type="date"></label>
<label class="switch"><input v-model="settings.booking_enabled" type="checkbox" true-value="1" false-value="0"><span>Reservas habilitadas</span></label>
<label>Aviso de reservas<textarea v-model="settings.booking_notice" rows="2" maxlength="500" placeholder="Ej: Esta semana solo jueves y viernes"></textarea></label>
</div>
<p v-if="settingsMsg" class="admin-ok" role="status">{{ settingsMsg }}</p>
<button type="button" class="reserve-btn" :class="{saving:settingsBusy}" :disabled="settingsBusy" @click="saveSettings">{{ settingsBusy ? 'GUARDANDO...' : 'GUARDAR CONFIG' }}</button>
</section>
</template>
</div>
</template>

