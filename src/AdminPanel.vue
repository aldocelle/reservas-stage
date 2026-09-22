<script setup>
import { onMounted, ref } from 'vue';
import { DEMO_EMAIL, DEMO_ON, demoCheck, demoLoad, demoReset, demoSave } from './demo';
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
const settings = ref({});
const settingsBusy = ref(false), settingsMsg = ref('');
const demoMode = ref(false);
const demoEmail = DEMO_EMAIL;
const demoOn = DEMO_ON;
const tabs = [['overview','Resumen'],['days','Días y horarios'],['bookings','Reservas'],['config','Config']];
async function api(path, opts) {
  const r = await fetch(API + path, Object.assign({ credentials: 'include', headers: { 'Content-Type': 'application/json' } }, opts || {}));
  const x = await r.json().catch(() => ({}));
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
    for (const day of days.value) { editDay.value[day.id] = { label: day.label, capacity: day.capacity, active: !!day.active }; newSlots.value[day.id] = { start: '', end: '', capacity: 14 }; }
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
      newSlots.value[d.id] = { start: '', end: '', capacity: 14 };
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
  demoMut(d => { const day = d.days.find(x => x.id === id); if (day) { day.label = String(e.label).slice(0, 30); day.capacity = e.capacity; day.active = e.active ? 1 : 0; } });
}
async function saveSlot(id, p) {
  if (demoMode.value) { demoMut(d => { for (const day of d.days) { const s = day.slots.find(x => x.id === id); if (s) { s.start = p.start; s.end = p.end; s.capacity = p.capacity; s.active = p.active ? 1 : 0; } } }); return; }
  await api('/schedules.php?path=slots/' + encodeURIComponent(id), { method: 'PATCH', body: JSON.stringify(p) });
  await loadAll();
}
async function createSlot(tid) {
  const n = newSlots.value[tid];
  if (!n.start || !n.end) { error.value = 'Completa inicio y fin (HH:MM)'; return; }
  if (demoMode.value) { demoMut(d => { const day = d.days.find(x => x.id === tid); if (day) day.slots.push({ id: 's' + Date.now(), start: n.start, end: n.end, capacity: n.capacity || 14, active: 1, reserved: 0 }); }); return; }
  await api('/schedules.php?path=' + encodeURIComponent(tid) + '/slots', { method: 'POST', body: JSON.stringify(n) });
  await loadAll();
}
async function removeSlot(id) {
  if (!confirm('¿Desactivar este horario?')) return;
  if (demoMode.value) { demoMut(d => { for (const day of d.days) { const s = day.slots.find(x => x.id === id); if (s) s.active = 0; } }); return; }
  await api('/schedules.php?path=slots/' + encodeURIComponent(id), { method: 'DELETE' });
  await loadAll();
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
<div><a href="#inicio" class="admin-back">Volver al sitio</a>
<h1>Panel Admin - Vina Stage</h1>
<small v-if="admin">{{ admin.email }} - {{ admin.role }}</small><span v-if="demoMode" class="pill">DEMO</span></div>
<button v-if="authed" type="button" class="ghost-btn small" @click="doLogout">Salir</button>
<button v-if="demoMode" type="button" class="ghost-btn small" @click="resetDemo">Reiniciar demo</button>
</header>
<section v-if="!authed" class="panel form-panel admin-login">
<div class="section-title">INGRESO ADMINISTRADOR</div>
<form @submit.prevent="doLogin">
<div class="form-grid">
<label class="field"><span aria-hidden="true">E</span><input v-model.trim="loginForm.email" type="email" required autocomplete="username" placeholder="Email admin *"></label>
<label class="field"><span aria-hidden="true">*</span><input v-model="loginForm.password" type="password" required autocomplete="current-password" placeholder="Contrasena *"></label>
</div>
<p v-if="loginError" class="admin-error" role="alert">{{ loginError }}</p>
<button class="reserve-btn" type="submit" :disabled="loginBusy">{{ loginBusy ? 'INGRESANDO...' : 'INGRESAR' }}</button>
</form>
<p class="admin-hint">Crea el admin con ADMIN_EMAIL=... ADMIN_PASS=... php database/create_admin.php</p>
<p v-if="demoOn" class="admin-demo">Demo disponible: {{ demoEmail }} / demo1234 <button type="button" class="ghost-btn small" @click="loginForm.email = demoEmail; loginForm.password = 'demo1234'">Autocompletar</button></p>
</section>
<template v-else>
<nav class="admin-tabs" role="tablist">
<button v-for="t in tabs" :key="t[0]" type="button" role="tab" :class="['admin-tab', { active: tab === t[0] }]" @click="tab = t[0]">{{ t[1] }}</button>
</nav>
<p v-if="error" class="notice" role="alert">{{ error }}</p>
<div v-if="loading" class="loading-row">Cargando panel...</div>
<section v-if="authed && !loading && tab === 'overview'" class="admin-grid">
<div class="panel stat"><b>{{ (dash.totals && dash.totals.today) || 0 }}</b><span>Reservas hoy</span></div>
<div class="panel stat"><b>{{ (dash.totals && dash.totals.upcoming) || 0 }}</b><span>Proximas vigentes</span></div>
<div class="panel stat"><b>{{ (dash.totals && dash.totals.total) || 0 }}</b><span>Total historico</span></div>
<div class="panel stat"><b>{{ (dash.totals && dash.totals.cancelled) || 0 }}</b><span>Canceladas</span></div>
<div class="panel wide">
<div class="section-title">PROXIMAS 8 RESERVAS</div>
<div class="table-wrap"><table class="admin-table">
<thead><tr><th>Fecha</th><th>Hora</th><th>Nombre</th><th>Codigo</th><th>Estado</th></tr></thead>
<tbody><tr v-for="r in dash.next" :key="r.id"><td>{{ r.reservation_date }}</td><td>{{ r.start_time }}-{{ r.end_time }}</td><td>{{ r.first_name }} {{ r.last_name }}</td><td><code>{{ r.reservation_code }}</code></td><td><span :class="['pill', r.status]">{{ r.status }}</span></td></tr></tbody>
</table></div>
</div>
</section>
<section v-if="authed && !loading && tab === 'days'" class="admin-stack">
<div v-for="d in days" :key="d.id" class="panel day-card">
<div class="day-card-head">
<strong>{{ d.label }} <small>(dia {{ d.weekday }})</small></strong>
<label class="switch"><input v-model="editDay[d.id].active" type="checkbox"><span>Activo</span></label>
</div>
<div class="day-edit">
<label>Nombre<input v-model="editDay[d.id].label" maxlength="30"></label>
<label>Capacidad dia<input v-model.number="editDay[d.id].capacity" type="number" min="1" max="500"></label>
<button type="button" class="ghost-btn small" @click="saveDay(d.id)">Guardar dia</button>
</div>
<div class="table-wrap"><table class="admin-table">
<thead><tr><th>Inicio</th><th>Fin</th><th>Cupos</th><th>Activo</th><th></th></tr></thead>
<tbody>
<tr v-for="s in d.slots" :key="s.id">
<td><input v-model="s.start" class="mini" maxlength="5" placeholder="14:00"></td>
<td><input v-model="s.end" class="mini" maxlength="5" placeholder="16:00"></td>
<td><input v-model.number="s.capacity" class="mini" type="number" min="1" max="500"></td>
<td><input v-model="s.active" type="checkbox"></td>
<td class="row-actions"><button type="button" class="ghost-btn small" @click="saveSlot(s.id, { start: s.start, end: s.end, capacity: s.capacity, active: !!s.active })">Guardar</button><button type="button" class="danger small" @click="removeSlot(s.id)">Off</button></td>
</tr>
</tbody>
</table></div>
<div class="new-slot">
<input v-model="newSlots[d.id].start" class="mini" maxlength="5" placeholder="20:00">
<input v-model="newSlots[d.id].end" class="mini" maxlength="5" placeholder="22:00">
<input v-model.number="newSlots[d.id].capacity" class="mini" type="number" min="1" max="500" placeholder="Cupos">
<button type="button" class="ghost-btn small" @click="createSlot(d.id)">+ Horario</button>
</div>
</div>
</section>
<section v-if="authed && !loading && tab === 'bookings'" class="panel wide">
<div class="section-title">RESERVAS</div>
<form class="admin-filters" @submit.prevent="loadReservations">
<input v-model="filters.date" type="date">
<select v-model="filters.status"><option value="">Todos</option><option value="confirmed">confirmed</option><option value="attended">attended</option><option value="cancelled">cancelled</option><option value="no_show">no_show</option></select>
<input v-model.trim="filters.q" placeholder="Codigo, nombre o whatsapp">
<button type="submit" class="ghost-btn small" :disabled="resBusy">Filtrar</button>
</form>
<div class="table-wrap"><table class="admin-table">
<thead><tr><th>Fecha</th><th>Hora</th><th>Nombre</th><th>WhatsApp</th><th>Codigo</th><th>Estado</th><th></th></tr></thead>
<tbody>
<tr v-for="r in reservations" :key="r.id">
<td>{{ r.reservation_date }}</td><td>{{ r.start_time }}-{{ r.end_time }}</td>
<td>{{ r.first_name }} {{ r.last_name }}</td><td>{{ r.whatsapp }}</td>
<td><code>{{ r.reservation_code }}</code></td>
<td><span :class="['pill', r.status]">{{ r.status }}</span></td>
<td class="row-actions">
<button type="button" class="ghost-btn small" :disabled="resBusy" @click="setStatus(r.id, 'attended')">Asistio</button>
<button type="button" class="ghost-btn small" :disabled="resBusy" @click="setStatus(r.id, 'confirmed')">Confirmar</button>
<button type="button" class="danger small" :disabled="resBusy" @click="setStatus(r.id, 'cancelled')">Cancelar</button>
</td>
</tr>
</tbody>
</table></div>
<p v-if="!reservations.length" class="empty-note">Sin reservas para esos filtros.</p>
</section>
<section v-if="authed && !loading && tab === 'config'" class="panel form-panel">
<div class="section-title">CONFIGURACION DEL SITIO</div>
<div class="admin-form">
<label>Nombre del sitio<input v-model="settings.site_name" maxlength="80"></label>
<label>Titulo hero<input v-model="settings.hero_title" maxlength="80"></label>
<label>Subtitulo hero<textarea v-model="settings.hero_subtitle" rows="2" maxlength="500"></textarea></label>
<label>Direccion<input v-model="settings.address" maxlength="200"></label>
<label>WhatsApp contacto<input v-model="settings.whatsapp" maxlength="30"></label>
<label>Instagram<input v-model="settings.instagram" maxlength="120"></label>
<label class="switch"><input v-model="settings.booking_enabled" type="checkbox" true-value="1" false-value="0"><span>Reservas habilitadas</span></label>
<label>Aviso de reservas<textarea v-model="settings.booking_notice" rows="2" maxlength="500" placeholder="Ej: Esta semana solo jueves y viernes"></textarea></label>
</div>
<p v-if="settingsMsg" class="admin-ok" role="status">{{ settingsMsg }}</p>
<button type="button" class="reserve-btn" :disabled="settingsBusy" @click="saveSettings">{{ settingsBusy ? 'GUARDANDO...' : 'GUARDAR CONFIG' }}</button>
</section>
</template>
</div>
</template>

