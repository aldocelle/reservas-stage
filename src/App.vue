<script setup>
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref, watch } from 'vue';
import { bloqueInfo } from './blocks';
import { formatRut, normalizeRut, rutError, sanitizeRutInput, validateRut } from './rut';

const AdminPanel = defineAsyncComponent(() => import('./AdminPanel.vue'));

const API=import.meta.env.VITE_API_BASE_URL||'/api';
const API_MISSING=API==='/api'||!API;
function apiError(prefix,response){
  if(API_MISSING)return `${prefix}: el backend no está configurado en este despliegue. Configura VITE_API_BASE_URL con la URL pública de Railway.`;
  const type=response.headers.get('content-type')||'';
  if(type.includes('text/html'))return `${prefix}: el servidor devolvió la web en vez de JSON. Revisa que VITE_API_BASE_URL termine en /api y que el backend esté desplegado en Railway.`;
  return `${prefix}: error del servidor (${response.status}).`;
}
const hashTick=ref(0);
const route=computed(()=>{void hashTick.value;return window.location.hash||'#inicio'});
const showAdmin=computed(()=>route.value.startsWith('#/admin'));
const navActive=computed(()=>{const h=route.value;if(h.startsWith('#dia')||h.startsWith('#reservas'))return '#reservas';if(h.startsWith('#promociones'))return '#promociones';if(h.startsWith('#eventos'))return '#eventos';if(h.startsWith('#contacto'))return '#contacto';return '#inicio'});
const vReveal={mounted(el,binding){if(matchMedia('(prefers-reduced-motion: reduce)').matches){el.classList.add('is-in');return}el.classList.add('reveal');if(binding.value!=null)el.style.setProperty('--d',binding.value+'s');const io=new IntersectionObserver(es=>{for(const e of es){if(e.isIntersecting){el.classList.add('is-in');io.disconnect();break}}},{threshold:.12,rootMargin:'0px 0px -50px 0px'});el._revealIO=io;io.observe(el)},unmounted(el){if(el._revealIO)el._revealIO.disconnect()}};
function onHash(){hashTick.value++;menuOpen.value=false;if(!window.location.hash)window.scrollTo({top:0,behavior:'smooth'})}
// Cierra el menú solo al hacer scroll fuera de él: al estar abierto se bloquea el
// scroll del body, así que si hay scroll es manual sobre el propio menú.
let deskMq=null;
function onDeskChange(e){if(e.matches)menuOpen.value=false}
function lockMenuScroll(){document.documentElement.style.overflow=menuOpen.value?'hidden':''}
function onMenuScroll(){if(menuOpen.value)menuOpen.value=false}
// (el lock del scroll del menú va junto a su declaración, más abajo)
onMounted(()=>{lockMenuScroll();window.addEventListener('scroll',()=>{topScrolled.value=window.scrollY>10},{passive:true});deskMq=matchMedia('(min-width:761px)');deskMq.addEventListener('change',onDeskChange)})
onUnmounted(()=>{document.documentElement.style.overflow='';const nav=document.getElementById('mainnav');if(nav)nav.removeEventListener('scroll',onMenuScroll);if(deskMq)deskMq.removeEventListener('change',onDeskChange)})
const siteSettings=ref({site_name:'Viña Stage',hero_title:'VIÑA STAGE',booking_notice:''});
async function loadSettings(){try{const r=await fetch(`${API}/public_settings.php`);if(!r.ok)throw Error(apiError('No se pudo conectar con el backend',r));const type=r.headers.get('content-type')||'';if(type.includes('text/html'))throw Error(apiError('No se pudo conectar con el backend',r));const x=await r.json();if(!x.settings)throw Error('El backend no devolvió la configuración del sitio');siteSettings.value={...siteSettings.value,...(x.settings||{})}}catch(e){error.value=e.message||'No se pudo conectar con el backend';loading.value=false}}
const bookingOpen=computed(()=>siteSettings.value.booking_enabled!=='0');

const names=['LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES'];
const WEEK=['LUN','MAR','MIÉ','JUE','VIE'];
const MESES=['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
const avail=ref({}),selectedDate=ref(''),selectedSlot=ref(0),loading=ref(true),dayLoading=ref(false),saving=ref(false),error=ref('');
const cal=ref({y:new Date().getFullYear(),m:new Date().getMonth()});
const day=ref({date:'',name:'',slots:[],used:0,total:0});
const form=ref({nombre:'',apellido:'',rut:'',whatsapp:'',email:'',consent:true}),confirmation=ref(''),lastReservation=ref(null);
const formErrors=ref({});
const menuOpen=ref(false),topScrolled=ref(false);
// Bloqueo del scroll del body mientras el menú móvil está abierto + cierre al scrollear el menú
watch(menuOpen,(v)=>{lockMenuScroll();const nav=document.getElementById('mainnav');if(nav){if(v)nav.addEventListener('scroll',onMenuScroll,{passive:true});else nav.removeEventListener('scroll',onMenuScroll)}})
const events=[
  {id:1,date:'PRÓXIMAMENTE',title:'LIVE SESSION',type:'MÚSICA EN VIVO',tone:'lime',mark:'LIVE',image:'/flyers/live-session.webp'},
  {id:2,date:'PRÓXIMAMENTE',title:'STAGE COMEDY',type:'COMEDIA EN VIVO',tone:'orange',mark:'COMEDY',image:'/flyers/stage-comedy.webp'},
  {id:3,date:'PRÓXIMAMENTE',title:'NIGHT STAGE',type:'NOCHE DE EVENTOS',tone:'violet',mark:'NIGHT',image:'/flyers/night-stage.webp'},
  {id:4,date:'PRÓXIMAMENTE',title:'STAGE UP',type:'EXPERIENCIAS EN VIVO',tone:'red',mark:'STAGE',image:'/flyers/stage-up.webp'}
];
const marqueeEvents=[...events,...events];
const promos=[
  {id:1,title:'Heineken / Royal Guard 650cc',price:'2x$6.500',image:'/flyers/promo-heineken-royal.webp'},
  {id:2,title:'Piscolón Mistral / Alto del Carmen',price:'2x$8.000',image:'/flyers/promo-piscolon.webp'},
  {id:3,title:'Daikiri frutal',price:'2x$10.000',image:'/flyers/promo-daikiri.webp'},
  {id:4,title:'Margarita',price:'2x$10.000',image:'/flyers/promo-margarita.webp'},
  {id:5,title:'Tropical Gin',price:'2x$12.000',image:'/flyers/promo-tropical-gin.webp'}
];
// Marquee de promociones: mismo funcionamiento que la cartelera (items duplicados en bucle),
// pero en sentido contrario gracias a vs-marquee-rev (-50% → 0, hacia la derecha).
// Duraciones (148s / 150s) calibradas para igualar la velocidad en px/s de .event-track (48s).
const marqueePromos=[...promos,...promos];
function printPage(){window.print()}
function onRutInput(event){form.value.rut=formatRut(sanitizeRutInput(event.target.value)); if (validateRut(form.value.rut)) delete formErrors.value.rut}

const pad=n=>String(n).padStart(2,'0');
function iso(d){return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`}
function dayName(date){const w=new Date(date+'T12:00:00').getDay();return names[w-1]||''}
function fechaCorta(v){return v?`${v.slice(8,10)}-${v.slice(5,7)}-${v.slice(0,4)}`:''}
function firstOfMonth(y,m){return iso(new Date(y,m,1))}
function firstOfNextMonth(y,m){return iso(new Date(y,m+1,1))}
function maxISO(){const d=new Date();d.setHours(12,0,0,0);d.setDate(d.getDate()+60);return iso(d)}
// Calendario: Lun-Vie del mes visible; la fecha mínima es max(hoy, booking_start_date).
const startISO=computed(()=>{const v=siteSettings.value.booking_start_date;return /^\d{4}-\d{2}-\d{2}$/.test(v||'')?v:''});
const minISO=computed(()=>{const t=iso(new Date());return startISO.value&&startISO.value>t?startISO.value:t});
const startLabel=computed(()=>fechaCorta(startISO.value));
const monthLabel=computed(()=>`${MESES[cal.value.m]} ${cal.value.y}`);
const cells=computed(()=>{const y=cal.value.y,m=cal.value.m,last=new Date(y,m+1,0).getDate(),out=[];
  const lead=(new Date(y,m,1).getDay()+6)%7;
  for(let i=0;i<lead;i++)out.push({empty:true,key:'e'+i});
  for(let d=1;d<=last;d++){const dt=new Date(y,m,d),wd=dt.getDay();if(wd===0||wd===6)continue;
    const date=iso(dt),a=avail.value[date]||{};
    out.push({key:date,date,d:pad(d),name:names[wd-1],short:WEEK[wd-1],dateShort:`${pad(d)}/${pad(m+1)}`,total:a.total||0,used:a.used||0,available:a.available||0,bookable:!!a.bookable});}
  while(out.length%5!==0)out.push({empty:true,key:'p'+out.length});
  return out})
const canPrev=computed(()=>firstOfMonth(cal.value.y,cal.value.m)>firstOfMonth(Number(minISO.value.slice(0,4)),Number(minISO.value.slice(5,7))-1));
const canNext=computed(()=>firstOfNextMonth(cal.value.y,cal.value.m)<=maxISO());
function moveMonth(step){const d=new Date(cal.value.y,cal.value.m+step,1);cal.value={y:d.getFullYear(),m:d.getMonth()};selectedDate.value='';loadMonth()}
function gotoMin(){const p=minISO.value.split('-');cal.value={y:Number(p[0]),m:Number(p[1])-1}}
function selectSlot(i){selectedSlot.value=i;confirmation.value=''}
function selectDay(date,keepConfirm){if(!date||!avail.value[date])return;selectedDate.value=date;selectedSlot.value=-1;if(!keepConfirm)confirmation.value='';loadDay(date,keepConfirm)}
async function loadMonth(preserve){
  loading.value=true;error.value='';
  const from=firstOfMonth(cal.value.y,cal.value.m),to=iso(new Date(cal.value.y,cal.value.m+1,0));
  try{
    const r=await fetch(`${API}/availability_range.php?from=${from}&to=${to}`);
    if(!r.ok)throw Error(apiError('No se pudo cargar la disponibilidad',r));
    const type=r.headers.get('content-type')||'';if(type.includes('text/html'))throw Error(apiError('No se pudo cargar la disponibilidad',r));
    const x=await r.json();
    if(!Array.isArray(x.days))throw Error('Respuesta inválida de disponibilidad');
    const map={};for(const d of (x.days||[]))map[d.date]=d;
    avail.value=map;
  }catch(e){
    error.value=e.message.includes('No se pudo cargar la disponibilidad')?e.message:`No se pudo cargar la disponibilidad: ${e.message}`;
  }
  const firstBookable=cells.value.find(c=>!c.empty&&c.bookable);
  const keep=selectedDate.value&&avail.value[selectedDate.value]&&avail.value[selectedDate.value].bookable?selectedDate.value:'';
  if(keep)selectDay(keep,preserve);else if(firstBookable)selectDay(firstBookable.date,preserve);
  else{selectedDate.value='';day.value={date:'',name:'',slots:[],used:0,total:0};selectedSlot.value=-1}
  loading.value=false;
}
async function loadDay(date,keepConfirm){
  const a=avail.value[date]||{};
  day.value={date,name:dayName(date),slots:[],used:a.used||0,total:a.total||0};
  selectedSlot.value=-1;if(!keepConfirm)confirmation.value='';dayLoading.value=true;
  try{
    const r=await fetch(`${API}/availability.php?date=${date}`);
    if(!r.ok)throw Error(apiError('No se pudo cargar el día seleccionado',r));
    const type=r.headers.get('content-type')||'';if(type.includes('text/html'))throw Error(apiError('No se pudo cargar el día seleccionado',r));
    const x=await r.json();
    if(!Array.isArray(x.slots))throw Error('Respuesta inválida de disponibilidad');
    day.value={...day.value,slots:x.slots||[]};
  }catch(e){
    error.value=e.message.includes('No se pudo cargar el día seleccionado')?e.message:`No se pudo cargar el día seleccionado: ${e.message}`;
  }
  dayLoading.value=false;
  const i=day.value.slots.findIndex(s=>s.available>0);selectedSlot.value=i;
}
const slot=computed(()=>day.value.slots[selectedSlot.value]);
const slotsBloques=computed(()=>(day.value.slots||[]).map(s=>({...s,...bloqueInfo(s.start_time,s.end_time)})));
const bloqueSel=computed(()=>bloqueInfo(slot.value&&slot.value.start_time,slot.value&&slot.value.end_time));
const bloquesDelDia=computed(()=>(day.value.slots||[]).length);
function pct(d){return d.total?Math.round(d.used/d.total*100):0}
const hasAvailability=computed(()=>day.value.slots.some(s=>s.available>0));
async function reserve(){if(saving.value||loading.value||dayLoading.value)return;const rut=normalizeRut(form.value.rut);if(!validateRut(rut)){error.value=rutError();return}if(!form.value.nombre||!form.value.apellido||!form.value.whatsapp||!form.value.email.trim()||!form.value.consent||!slot.value){error.value='Completa todos los campos obligatorios y acepta el consentimiento.';return}let digits=form.value.whatsapp.replace(/[\s\-.()]/g,'').replace(/^\+/,'');if(!/^56/.test(digits))digits='56'+digits;if(!/^\d{8,15}$/.test(digits)){error.value='WhatsApp inválido (8 a 15 dígitos)';return}if(form.value.email&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email.trim())){error.value='Email inválido';return}saving.value=true;error.value='';confirmation.value='';lastReservation.value=null;try{
const r=await fetch(`${API}/reservations.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({date:day.value.date,slotId:slot.value.slot_id,firstName:form.value.nombre,lastName:form.value.apellido,rut,whatsapp:digits,email:form.value.email.trim(),whatsappConsent:form.value.consent})});const x=await r.json().catch(()=>({}));if(!r.ok||!x.reservation)throw Error(x.error||'No fue posible crear la reserva');confirmation.value=x.reservation.reservation_code;lastReservation.value={code:confirmation.value,date:day.value.date,day:day.value.name,start:slot.value.start_time,end:slot.value.end_time,name:`${form.value.nombre} ${form.value.apellido}`,bloque:bloqueInfo(slot.value.start_time,slot.value.end_time).nombre};form.value={nombre:'',apellido:'',rut:'',whatsapp:'',email:'',consent:true};await loadMonth(true)}catch(e){error.value=e.message||'No fue posible crear la reserva'}finally{saving.value=false}}
onMounted(async()=>{if('scrollRestoration' in history)history.scrollRestoration='manual';if(!window.location.hash)window.scrollTo(0,0);await loadSettings();gotoMin();await loadMonth();window.addEventListener('hashchange',onHash)});
onUnmounted(()=>{window.removeEventListener('hashchange',onHash)});
</script>

<template>
<AdminPanel v-if="showAdmin" />
<div v-else class="site">
<header class="topbar" :class="{scrolled:topScrolled}">
  <a class="brand" href="#inicio" aria-label="Viña Stage · inicio"><img class="brand-logo" src="/flyers/logo-stage-neon.webp" alt="Viña Stage" width="600" height="283" decoding="async" fetchpriority="high"><span class="brand-tag"><b>MULTIESPACIO</b><small>VIÑA DEL MAR · CHILE</small></span></a>
  <nav id="mainnav" class="nav" :class="{open:menuOpen}">
    <a href="#inicio" :class="{active:navActive==='#inicio'}" @click="menuOpen=false"><span class="nav-label">HOME</span></a>
    <a href="#reservas" :class="{active:navActive==='#reservas'}" @click="menuOpen=false"><span class="nav-label">RESERVAS</span><span class="nav-num" aria-hidden="true">01</span></a>
    <a href="#promociones" :class="{active:navActive==='#promociones'}" @click="menuOpen=false"><span class="nav-label">PROMOCIONES</span><span class="nav-num" aria-hidden="true">02</span></a>
    <a href="#eventos" :class="{active:navActive==='#eventos'}" @click="menuOpen=false"><span class="nav-label">PROGRAMACIÓN</span><span class="nav-num" aria-hidden="true">03</span></a>
    <a href="#contacto" :class="{active:navActive==='#contacto'}" @click="menuOpen=false"><span class="nav-label">CONTACTO</span><span class="nav-num" aria-hidden="true">04</span></a>
    <a class="top-cta" href="#dia" @click="menuOpen=false">RESERVAR <span class="cta-arrow" aria-hidden="true">→</span></a>
  </nav>
  <button class="menu" :class="{open:menuOpen}" :aria-expanded="menuOpen?'true':'false'" aria-controls="mainnav" :aria-label="menuOpen?'Cerrar menú':'Abrir menú'" @click="menuOpen=!menuOpen" @keyup.enter.space="menuOpen=!menuOpen"><span/><span/><span/></button>
  <transition name="fade"><div v-if="menuOpen" class="mobile-overlay" @click="menuOpen=false" aria-hidden="true"></div></transition>
</header>

<main>
<section id="inicio" class="photo-hero" aria-labelledby="photo-hero-title">
  <img class="photo-hero-image" src="/hero/stage-hero.webp" alt="Viña Stage Multiespacio: coctelería, gastronomía, música en vivo y terraza" width="1881" height="836" fetchpriority="high" decoding="async">
  <h2 id="photo-hero-title" class="sr-only">Viña Stage Multiespacio en Viña del Mar</h2>
</section>

<section id="reservas" class="reservation-section">
  <!-- Reservas: la imagen de la promo manda; el calendario viene justo debajo. -->
  <div class="section-head" v-reveal="0.05"><div><p class="eyebrow">01 Reservas Promo</p><h2>RESERVAS<br><strong>PROMO QR</strong></h2></div><p class="section-dek">Tu próxima gran noche ya tiene lugar. Reserva y nos vemos en Viña Stage.</p><span class="section-index" aria-hidden="true">01<br><b>RESERVAS</b></span></div>
  <div class="reservation-intro stage-hero" v-reveal="0">
    <h2 class="sr-only">Reserva tu cupo para la Promo QR Viña Stage</h2>
    <div class="stage-hero-body">
      <figure class="stage-hero-poster">
        <img class="stage-hero-banner" src="/flyers/reservas2.webp" alt="¡Gana la promo! 2 schop de 500 cc + 1 pizza individual por $5.000. Solo 40 cupos diarios, lunes a viernes de 14:00 a 17:00 y de 17:00 a 20:00. Av. Valparaíso 65, Viña del Mar. Promoción válida solo para mayores de 18 años." width="1698" height="926" loading="lazy" decoding="async">
      </figure>
      <div class="stage-hero-info">
        <ul class="stage-hero-facts">
          <li><svg class="fact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 2v4M16 2v4M5 9h14M8 13h3M8 16h5"/></svg> Lunes a viernes</li>
          <li><svg class="fact-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/></svg> 14:00 a 20:00 Hrs.</li>
          <li><svg class="fact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2 21h20L12 3Z"/><path d="M12 9v5M12 17h.01"/></svg> Solo 40 cupos diarios</li>
        </ul>
        <p class="stage-hero-age">Solo mayores de 18 años</p>
        <a class="stage-hero-cta" href="#dia">Reserva tu cupo <span aria-hidden="true">↓</span></a>
        <p class="stage-hero-note">Elige día y bloque horario, completa tus datos y confirma tu reserva.</p>
      </div>
    </div>
  </div>
  <div v-if="!bookingOpen" class="notice" role="alert">Reservas pausadas por el momento. {{siteSettings.booking_notice}}</div>
  <template v-else>
  <div class="booking-grid">
  <section id="dia" class="panel day-panel" v-reveal="0.1" :aria-busy="loading ? 'true' : 'false'"><div class="section-title">Selecciona un día y bloque de horario.</div><div class="cal-nav"><button type="button" :disabled="!canPrev" aria-label="Mes anterior" @click="moveMonth(-1)">‹</button><strong>{{monthLabel}}</strong><button type="button" :disabled="!canNext" aria-label="Mes siguiente" @click="moveMonth(1)">›</button></div><div class="cal-head"><span v-for="w in WEEK" :key="w">{{w}}</span></div><div v-if="loading" class="loading-row">Consultando disponibilidad…</div><template v-else><div class="cal-grid"><template v-for="c in cells" :key="c.key"><span v-if="c.empty" class="cal-cell empty" aria-hidden="true"></span><button v-else type="button" :class="['cal-cell',{active:c.date===selectedDate,off:!c.bookable,full:c.available<=0}]" :disabled="!c.bookable" :aria-pressed="c.date===selectedDate" :title="c.bookable?(c.available+' cupos disponibles'):'No disponible'" @click="selectDay(c.date)"><span class="cal-day">{{c.d}}</span><span v-if="c.bookable" :class="['cal-availability',{warning:c.available<=10,empty:c.available<=0}]"><b>{{c.available}}</b><small>cupos disponibles</small></span><span v-else class="cal-unavailable" aria-hidden="true">—</span><span v-if="c.bookable" class="cal-bar"><i :style="{width:pct(c)+'%'}"></i></span></button></template></div><p v-if="!cells.some(c=>c.bookable)" class="empty-note">Sin días disponibles este mes. Revisa el mes siguiente.</p></template><p class="cal-note"><span v-if="startLabel">Reservas desde <b>{{startLabel}}</b> · </span>agenda abierta hasta <b>{{fechaCorta(maxISO())}}</b> · 2 bloques de 3 horas por día.</p></section>
  <div class="booking-side">
  <section class="panel schedule-panel" v-reveal="0.15"><div class="schedule-heading"><h2>BLOQUES DISPONIBLES <span>•</span> <strong>{{day.name}} {{fechaCorta(day.date)}}</strong></h2><div>{{bloquesDelDia}} bloque{{bloquesDelDia===1?'':'s'}} de 3 horas · Cupos del día: <b>{{day.used}}/{{day.total}}</b></div></div><div v-if="dayLoading" class="loading-row">Cargando bloques…</div><div v-else-if="!day.slots.length" class="empty-note">Sin bloques publicados para este día.</div><div v-else class="slots"><button v-for="(s,i) in slotsBloques" :key="s.slot_id" type="button" :disabled="s.available<=0" :class="['slot',{active:i===selectedSlot&&s.available>0,full:s.available<=0,'slot--b2':s.n===2}]" :aria-pressed="i===selectedSlot&&s.available>0" @click="selectSlot(i)"><strong v-if="s.nombre">BLOQUE {{s.n}}<span class="solo-desktop"> · {{s.start_time}}</span></strong><strong v-else>{{s.start_time}} - {{s.end_time}}</strong><span class="slot-range">{{s.rango}}</span><span :class="['slot-cupos',{warning:s.available<=5,empty:s.available<=0}]"><b>{{s.available}}</b> {{s.available===1?'cupo disponible':'cupos disponibles'}}</span><span v-if="i===selectedSlot&&s.available>0" class="tick">✓</span></button></div></section>
  <section class="panel form-panel" v-reveal="0.2"><div class="section-title">COMPLETA TUS DATOS</div><div v-if="day.date" class="slot-chosen" :class="{loading:dayLoading,'slot-chosen--b2':slot&&bloqueSel.n===2}" aria-label="Reserva seleccionada" aria-live="polite"><span class="slot-chosen-label">{{dayLoading?'Actualizando disponibilidad':'Tu reserva actual'}}</span><template v-if="slot"><strong>{{bloqueSel.nombre || 'Bloque seleccionado'}}<i v-if="bloqueSel.nombre"> · </i>{{bloqueSel.rango}}</strong></template><strong v-else-if="!dayLoading" class="slot-chosen-empty">Selecciona un bloque disponible</strong><span v-if="!slot&&dayLoading" class="slot-chosen-loading" aria-hidden="true">Cargando bloques…</span><span class="slot-chosen-date">{{day.name}} · {{fechaCorta(day.date)}}</span></div><form @submit.prevent="reserve" novalidate><div class="form-grid"><label class="field"><span aria-hidden="true">♙</span><input v-model.trim="form.nombre" required minlength="2" maxlength="80" autocomplete="given-name" placeholder="Nombre *" aria-label="Nombre *"></label><label class="field"><span aria-hidden="true">♙</span><input v-model.trim="form.apellido" required minlength="2" maxlength="80" autocomplete="family-name" placeholder="Apellido *" aria-label="Apellido *"></label><label class="field"><span aria-hidden="true">▣</span>    <input v-model="form.rut" required inputmode="numeric" autocomplete="off" maxlength="12" placeholder="RUT *" aria-label="RUT *" @input="onRutInput"></label><label class="field field-tel"><span class="field-prefix" aria-hidden="true">+56</span><input v-model.trim="form.whatsapp" required inputmode="tel" autocomplete="tel" maxlength="16" placeholder="9 1234 5678" aria-label="WhatsApp, sin +56 *"></label><label class="field"><span aria-hidden="true">✉</span><input v-model.trim="form.email" type="email" required autocomplete="email" maxlength="160" placeholder="Email *" aria-label="Email *"></label></div><label class="consent"><input v-model="form.consent" type="checkbox" required><span>Acepto recibir información de Viña Stage por WhatsApp. *</span></label><button class="reserve-btn" :class="{saving:saving}" type="submit" :disabled="saving || loading || dayLoading || !slot || (slot && slot.available<=0)"><span aria-hidden="true">▣</span> {{saving?'RESERVANDO...':'RESERVAR CUPO'}}</button></form></section>
  </div>
  </div>
  <section v-if="confirmation" class="confirmation" role="status"><div class="check">✓</div><div><strong>¡Reserva confirmada!</strong><span v-if="lastReservation">A nombre de <b>{{lastReservation.name}}</b> · <b>{{lastReservation.day}} {{lastReservation.date}}</b> · <b>{{lastReservation.bloque ? lastReservation.bloque+' · ' : ''}}{{lastReservation.start}} a {{lastReservation.end}} Hrs.</b></span><span>Código de reserva: <b class="res-code">{{confirmation}}</b></span><span>Muestra este código al llegar. Si no puedes asistir, avísanos por WhatsApp.</span><button type="button" class="ghost-btn small confirmation-print" @click="printPage"><span aria-hidden="true">⎙</span> IMPRIMIR CÓDIGO</button></div></section>
  <div v-if="error" class="notice" role="alert">{{error}}</div>
  </template>
</section>

<section id="promociones" class="promo-section">
  <div class="section-head" v-reveal="0.05"><div><p class="eyebrow">PROMOCIONES / 02</p><h2>PRECIOS QUE<br><strong>DAN GANAS</strong></h2></div><p class="section-dek">Variedad de tragos y cervezas a precios increíbles, todos los días del año.</p><span class="section-index" aria-hidden="true">02<br><b>PROMOCIONES</b></span></div>
  <div class="promo-slider" v-reveal="0.1" role="region" aria-roledescription="carrusel" aria-label="Promociones Viña Stage">
    <div class="promo-slider-frame">
      <div class="promo-track">
        <img v-for="(p,i) in marqueePromos" :key="p.id+'-'+i" :src="p.image" :alt="p.title+' '+p.price" class="promo-slide" :aria-hidden="i>=promos.length" loading="lazy" draggable="false">
      </div>
    </div>
  </div>
</section>

<section id="eventos" class="events-section">
  <div class="section-head" v-reveal="0.05"><div><p class="eyebrow">CARTELERA / 03</p><h2>LO QUE PASA<br><strong>EN STAGE</strong></h2></div><p class="section-dek">Siempre hay una buena razón para salir. Encuentra la tuya en nuestra cartelera.</p><span class="section-index" aria-hidden="true">03<br><b>PROGRAMACIÓN</b></span></div>
  <div class="event-stage" v-reveal="0.12" role="region" aria-roledescription="carrusel" aria-label="Cartelera de eventos">
    <div class="event-track">
      <article v-for="(event,i) in marqueeEvents" :key="event.id+'-'+i" :class="['event-card',event.tone]" :aria-hidden="i>=events.length">
        <img v-if="event.image" :src="event.image" :alt="event.title" class="flyer-img" loading="lazy" draggable="false" @error="event.image=''" />
      </article>
    </div>
  </div>
</section>

<section id="contacto" class="contact-section">
  <div class="section-head" v-reveal="0.05"><div><p class="eyebrow">04 Contacto</p><h2>HABLEMOS<br><strong>DE VIÑA STAGE</strong></h2></div><p class="section-dek">¿Tienes una consulta, quieres reservar o necesitas información? Escríbenos.</p><span class="section-index" aria-hidden="true">04<br><b>CONTACTO</b></span></div>
  <div class="contact-intro" v-reveal="0.08">
    <h2 class="sr-only">Contacto · Viña Stage</h2>
    <div class="contact-address">
      <span class="contact-detail-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
      </span>
      <span><small>DIRECCIÓN</small><strong>Av. Valparaíso 65<br>Viña del Mar, Chile</strong></span>
    </div>
    <div class="contact-ctas">
      <a class="tickets-btn" href="https://portaldisc.com/cartelera/vinastage?utm_source=ig&amp;utm_medium=social&amp;utm_content=link_in_bio&amp;fbclid=PAZXh0bgNhZW0CMTEAcGRvZgJzcnRjBmFwcF9pZA85MzY2MTk3NDMzOTI0NTkAAae_jBc4N5jp5p2RUcRKlNLcsqjBEVnau2oozuMQjH5Yl1vDrk-GDCURS5VS6w_aem_48oI8thtPyZpwos43ufg6Q&amp;utm_id=97760_v0_s00_e0_tv3" target="_blank" rel="noreferrer">
        <span class="contact-detail-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M4 6h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4V6Z"/><path d="M9 6v3M15 6v3M9 15v3M15 15v3"/></svg>
        </span>
        <span><small>VENTA DE ENTRADAS</small><strong>COMPRAR PASSES</strong></span><span aria-hidden="true">↗</span>
      </a>
    </div>
    <a class="contact-email" href="mailto:eventos.vina.stage@gmail.com">
      <span class="contact-detail-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="1"/><path d="m4 7 8 6 8-6"/></svg>
      </span>
      <span><small>ESCRÍBENOS</small><strong>eventos.vina.stage@gmail.com</strong></span><span aria-hidden="true">↗</span>
    </a>
    <div class="contact-socials" aria-label="Redes sociales de Viña Stage">
      <small>SÍGUENOS</small>
      <div>
        <a class="social-link" href="https://www.instagram.com/vinastage" target="_blank" rel="noreferrer" aria-label="Instagram de Viña Stage">
          <svg class="instagram-icon" viewBox="0 0 24 24" role="img" aria-label="Instagram"><defs><linearGradient id="instagram-gradient" x1="4" y1="3" x2="20" y2="21" gradientUnits="userSpaceOnUse"><stop stop-color="#FFD400"/><stop offset=".5" stop-color="#FF4438"/><stop offset="1" stop-color="#BC2BC3"/></linearGradient></defs><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4.1"/><circle cx="17.5" cy="6.5" r="1"/></svg>
          <span>Instagram</span>
        </a>
        <a class="social-link" href="https://www.facebook.com/profile.php?id=61586113003789&amp;ref=PROFILE_EDIT_xav_ig_profile_page_web" target="_blank" rel="noreferrer" aria-label="Facebook de Viña Stage">
          <svg class="facebook-icon" viewBox="0 0 24 24" role="img" aria-label="Facebook"><defs><linearGradient id="facebook-gradient" x1="4" y1="2" x2="20" y2="22" gradientUnits="userSpaceOnUse"><stop stop-color="#18A8FB"/><stop offset=".55" stop-color="#1877F2"/><stop offset="1" stop-color="#E10600"/></linearGradient></defs><path d="M14.2 22v-9h3l.5-3.6h-3.5V7.2c0-1 .3-1.8 1.9-1.8h1.8V2.1A25 25 0 0 0 15.1 2c-2.9 0-4.9 1.8-4.9 5.1v2.3H7.3V13h2.9v9h4Z"/></svg>
          <span>Facebook</span>
        </a>
      </div>
    </div>
  </div>
  <div class="contact-card" v-reveal="0.16" aria-label="Mapa de Av. Valparaíso 65, Viña del Mar">
    <div class="contact-map"><iframe :src="'https://www.google.com/maps?q=Av.+Valpara%C3%ADso+65,+Vi%C3%B1a+del+Mar,+Chile&z=16&output=embed&hl=es'" title="Mapa de Av. Valparaíso 65, Viña del Mar" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe><div class="map-sonar" aria-hidden="true"><i/><i/><i/><b/></div><a class="contact-map-link" href="https://www.google.com/maps/search/?api=1&query=Av.+Valpara%C3%ADso+65,+Vi%C3%B1a+del+Mar" target="_blank" rel="noreferrer">ABRIR MAPA ↗</a></div>
  </div>
</section>
</main>

<footer>
  <div class="footer-top">
    <div class="footer-brand"><img class="footer-logo" src="/flyers/logo-stage-neon.webp" alt="Viña Stage" width="600" height="283" loading="lazy" decoding="async"></div>
    <nav class="footer-column footer-nav" aria-label="Navegación del footer"><small>EXPLORA</small><a href="#reservas">Reservas</a><a href="#eventos">Cartelera</a><a href="#contacto">Contacto</a></nav>
    <div class="footer-column footer-contact"><small>ENCUÉNTRANOS</small><a href="mailto:eventos.vina.stage@gmail.com">eventos.vina.stage@gmail.com</a><span>Av. Valparaíso 65<br>Viña del Mar, Chile</span></div>
  </div>
  <div class="footer-bottom"><a href="#/admin" class="admin-link">Acceso admin <span aria-hidden="true">↗</span></a></div>
</footer>
</div>
</template>