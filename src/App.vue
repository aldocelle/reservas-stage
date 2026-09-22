<script setup>
import {computed,onMounted,ref} from 'vue';

const API=import.meta.env.VITE_API_BASE_URL||'/api';
const names=['LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES'];
const days=ref([]),selectedDay=ref(0),selectedSlot=ref(0),loading=ref(true),saving=ref(false),error=ref('');
const form=ref({nombre:'',apellido:'',whatsapp:'',email:'',consent:true}),confirmation=ref('');
const pad=n=>String(n).padStart(2,'0');
function iso(d){return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`}
function nextDays(){const out=[],now=new Date();now.setHours(12,0,0,0);for(let i=0;i<14&&out.length<5;i++){const d=new Date(now);d.setDate(now.getDate()+i);if(d.getDay()>=1&&d.getDay()<=5)out.push({date:iso(d),name:names[d.getDay()-1]})}return out}
async function load(){loading.value=true;error.value='';try{days.value=await Promise.all(nextDays().map(async d=>{const r=await fetch(`${API}/availability.php?date=${d.date}`);if(!r.ok)throw Error('No se pudo consultar disponibilidad');const x=await r.json();const slots=x.slots||[];return {...d,slots,used:slots.reduce((n,s)=>n+s.reserved,0),total:slots.reduce((n,s)=>n+s.capacity,0)}}));selectedDay.value=Math.max(0,days.value.findIndex(d=>d.slots.some(s=>s.available>0)));selectedSlot.value=0}catch(e){error.value=e.message;days.value=nextDays().map(d=>({...d,slots:[{slot_id:'demo',start_time:'14:00',end_time:'16:00',capacity:14,reserved:6,available:8},{slot_id:'demo2',start_time:'16:00',end_time:'18:00',capacity:14,reserved:9,available:5},{slot_id:'demo3',start_time:'18:00',end_time:'20:00',capacity:12,reserved:7,available:5}],used:22,total:40}));selectedDay.value=0;selectedSlot.value=0}finally{loading.value=false}}
const day=computed(()=>days.value[selectedDay.value]||{name:'',slots:[],used:0,total:0});
const slot=computed(()=>day.value.slots[selectedSlot.value]);
function pct(d){return d.total?Math.round(d.used/d.total*100):100}
async function reserve(){if(!form.value.nombre||!form.value.apellido||!form.value.whatsapp)return; saving.value=true;error.value='';try{const r=await fetch(`${API}/reservations.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({date:day.value.date,slotId:slot.value.slot_id,firstName:form.value.nombre,lastName:form.value.apellido,whatsapp:form.value.whatsapp,email:form.value.email,whatsappConsent:form.value.consent})});const x=await r.json();if(!r.ok)throw Error(x.error||'No fue posible crear la reserva');confirmation.value=x.reservation.reservation_code;form.value={nombre:'',apellido:'',whatsapp:'',email:'',consent:true};await load()}catch(e){error.value=e.message}finally{saving.value=false}}
onMounted(load);
</script>
<template>
<div class="site">
<header class="topbar"><a class="brand"><span class="crown">♛</span><span>VIÑA<br>STAGE</span></a><div class="tagline">MÚSICA <i>•</i> AMIGOS <i>•</i> BUENA ONDA</div><button class="menu"><span/><span/><span/></button></header>
<main>
<section class="hero"><div class="hero-content"><p class="eyebrow">RESERVAS</p><h1>RESERVA<br><strong>TU CUPO</strong></h1><p>Selecciona el día y horario, completa tus datos<br class="desktop"> y asegura tu lugar.</p></div></section>
<section class="panel day-panel"><div class="section-title">SELECCIONA UN DÍA</div><div class="days"><button v-for="(d,i) in days" :key="d.date" :class="['day',{active:i===selectedDay}]" @click="selectedDay=i;selectedSlot=0"><span class="day-name">{{d.name}}</span><span class="ring" :style="{'--pct':pct(d)+'%'}"><span class="ring-content">{{d.used}}/{{d.total}}<small>CUPOS</small></span></span></button></div></section>
<section class="panel schedule-panel"><div class="schedule-heading"><h2>HORARIOS DISPONIBLES <span>•</span> <strong>{{day.name}}</strong></h2><div>Total del día: <b>{{day.used}}/{{day.total}}</b></div></div><div class="slots"><button v-for="(s,i) in day.slots" :key="s.slot_id" :disabled="s.available<=0" :class="['slot',{active:i===selectedSlot}]" @click="selectedSlot=i"><strong>{{s.start_time}} - {{s.end_time}}</strong><span>{{s.available}} cupos</span><span v-if="i===selectedSlot" class="tick">✓</span></button></div></section>
<section class="panel form-panel"><div class="section-title">COMPLETA TUS DATOS</div><form @submit.prevent="reserve"><div class="form-grid"><label class="field"><span>♙</span><input v-model.trim="form.nombre" required placeholder="Nombre *"></label><label class="field"><span>♙</span><input v-model.trim="form.apellido" required placeholder="Apellido *"></label><label class="field"><span>⌕</span><input v-model.trim="form.whatsapp" required placeholder="WhatsApp *"></label><label class="field"><span>✉</span><input v-model.trim="form.email" type="email" placeholder="Email (opcional)"></label></div><label class="consent"><input v-model="form.consent" type="checkbox"><span>Acepto recibir información de Viña Stage por WhatsApp.</span></label><button class="reserve-btn" :disabled="saving || loading || !slot"><span>▣</span> {{saving?'RESERVANDO...':'RESERVAR CUPO'}}</button></form></section>
<section v-if="confirmation" class="confirmation"><div class="check">✓</div><div><strong>¡Reserva confirmada!</strong><span>Código de reserva: <b>{{confirmation}}</b></span></div></section>
<div v-if="error" class="notice">{{error}} <small v-if="API==='/api'">La vista continúa en modo demostración para que puedas revisar el diseño.</small></div>
</main>
<footer><div class="footer-brand"><span class="crown">♛</span><strong>VIÑA<br>STAGE</strong><small>MÚSICA • AMIGOS • BUENA ONDA</small></div><div>Viña del Mar</div></footer>
</div>
</template>