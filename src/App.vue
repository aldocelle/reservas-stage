<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AdminPanel from './AdminPanel.vue';

const API=import.meta.env.VITE_API_BASE_URL||'/api';
const hashTick=ref(0);
const route=computed(()=>{void hashTick.value;return window.location.hash||'#inicio'});
const showAdmin=computed(()=>route.value.startsWith('#/admin'));
function onHash(){hashTick.value++}
const siteSettings=ref({site_name:'Viña Stage',hero_title:'VIÑA STAGE',booking_notice:''});
async function loadSettings(){try{const r=await fetch(`${API}/public_settings.php`);if(!r.ok)return;const x=await r.json();siteSettings.value={...siteSettings.value,...(x.settings||{})}}catch(e){}}
const bookingOpen=computed(()=>siteSettings.value.booking_enabled!=='0');

const names=['LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES'];
const days=ref([]),selectedDay=ref(0),selectedSlot=ref(0),loading=ref(true),saving=ref(false),error=ref('');
const form=ref({nombre:'',apellido:'',whatsapp:'',email:'',consent:true}),confirmation=ref('');
const carouselIndex=ref(0);
let carouselTimer=null;

const events=[
  {id:1,date:'PRÓXIMAMENTE',title:'LIVE SESSION',type:'MÚSICA EN VIVO',tone:'lime',mark:'LIVE'},
  {id:2,date:'PRÓXIMAMENTE',title:'STAGE COMEDY',type:'COMEDIA EN VIVO',tone:'orange',mark:'COMEDY'},
  {id:3,date:'PRÓXIMAMENTE',title:'NIGHT STAGE',type:'NOCHE DE EVENTOS',tone:'violet',mark:'NIGHT'},
  {id:4,date:'PRÓXIMAMENTE',title:'STAGE UP',type:'EXPERIENCIAS EN VIVO',tone:'red',mark:'STAGE'}
];

const activeEvents=computed(()=>events.map((_,i)=>events[(carouselIndex.value+i)%events.length]));

function startCarousel(){if(carouselTimer)return;carouselTimer=setInterval(()=>{carouselIndex.value=(carouselIndex.value+1)%events.length},4200)}
function stopCarousel(){if(carouselTimer){clearInterval(carouselTimer);carouselTimer=null}}
function setEvent(i){stopCarousel();carouselIndex.value=(carouselIndex.value+i+events.length)%events.length;startCarousel()}

const pad=n=>String(n).padStart(2,'0');
function iso(d){return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`}
function nextDays(){const out=[],now=new Date();now.setHours(12,0,0,0);for(let i=0;i<14&&out.length<5;i++){const d=new Date(now);d.setDate(now.getDate()+i);if(d.getDay()>=1&&d.getDay()<=5)out.push({date:iso(d),name:names[d.getDay()-1]})}return out}
function selectDay(i){selectedDay.value=i;selectedSlot.value=0;confirmation.value=''}
function selectSlot(i){selectedSlot.value=i;confirmation.value=''}
async function load(){loading.value=true;error.value='';try{days.value=await Promise.all(nextDays().map(async d=>{const r=await fetch(`${API}/availability.php?date=${d.date}`);if(!r.ok)throw Error('No se pudo consultar disponibilidad');const x=await r.json();const slots=x.slots||[];return {...d,slots,used:slots.reduce((n,s)=>n+(s.reserved||0),0),total:slots.reduce((n,s)=>n+(s.capacity||0),0)}}));const firstAvailable=days.value.findIndex(d=>d.slots.some(s=>s.available>0));selectedDay.value=firstAvailable>=0?firstAvailable:0;selectedSlot.value=0}catch(e){error.value=e.message||'No se pudo consultar disponibilidad';days.value=nextDays().map(d=>({...d,slots:[{slot_id:'demo',start_time:'14:00',end_time:'16:00',capacity:14,reserved:6,available:8},{slot_id:'demo2',start_time:'16:00',end_time:'18:00',capacity:14,reserved:9,available:5},{slot_id:'demo3',start_time:'18:00',end_time:'20:00',capacity:12,reserved:7,available:5}],used:22,total:40}));selectedDay.value=0;selectedSlot.value=0}finally{loading.value=false}}
const day=computed(()=>days.value[selectedDay.value]||{name:'',slots:[],used:0,total:0});
const slot=computed(()=>day.value.slots[selectedSlot.value]);
function pct(d){return d.total?Math.round(d.used/d.total*100):0}
const hasAvailability=computed(()=>days.value.some(d=>d.slots.some(s=>s.available>0)));
async function reserve(){if(saving.value||loading.value)return;if(!form.value.nombre||!form.value.apellido||!form.value.whatsapp||!slot.value)return;const digits=form.value.whatsapp.replace(/[\s\-.()]/g,'');if(!/^\+?\d{8,15}$/.test(digits)){error.value='WhatsApp inválido (8 a 15 dígitos)';return}if(form.value.email&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email.trim())){error.value='Email inválido';return}saving.value=true;error.value='';confirmation.value='';try{const r=await fetch(`${API}/reservations.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({date:day.value.date,slotId:slot.value.slot_id,firstName:form.value.nombre,lastName:form.value.apellido,whatsapp:form.value.whatsapp,email:form.value.email,whatsappConsent:form.value.consent})});const x=await r.json().catch(()=>({}));if(!r.ok)throw Error(x.error||'No fue posible crear la reserva');confirmation.value=x.reservation.reservation_code;form.value={nombre:'',apellido:'',whatsapp:'',email:'',consent:true};await load()}catch(e){error.value=e.message||'No fue posible crear la reserva'}finally{saving.value=false}}
onMounted(()=>{load();loadSettings();startCarousel();window.addEventListener('hashchange',onHash)});
onUnmounted(()=>{stopCarousel();window.removeEventListener('hashchange',onHash)});
</script>

<template>
<AdminPanel v-if="showAdmin" />
<div v-else class="site">
<header class="topbar">
  <a class="brand" href="#inicio" aria-label="Viña Stage inicio"><span class="crown">♛</span><span>VIÑA<br>STAGE</span></a>
  <nav class="nav"><a href="#inicio">INICIO</a><a href="#eventos">EVENTOS</a><a href="#reservas">RESERVAS</a><a href="#contacto">CONTACTO</a></nav>
  <a class="top-cta" href="#reservas">RESERVAR</a>
  <button class="menu" aria-label="Menú"><span/><span/><span/></button>
</header>

<main>
<section id="inicio" class="hero landing-hero">
  <div class="hero-glow"></div><div class="hero-content">
    <p class="eyebrow">AV. VALPARAÍSO 65 · VIÑA DEL MAR</p>
    <h1>VIÑA<br><strong>STAGE</strong></h1>
    <p class="hero-copy">Música, eventos y noches para vivir Viña desde el centro de la ciudad.</p>
    <div class="hero-actions"><a class="primary-btn" href="#eventos">VER EVENTOS <span>↘</span></a><a class="ghost-btn" href="#reservas">RESERVAR CUPO</a></div>
  </div>
  <div class="hero-stamp"><span>LIVE</span><strong>STAGE</strong><small>VIÑA DEL MAR</small></div>
</section>

<section id="eventos" class="events-section">
  <div class="section-head"><div><p class="eyebrow">CARTELERA</p><h2>LO QUE PASA<br><strong>EN STAGE</strong></h2></div><div class="carousel-controls"><button type="button" aria-label="Evento anterior" @click="setEvent(-1)">←</button><button type="button" aria-label="Evento siguiente" @click="setEvent(1)">→</button></div></div>
  <div class="event-stage" @mouseenter="stopCarousel" @mouseleave="startCarousel">
    <div v-for="(event,i) in activeEvents" :key="event.id+'-'+i" :class="['event-card',{front:i===0},event.tone]" :style="{ '--stack': i }">
      <div class="flyer-noise"></div><span class="flyer-mark">{{event.mark}}</span><div class="flyer-content"><small>{{event.date}}</small><h3>{{event.title}}</h3><p>{{event.type}}</p></div><div class="flyer-footer">VIÑA STAGE · AV. VALPARAÍSO 65</div>
    </div>
  </div>
  <div class="event-meta"><div><span class="live-dot"></span> CARTELERA EN MOVIMIENTO</div><div class="dots"><button v-for="(_,i) in events" :key="i" type="button" :class="{active:i===carouselIndex}" :aria-label="'Ir al evento '+(i+1)" @click="setEvent(i-carouselIndex)">0{{i+1}}</button></div></div>
</section>

<section class="experience-section">
  <div class="experience-copy"><p class="eyebrow">LA EXPERIENCIA</p><h2>DONDE LA NOCHE<br><strong>TOMA EL ESCENARIO.</strong></h2><p>Un punto de encuentro en pleno centro de Viña del Mar para disfrutar música, eventos y buena compañía.</p><a href="#contacto" class="text-link">CONÓCENOS <span>→</span></a></div>
  <div class="experience-grid"><article><b>01</b><span>♪</span><h3>MÚSICA</h3><p>Sonido y escenario para noches en vivo.</p></article><article><b>02</b><span>✦</span><h3>EVENTOS</h3><p>Una cartelera que cambia y se mueve.</p></article><article><b>03</b><span>◉</span><h3>ENCUENTRO</h3><p>Un espacio para venir, quedarse y compartir.</p></article><article><b>04</b><span>↗</span><h3>CENTRO</h3><p>Av. Valparaíso 65, Viña del Mar.</p></article></div>
</section>

<section id="reservas" class="reservation-section">
  <div class="reservation-intro"><p class="eyebrow">RESERVAS</p><h2>RESERVA<br><strong>TU CUPO</strong></h2><p>Selecciona el día y horario, completa tus datos y asegura tu lugar.</p><p v-if="siteSettings.booking_notice" class="booking-notice">{{siteSettings.booking_notice}}</p></div>
  <div v-if="!bookingOpen" class="notice" role="alert">Reservas pausadas por el momento. {{siteSettings.booking_notice}}</div>
  <template v-else>
  <section class="panel day-panel"><div class="section-title">SELECCIONA UN DÍA</div><div v-if="loading" class="loading-row">Consultando disponibilidad…</div><div v-else class="days"><button v-for="(d,i) in days" :key="d.date" type="button" :class="['day',{active:i===selectedDay}]" :aria-pressed="i===selectedDay" @click="selectDay(i)"><span class="day-name">{{d.name}}</span><small class="day-date">{{d.date}}</small><span class="ring" :style="{'--pct':pct(d)+'%'}"><span class="ring-content">{{d.used}}/{{d.total}}<small>CUPOS</small></span></span></button></div><p v-if="!loading && !hasAvailability" class="empty-note">Sin cupos disponibles en los próximos días. Intenta más tarde.</p></section>
  <section class="panel schedule-panel"><div class="schedule-heading"><h2>HORARIOS DISPONIBLES <span>•</span> <strong>{{day.name}}</strong></h2><div>Total del día: <b>{{day.used}}/{{day.total}}</b></div></div><div v-if="loading" class="loading-row">Cargando horarios…</div><div v-else-if="!day.slots.length" class="empty-note">Sin horarios publicados para este día.</div><div v-else class="slots"><button v-for="(s,i) in day.slots" :key="s.slot_id" type="button" :disabled="s.available<=0" :class="['slot',{active:i===selectedSlot}]" :aria-pressed="i===selectedSlot" @click="selectSlot(i)"><strong>{{s.start_time}} - {{s.end_time}}</strong><span>{{s.available}} cupos</span><span v-if="i===selectedSlot" class="tick">✓</span></button></div></section>
  <section class="panel form-panel"><div class="section-title">COMPLETA TUS DATOS</div><form @submit.prevent="reserve" novalidate><div class="form-grid"><label class="field"><span aria-hidden="true">♙</span><input v-model.trim="form.nombre" required minlength="2" maxlength="80" autocomplete="given-name" placeholder="Nombre *"></label><label class="field"><span aria-hidden="true">♙</span><input v-model.trim="form.apellido" required minlength="2" maxlength="80" autocomplete="family-name" placeholder="Apellido *"></label><label class="field"><span aria-hidden="true">⌕</span><input v-model.trim="form.whatsapp" required inputmode="tel" autocomplete="tel" maxlength="16" placeholder="WhatsApp *"></label><label class="field"><span aria-hidden="true">✉</span><input v-model.trim="form.email" type="email" autocomplete="email" maxlength="160" placeholder="Email (opcional)"></label></div><label class="consent"><input v-model="form.consent" type="checkbox"><span>Acepto recibir información de Viña Stage por WhatsApp.</span></label><button class="reserve-btn" type="submit" :disabled="saving || loading || !slot || (slot && slot.available<=0)"><span aria-hidden="true">▣</span> {{saving?'RESERVANDO...':'RESERVAR CUPO'}}</button></form></section>
  <section v-if="confirmation" class="confirmation" role="status"><div class="check">✓</div><div><strong>¡Reserva confirmada!</strong><span>Código de reserva: <b>{{confirmation}}</b></span></div></section>
  <div v-if="error" class="notice" role="alert">{{error}} <small v-if="API==='/api'">La vista continúa en modo demostración para que puedas revisar el diseño.</small></div>
  </template>
</section>

<section id="contacto" class="contact-section">
  <div><p class="eyebrow">CONTACTO</p><h2>ENCUÉNTRANOS<br><strong>EN VIÑA.</strong></h2><p>Av. Valparaíso 65<br>Viña del Mar, Chile</p><a class="primary-btn" href="https://www.google.com/maps/search/?api=1&query=Av.+Valpara%C3%ADso+65,+Vi%C3%B1a+del+Mar" target="_blank" rel="noreferrer">VER EN MAPA ↗</a></div>
  <div class="contact-card"><span class="contact-icon">⌖</span><small>UBICACIÓN</small><strong>AV. VALPARAÍSO 65</strong><p>En pleno centro de Viña del Mar.</p><div class="map-lines"></div></div>
</section>
</main>

<footer><div class="footer-brand"><span class="crown">♛</span><strong>VIÑA<br>STAGE</strong><small>MÚSICA • AMIGOS • BUENA ONDA</small></div><div>Av. Valparaíso 65 · Viña del Mar · <a href="#/admin" class="admin-link">Admin</a></div></footer>
</div>
</template>