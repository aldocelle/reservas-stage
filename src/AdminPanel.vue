<script setup>
import { onMounted, ref } from 'vue';
import { DEMO_EMAIL, DEMO_ON, demoCheck, demoLoad, demoReset, demoSave } from './demo';
import { bloqueNombre } from './blocks';
const API = import.meta.env.VITE_API_BASE_URL || '/api';
const authed = ref(false), admin = ref(null);
const loginForm = ref({ email: '', password: '' });
const loginError = ref(''), loginBusy = ref(false);
const tab = ref('overview');
const loading = ref(true), error = ref('');
const dash = ref({ totals: {}, next: [] });
const days = ref([]);
const editDay = ref({});
const newSlots = ref({});
const reservations = ref([]);
const filters = ref({ date: '', status: '', q: '' });
const resBusy = ref(false);
const slotBusy = ref('');
const pendingSlot = ref('');
const settings = ref({});
const settingsBusy = ref(false), settingsMsg = ref('');
const demoMode = ref(false);
const demoEmail = DEMO_EMAIL;
const demoOn = DEMO_ON;
const tabs = [['overview','Resumen'],['days','Días y horarios'],['bookings','Reservas'],['config','Config']];
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
  try { const x = await api('/auth.php?action=status'); authed.value = !!x.authenticated; admin.value = x.admin || null; if (authed.value) demoMode.value = false; }
  catch (e) {
    if (DEMO_ON && sessionStorage.getItem('vs_demo_admin') === '1') {
      authed.value = true; admin.value = { email: DEMO_EMAIL, role: 'admin' }; demoMode.value = true;
    } else { authed.value = false; }
  }
}
async function doLogin() {
  loginBusy.value = true; loginError.value = '';
  try {
    const x = await api('/auth.php?action=login', { method: 'POST', body: JSON.stringify({ email: loginForm.value.email, password: loginForm.value.password }) });
    authed.value = true; admin.value = x.admin; demoMode.value = false; loginForm.value.password = ''; await loadAll();
  } catch (e) {
    if (DEMO_ON && demoCheck(loginForm.value.email, loginForm.value.password)) {
      try { sessionStorage.setItem('vs_demo_admin', '1'); } catch (se) {}
      authed.value = true; admin.value = { email: DEMO_EMAIL, role: 'admin' }; demoMode.value = true; loginError.value = ''; loginForm.value.password = ''; await loadAllDemo();
    } else { loginError.value = e.message; }
  }
  finally { loginBusy.value = false; }
}
async function doLogout() {
  try { await api('/auth.php?action=logout', { method: 'POST', body: '{}' }); } catch (e) {}
  try { sessionStorage.removeItem('vs_demo_admin'); } catch (se) {}
  authed.value = false; admin.value = null; demoMode.value = false;
}
function loadAllDemo() {
  loading.value = true; error.value = '';
  try {
    const d = demoLoad();
    days.value = d.days.map(x => ({ id: x.id, weekday: x.weekday, label: x.label, capacity: x.capacity, active: x.active, slots: x.slots.map(s => ({ id: s.id, start: s.start, end: s.end, capacity: s.capacity, active: s.active })) }));
    editDay.value = {}; newSlots.value = {};
        for (const day of days.value) { editDay.value[day.id] = { label: day.label, capacity: day.capacity, active: !!day.active }; newSlots.value[day.id] = { start: '', end: '', capacity: 20 }; }
    const today = new Date().toISOString().slice(0, 10);
    dash.value = { totals: { today: d.reservations.filter(r => r.reservation_date === today).length, upcoming: d.reservations.filter(r => r.status === 'confirmed').length, total: d.reservations.length, cancelled: d.reservations.filter(r => r.status === 'cancelled').length }, next: d.reservations.slice(0, 8) };
    reservations.value = d.reservations.slice(0, 200);
    settings.value = Object.assign({}, d.settings);
  } catch (e) { error.value = e.message; }
  finally { loading.value = false; }
}
async function loadAll() {
  loading.value = true; error.value = '';
  try {
    dash.value = await api('/dashboard.php');
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
  if (demoMode.value) return demoSaveDay(id);
  const e = editDay.value[id];
  await api('/schedules.php?path=' + encodeURIComponent(id), { method: 'PATCH', body: JSON.stringify(e) });
  await loadAll();
}
function demoMut(fn) { const d = demoLoad(); fn(d); demoSave(d); loadAllDemo(); }
function demoSaveDay(id) {
  const e = editDay.value[id];
  demoMut(d => {
    const day = d.days.find(x => x.id === id);
    if (!day) return;
    day.label = String(e.label).slice(0, 30);
    day.capacity = Number(e.capacity) || day.capacity;
    day.active = e.active ? 1 : 0;
    // El frontend suma los cupos de los bloques: reparte la capacidad del día (60 => 20+20+20).
    const act = (day.slots || []).filter(s => s.active !== 0);
    if (act.length) {
      const base = Math.floor(day.capacity / act.length);
      const rem = day.capacity - base * act.length;
      act.forEach((s, i) => { s.capacity = base + (i < rem ? 1 : 0); });
    }
  });
}
async function saveSlot(id, p) {
  error.value = '';
  if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(p.start) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(p.end) || p.end <= p.start) { error.value = 'Revisa que el inicio y fin sean horarios válidos y que el fin sea posterior.'; return; }
  if (Number(p.capacity) < 1 || Number(p.capacity) > 500) { error.value = 'Los cupos deben estar entre 1 y 500.'; return; }
  slotBusy.value = id;
  try {
    if (demoMode.value) { demoMut(d => { for (const day of d.days) { const s = day.slots.find(x => x.id === id); if (s) { s.start = p.start; s.end = p.end; s.capacity = Number(p.capacity); s.active = p.active ? 1 : 0; } } }); return; }
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
    if (demoMode.value) { demoMut(d => { const day = d.days.find(x => x.id === tid); if (day) day.slots.push({ id: 's' + Date.now(), start: n.start, end: n.end, capacity: Number(n.capacity), active: 1, reserved: 0 }); }); return; }
    await api('/schedules.php?path=' + encodeURIComponent(tid) + '/slots', { method: 'POST', body: JSON.stringify(n) });
    await loadAll();
  } catch (e) { error.value = e.message || 'No fue posible crear el bloque.'; }
  finally { slotBusy.value = ''; }
}
async function removeSlot(id) {
  if (pendingSlot.value !== id) { pendingSlot.value = id; return; }
  pendingSlot.value = ''; error.value = ''; slotBusy.value = id;
  try {
    if (demoMode.value) { demoMut(d => { for (const day of d.days) { const s = day.slots.find(x => x.id === id); if (s) s.active = 0; } }); return; }
    await api('/schedules.php?path=slots/' + encodeURIComponent(id), { method: 'DELETE' });
    await loadAll();
  } catch (e) { error.value = e.message || 'No fue posible desactivar el bloque.'; }
  finally { slotBusy.value = ''; }
}
async function loadReservations() {
  if (demoMode.value) {
    const d = demoLoad();
    let list = d.reservations.slice();
    if (filters.value.date) list = list.filter(r => r.reservation_date === filters.value.date);
    if (filters.value.status) list = list.filter(r => r.status === filters.value.status);
    if (filters.value.q) { const q = filters.value.q.toLowerCase(); list = list.filter(r => (r.reservation_code + r.first_name + r.last_name + r.whatsapp).toLowerCase().includes(q)); }
    reservations.value = list.slice(0, 200);
    return;
  }
  const p = new URLSearchParams();
  if (filters.value.date) p.set('date', filters.value.date);
  if (filters.value.status) p.set('status', filters.value.status);
  if (filters.value.q) p.set('q', filters.value.q);
  const x = await api('/admin_reservations.php?' + p.toString());
  reservations.value = x.reservations || [];
}
async function setStatus(id, status) {
  if (demoMode.value) { demoMut(d => { const r = d.reservations.find(x => x.id === id); if (r) r.status = status; }); return; }
  resBusy.value = true;
  try { await api('/admin_reservations.php?path=' + id + '/status', { method: 'PATCH', body: JSON.stringify({ status }) }); await loadReservations(); }
  catch (e) { error.value = e.message; }
  finally { resBusy.value = false; }
}
async function loadSettings() { const x = await api('/settings.php'); settings.value = x.settings || {}; }
async function saveSettings() {
  if (demoMode.value) { demoMut(d => { d.settings = Object.assign({}, d.settings, settings.value); settings.value = Object.assign({}, d.settings); }); settingsMsg.value = 'Guardado ✓ (demo)'; return; }
  settingsBusy.value = true; settingsMsg.value = '';
  try { await api('/settings.php', { method: 'PUT', body: JSON.stringify(settings.value) }); settingsMsg.value = 'Guardado ✓'; }
  catch (e) { settingsMsg.value = e.message; }
  finally { settingsBusy.value = false; }
}
function resetDemo() { demoReset(); try { sessionStorage.removeItem('vs_demo_admin'); } catch (e) {} loadAllDemo(); }
onMounted(async () => { await checkAuth(); if (authed.value) { if (demoMode.value) loadAllDemo(); else await loadAll(); } else loading.value = false; });
</script>
<template>
<div class="admin-wrap">
<header class="admin-top">
<div><a href="#eventos" class="admin-back">Volver al sitio</a>
<h1>Panel Admin - Vina Stage</h1>
<small v-if="admin">{{ admin.email }} - {{ admin.role }}</small><span v-if="demoMode" class="pill">DEMO</span></div>
<button v-if="authed" type="button" class="ghost-btn small" @click="doLogout">Salir</button>
<button v-if="demoMode" type="button" class="ghost-btn small" @click="resetDemo">Reiniciar demo</button>
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
<p class="admin-hint">Crea el admin con ADMIN_EMAIL=... ADMIN_PASS=... php database/create_admin.php</p>
<p v-if="demoOn" class="admin-demo">Demo disponible: {{ demoEmail }} / demo1234 <button type="button" class="ghost-btn small" @click="loginForm.email = demoEmail; loginForm.password = 'demo1234'">Autocompletar</button></p>
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
<div class="section-title">PROXIMAS 8 RESERVAS</div>
<div class="table-wrap"><table class="admin-table">
<thead><tr><th>Fecha</th><th>Hora</th><th>Nombre</th><th>Codigo</th><th>Estado</th></tr></thead>
<tbody><tr v-for="r in dash.next" :key="r.id"><td>{{ r.reservation_date }}</td><td><span class="bloque-tag">{{ bloqueNombre(r.start_time) || '—' }}</span><br><span class="muted">{{ r.start_time }} - {{ r.end_time }}</span></td><td>{{ r.first_name }} {{ r.last_name }}</td><td><code>{{ r.reservation_code }}</code></td><td><span :class="['pill', r.status]">{{ r.status }}</span></td></tr></tbody>
</table></div>
<p class="day-note">Los cambios en los bloques se guardan automáticamente al actualizar la página. Los cupos del día se reparten entre los bloques activos.</p>
</div>
</section>
<section v-if="authed && !loading && tab === 'days'" id="panel-days" class="admin-stack" role="tabpanel" aria-labelledby="tab-days">
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
<form class="admin-filters" aria-label="Filtros de reservas" @submit.prevent="loadReservations">
<label>Fecha<input v-model="filters.date" type="date"></label>
<label>Estado<select v-model="filters.status"><option value="">Todos</option><option value="confirmed">Confirmada</option><option value="attended">Asistió</option><option value="cancelled">Cancelada</option><option value="no_show">No show</option></select></label>
<label>Búsqueda<input v-model.trim="filters.q" placeholder="Código, nombre o WhatsApp"></label>
<button type="submit" class="ghost-btn small" :disabled="resBusy">Filtrar</button>
</form>
<div class="table-wrap"><table class="admin-table">
<thead><tr><th>Fecha</th><th>Hora</th><th>Nombre</th><th>WhatsApp</th><th>Código</th><th>Estado</th><th></th></tr></thead>
<tbody>
<tr v-for="(r,rIdx) in reservations" :key="r.id" :style="{'--i':rIdx}">
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

