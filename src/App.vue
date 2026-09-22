<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const API=import.meta.env.VITE_API_BASE_URL||'/api';
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

function startCarousel(){carouselTimer=setInterval(()=>carouselIndex.value=(carouselIndex.value+1)%events.length,4200)}
function stopCarousel(){if(carouselTimer){clearInterval(carouselTimer);carouselTimer=null}}
function setEvent(i){stopCarousel();carouselIndex.value=(carouselIndex.value+i)%events.length;startCarousel()}

const pad=n=>String(n).padStart(2,'0');
function iso(d){return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`}
function nextDays(){const out=[],now=new Date();now.setHours(12,0,0,0);for(let i=0;i<14&&out.length<5;i++){const d=new Date(now);d.setDate(now.getDate()+i);if(d.getDay()>=1&&d.getDay()<=5)out.push({date:iso(d),name:names[d.getDay()-1]})}return out}
async function load(){loading.value=true;error.value='';try{days.value=await Promise.all(nextDays().map(async d=>{const r=await fetch(`${API}/availability.php?date=${d.date}`);if(!r.ok)throw Error('No se pudo consultar disponibilidad');const x=await r.json();const slots=x.slots||[];return {...d,slots,used:slots.reduce((n,s)=>n+s.reserved,0),total:slots.reduce((n,s)=>n+s.capacity,0)}}));selectedDay.value=Math.max(0,days.value.findIndex(d=>d.slots.some(s=>s.available>0)))}catch(e){error.value=e.message;days.value=nextDays().map(d=>({...d,slots:[{slot_id:'demo',start_time:'14:00',end_time:'16:00',capacity:14,reserved:6,available:8},{slot_id:'demo2',start_time:'16:00',end_time:'18:00',capacity:14,reserved:9,available:5},{slot_id:'demo3',start_time:'18:00',end_time:'20:00',capacity:12,reserved:7,available:5}],used:22,total:40}));selectedDay.value=0;selectedSlot.value=0}finally{loading.value=false}}
const day=computed(()=>days.value[selectedDay.value]||{name:'',slots:[],used:0,total:0});
const slot=computed(()=>day.value.slots[selectedSlot.value]);
function pct(d){return d.total?Math.round(d.used/d.total*100):100}
async function reserve(){if(!form.value.nombre||!form.value.apellido||!form.value.whatsapp||!slot.value)return;saving.value=true;error.value='';try{const r=await fetch(`${API}/reservations.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({date:day.value.date,slotId:slot.value.slot_id,firstName:form.value.nombre,lastName:form.value.apellido,whatsapp:form.value.whatsapp,email:form.value.email,whatsappConsent:form.value.consent})});const x=await r.json();if(!r.ok)throw Error(x.error||'No fue posible crear la reserva');confirmation.value=x.reservation.reservation_code;form.value={nombre:'',apellido:'',whatsapp:'',email:'',consent:true};await load()}catch(e){error.value=e.message}finally{saving.value=false}}
onMounted(()=>{load();startCarousel()});
onUnmounted(stopCarousel);
</script>

<template>
<div class="site">
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
  <div class="section-head"><div><p class="eyebrow">CARTELERA</p><h2>LO QUE PASA<br><strong>EN STAGE</strong></h2></div><div class="carousel-controls"><button @click="setEvent(-1)">←</button><button @click="setEvent(1)">→</button></div></div>
  <div class="event-stage" @mouseenter="stopCarousel" @mouseleave="startCarousel">
    <div v-for="(event,i) in activeEvents" :key="event.id+'-'+i" :class="['event-card',{front:i===0},event.tone]" :style="{ '--stack': i }">
      <div class="flyer-noise"></div><span class="flyer-mark">{{event.mark}}</span><div class="flyer-content"><small>{{event.date}}</small><h3>{{event.title}}</h3><p>{{event.type}}</p></div><div class="flyer-footer">VIÑA STAGE · AV. VALPARAÍSO 65</div>
    </div>
  </div>
  <div class="event-meta"><div><span class="live-dot"></span> CARTELERA EN MOVIMIENTO</div><div class="dots"><button v-for="(_,i) in events" :key="i" :class="{active:i===carouselIndex}" @click="setEvent(i-carouselIndex)">0{{i+1}}</button></div></div>
</section>

<section class="experience-section">
  <div class="experience-copy"><p class="eyebrow">LA EXPERIENCIA</p><h2>DONDE LA NOCHE<br><strong>TOMA EL ESCENARIO.</strong></h2><p>Un punto de encuentro en pleno centro de Viña del Mar para disfrutar música, eventos y buena compañía.</p><a href="#contacto" class="text-link">CONÓCENOS <span>→</span></a></div>
  <div class="experience-grid"><article><b>01</b><span>♪</span><h3>MÚSICA</h3><p>Sonido y escenario para noches en vivo.</p></article><article><b>02</b><span>✦</span><h3>EVENTOS</h3><p>Una cartelera que cambia y se mueve.</p></article><article><b>03</b><span>◉</span><h3>ENCUENTRO</h3><p>Un espacio para venir, quedarse y compartir.</p></article><article><b>04</b><span>↗</span><h3>CENTRO</h3><p>Av. Valparaíso 65, Viña del Mar.</p></article></div>
</section>

<section id="reservas" class="reservation-section">
  <div class="reservation-intro"><p class="eyebrow">RESERVAS</p><h2>RESERVA<br><strong>TU CUPO</strong></h2><p>Selecciona el día y horario, completa tus datos y asegura tu lugar.</p></div>
  <section class="panel day-panel"><div class="section-title">SELECCIONA UN DÍA</div><div class="days"><button v-for="(d,i) in days" :key="d.date" :class="['day',{active:i===selectedDay}]" @click="selectedDay=i;selectedSlot=0"><span class="day-name">{{d.name}}</span><span class="ring" :style="{'--pct':pct(d)+'%'}"><span class="ring-content">{{d.used}}/{{d.total}}<small>CUPOS</small></span></span></button></div></section>
  <section class="panel schedule-panel"><div class="schedule-heading"><h2>HORARIOS DISPONIBLES <span>•</span> <strong>{{day.name}}</strong></h2><div>Total del día: <b>{{day.used}}/{{day.total}}</b></div></div><div class="slots"><button v-for="(s,i) in day.slots" :key="s.slot_id" :disabled="s.available<=0" :class="['slot',{active:i===selectedSlot}]" @click="selectedSlot=i"><strong>{{s.start_time}} - {{s.end_time}}</strong><span>{{s.available}} cupos</span><span v-if="i===selectedSlot" class="tick">✓</span></button></div></section>
  <section class="panel form-panel"><div class="section-title">COMPLETA TUS DATOS</div><form @submit.prevent="reserve"><div class="form-grid"><label class="field"><span>♙</span><input v-model.trim="form.nombre" required placeholder="Nombre *"></label><label class="field"><span>♙</span><input v-model.trim="form.apellido" required placeholder="Apellido *"></label><label class="field"><span>⌕</span><input v-model.trim="form.whatsapp" required placeholder="WhatsApp *"></label><label class="field"><span>✉</span><input v-model.trim="form.email" type="email" placeholder="Email (opcional)"></label></div><label class="consent"><input v-model="form.consent" type="checkbox"><span>Acepto recibir información de Viña Stage por WhatsApp.</span></label><button class="reserve-btn" :disabled="saving || loading || !slot"><span>▣</span> {{saving?'RESERVANDO...':'RESERVAR CUPO'}}</button></form></section>
  <section v-if="confirmation" class="confirmation"><div class="check">✓</div><div><strong>¡Reserva confirmada!</strong><span>Código de reserva: <b>{{confirmation}}</b></span></div></section>
  <div v-if="error" class="notice">{{error}} <small v-if="API==='/api'">La vista continúa en modo demostración para que puedas revisar el diseño.</small></div>
</section>

<section id="contacto" class="contact-section">
  <div><p class="eyebrow">CONTACTO</p><h2>ENCUÉNTRANOS<br><strong>EN VIÑA.</strong></h2><p>Av. Valparaíso 65<br>Viña del Mar, Chile</p><a class="primary-btn" href="https://www.google.com/maps/search/?api=1&query=Av.+Valpara%C3%ADso+65,+Vi%C3%B1a+del+Mar" target="_blank" rel="noreferrer">VER EN MAPA ↗</a></div>
  <div class="contact-card"><span class="contact-icon">⌖</span><small>UBICACIÓN</small><strong>AV. VALPARAÍSO 65</strong><p>En pleno centro de Viña del Mar.</p><div class="map-lines"></div></div>
</section>
</main>

<footer><div class="footer-brand"><span class="crown">♛</span><strong>VIÑA<br>STAGE</strong><small>MÚSICA • AMIGOS • BUENA ONDA</small></div><div>Av. Valparaíso 65 · Viña del Mar</div></footer>
</div>
</template>