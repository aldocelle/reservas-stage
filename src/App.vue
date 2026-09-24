<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AdminPanel from './AdminPanel.vue';
import { DEMO_ON, demoDaySlots, demoLoad, demoRange, demoReserve } from './demo';
import { bloqueInfo } from './blocks';

const API=import.meta.env.VITE_API_BASE_URL||'/api';
const hashTick=ref(0);
const route=computed(()=>{void hashTick.value;return window.location.hash||'#reservas'});
const showAdmin=computed(()=>route.value.startsWith('#/admin'));
const navActive=computed(()=>{const h=route.value;if(h.startsWith('#reservas'))return '#reservas';if(h.startsWith('#contacto'))return '#contacto';return '#eventos'});
const vReveal={mounted(el,binding){if(matchMedia('(prefers-reduced-motion: reduce)').matches){el.classList.add('is-in');return}el.classList.add('reveal');if(binding.value!=null)el.style.setProperty('--d',binding.value+'s');const io=new IntersectionObserver(es=>{for(const e of es){if(e.isIntersecting){el.classList.add('is-in');io.disconnect();break}}},{threshold:.12,rootMargin:'0px 0px -50px 0px'});el._revealIO=io;io.observe(el)},unmounted(el){if(el._revealIO)el._revealIO.disconnect()}};
function onHash(){hashTick.value++;menuOpen.value=false}
// Cierra el menú al hacer scroll (móvil) y al volver a escritorio (rotación / resize)
let scrollClose='',deskMq=null;
function onDeskChange(e){if(e.matches)menuOpen.value=false}
onMounted(()=>{scrollClose=()=>{if(menuOpen.value)menuOpen.value=false;topScrolled.value=window.scrollY>10};window.addEventListener('scroll',scrollClose,{passive:true});deskMq=matchMedia('(min-width:761px)');deskMq.addEventListener('change',onDeskChange)})
onUnmounted(()=>{if(scrollClose)window.removeEventListener('scroll',scrollClose);if(deskMq)deskMq.removeEventListener('change',onDeskChange)})
const siteSettings=ref({site_name:'Viña Stage',hero_title:'VIÑA STAGE',booking_notice:''});
const demoMode=ref(false);
async function loadSettings(){if(demoMode.value){try{siteSettings.value={...siteSettings.value,...demoLoad().settings}}catch(e){}return}try{const r=await fetch(`${API}/public_settings.php`);const x=await r.json();if(!x.settings)throw Error('sin settings');siteSettings.value={...siteSettings.value,...(x.settings||{})}}catch(e){if(DEMO_ON){demoMode.value=true;try{siteSettings.value={...siteSettings.value,...demoLoad().settings}}catch(e2){}}}}
const bookingOpen=computed(()=>siteSettings.value.booking_enabled!=='0');

const names=['LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES'];
const WEEK=['LUN','MAR','MIÉ','JUE','VIE'];
const MESES=['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
const avail=ref({}),selectedDate=ref(''),selectedSlot=ref(0),loading=ref(true),dayLoading=ref(false),saving=ref(false),error=ref('');
const cal=ref({y:new Date().getFullYear(),m:new Date().getMonth()});
const day=ref({date:'',name:'',slots:[],used:0,total:0});
const form=ref({nombre:'',apellido:'',whatsapp:'',email:'',consent:true}),confirmation=ref(''),lastReservation=ref(null);
const formErrors=ref({});
const carouselHeld=ref(false);
const menuOpen=ref(false),topScrolled=ref(false);
const events=[
  {id:1,date:'PRÓXIMAMENTE',title:'LIVE SESSION',type:'MÚSICA EN VIVO',tone:'lime',mark:'LIVE',image:'flyers/live-session.jpg'},
  {id:2,date:'PRÓXIMAMENTE',title:'STAGE COMEDY',type:'COMEDIA EN VIVO',tone:'orange',mark:'COMEDY',image:'flyers/stage-comedy.jpg'},
  {id:3,date:'PRÓXIMAMENTE',title:'NIGHT STAGE',type:'NOCHE DE EVENTOS',tone:'violet',mark:'NIGHT',image:'flyers/night-stage.jpg'},
  {id:4,date:'PRÓXIMAMENTE',title:'STAGE UP',type:'EXPERIENCIAS EN VIVO',tone:'red',mark:'STAGE',image:'flyers/stage-up.jpg'}
];
const marqueeEvents=[...events,...events];
function holdCarousel(e){carouselHeld.value=true;try{const t=e.currentTarget;if(t&&t.setPointerCapture&&e.pointerId!=null)t.setPointerCapture(e.pointerId)}catch(_){}}
function releaseCarousel(){carouselHeld.value=false}
// La cartelera también se puede fijar detenida desde un botón (teclado, tacto y lectores de pantalla).
const marqueePinned=ref(false);
const marqueePaused=computed(()=>carouselHeld.value||marqueePinned.value);
function toggleMarquee(){marqueePinned.value=!marqueePinned.value}
function printPage(){window.print()}

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
function selectDay(date,keepConfirm){if(!date||!avail.value[date])return;selectedDate.value=date;selectedSlot.value=0;if(!keepConfirm)confirmation.value='';loadDay(date,keepConfirm)}
async function loadMonth(preserve){
  loading.value=true;error.value='';
  const from=firstOfMonth(cal.value.y,cal.value.m),to=iso(new Date(cal.value.y,cal.value.m+1,0));
  try{
    const r=await fetch(`${API}/availability_range.php?from=${from}&to=${to}`);
    if(!r.ok)throw Error(`Error del servidor (${r.status})`);
    const x=await r.json();
    if(!Array.isArray(x.days))throw Error('Respuesta inválida de disponibilidad');
    const map={};for(const d of (x.days||[]))map[d.date]=d;
    avail.value=map;demoMode.value=false;
  }catch(e){
    if(!DEMO_ON){error.value=e.message}
    else{demoMode.value=true;try{siteSettings.value={...siteSettings.value,...demoLoad().settings}}catch(se){}
      const map={};for(const d of demoRange(from,to,minISO.value,iso(new Date())))map[d.date]=d;avail.value=map}
  }
  const firstBookable=cells.value.find(c=>!c.empty&&c.bookable);
  const keep=selectedDate.value&&avail.value[selectedDate.value]&&avail.value[selectedDate.value].bookable?selectedDate.value:'';
  if(keep)selectDay(keep,preserve);else if(firstBookable)selectDay(firstBookable.date,preserve);
  else{selectedDate.value='';day.value={date:'',name:'',slots:[],used:0,total:0};selectedSlot.value=0}
  loading.value=false;
}
async function loadDay(date,keepConfirm){
  const a=avail.value[date]||{};
  day.value={date,name:dayName(date),slots:[],used:a.used||0,total:a.total||0};
  selectedSlot.value=0;if(!keepConfirm)confirmation.value='';dayLoading.value=true;
  if(demoMode.value)day.value={...day.value,slots:demoDaySlots(date)};
  else try{
    const r=await fetch(`${API}/availability.php?date=${date}`);
    if(!r.ok)throw Error(`Error del servidor (${r.status})`);
    const x=await r.json();
    if(!Array.isArray(x.slots))throw Error('Respuesta inválida de disponibilidad');
    day.value={...day.value,slots:x.slots||[]};
  }catch(e){
    if(!DEMO_ON)error.value=e.message;
    else{demoMode.value=true;day.value={...day.value,slots:demoDaySlots(date)}}
  }
  dayLoading.value=false;
  const i=day.value.slots.findIndex(s=>s.available>0);selectedSlot.value=i>=0?i:0;
}
const slot=computed(()=>day.value.slots[selectedSlot.value]);
const slotsBloques=computed(()=>(day.value.slots||[]).map(s=>({...s,...bloqueInfo(s.start_time,s.end_time)})));
const bloqueSel=computed(()=>bloqueInfo(slot.value&&slot.value.start_time,slot.value&&slot.value.end_time));
const bloquesDelDia=computed(()=>(day.value.slots||[]).length);
function pct(d){return d.total?Math.round(d.used/d.total*100):0}
const hasAvailability=computed(()=>day.value.slots.some(s=>s.available>0));
async function reserve(){if(saving.value||loading.value||dayLoading.value)return;if(!form.value.nombre||!form.value.apellido||!form.value.whatsapp||!slot.value)return;const digits=form.value.whatsapp.replace(/[\s\-.()]/g,'');if(!/^\+?\d{8,15}$/.test(digits)){error.value='WhatsApp inválido (8 a 15 dígitos)';return}if(form.value.email&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email.trim())){error.value='Email inválido';return}saving.value=true;error.value='';confirmation.value='';lastReservation.value=null;try{if(demoMode.value){confirmation.value=demoReserve(day.value.date,slot.value.slot_id,{nombre:form.value.nombre,apellido:form.value.apellido,whatsapp:form.value.whatsapp,email:form.value.email},slot.value);lastReservation.value={code:confirmation.value,date:day.value.date,day:day.value.name,start:slot.value.start_time,end:slot.value.end_time,name:`${form.value.nombre} ${form.value.apellido}`,bloque:bloqueInfo(slot.value.start_time,slot.value.end_time).nombre};form.value={nombre:'',apellido:'',whatsapp:'',email:'',consent:true};await loadMonth(true);return}const r=await fetch(`${API}/reservations.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({date:day.value.date,slotId:slot.value.slot_id,firstName:form.value.nombre,lastName:form.value.apellido,whatsapp:form.value.whatsapp,email:form.value.email,whatsappConsent:form.value.consent})});const x=await r.json().catch(()=>({}));if(!r.ok||!x.reservation)throw Error(x.error||'No fue posible crear la reserva');confirmation.value=x.reservation.reservation_code;lastReservation.value={code:confirmation.value,date:day.value.date,day:day.value.name,start:slot.value.start_time,end:slot.value.end_time,name:`${form.value.nombre} ${form.value.apellido}`,bloque:bloqueInfo(slot.value.start_time,slot.value.end_time).nombre};form.value={nombre:'',apellido:'',whatsapp:'',email:'',consent:true};await loadMonth(true)}catch(e){error.value=e.message||'No fue posible crear la reserva'}finally{saving.value=false}}
onMounted(async()=>{await loadSettings();gotoMin();await loadMonth();window.addEventListener('hashchange',onHash)});
onUnmounted(()=>{window.removeEventListener('hashchange',onHash)});
</script>

<template>
<AdminPanel v-if="showAdmin" />
<div v-else class="site">
<header class="topbar" :class="{scrolled:topScrolled}">
  <a class="brand" href="#reservas" aria-label="Viña Stage · inicio"><img class="brand-logo" :src="'flyers/logo%20stage.png'" alt="Viña Stage" width="1165" height="610" decoding="async" fetchpriority="high"><span class="brand-tag"><b>CENTRO DE EVENTOS</b><small>VIÑA DEL MAR · CHILE</small></span></a>
  <nav id="mainnav" class="nav" :class="{open:menuOpen}">
    <a href="#reservas" :class="{active:navActive==='#reservas'}" @click="menuOpen=false">RESERVAS</a>
    <a href="#eventos" :class="{active:navActive==='#eventos'}" @click="menuOpen=false">CARTELERA</a>
    <a href="#contacto" :class="{active:navActive==='#contacto'}" @click="menuOpen=false">CONTACTO</a>
    <a class="top-cta" href="#reservas" @click="menuOpen=false">RESERVAR <span class="cta-arrow" aria-hidden="true">→</span></a>
  </nav>
  <button class="menu" :class="{open:menuOpen}" :aria-expanded="menuOpen?'true':'false'" aria-controls="mainnav" aria-label="Menú" @click="menuOpen=!menuOpen" @keyup.enter.space="menuOpen=!menuOpen"><span/><span/><span/></button>
  <transition name="fade"><div v-if="menuOpen" class="mobile-overlay" @click="menuOpen=false" aria-hidden="true"></div></transition>
</header>

<main>
<section id="reservas" class="reservation-section">
  <!-- Reservas: la imagen de la promo manda; el calendario viene justo debajo. -->
  <div class="reservation-intro stage-hero" v-reveal="0">
    <h2 class="sr-only">Reserva tu cupo para la Promo Estudiante Viña Stage</h2>
    <p class="stage-hero-tag"><span aria-hidden="true">01</span> Reservas · Promo Estudiante</p>
    <div class="stage-hero-body">
      <figure class="stage-hero-poster">
        <img class="stage-hero-banner" :src="'flyers/reservas.png'" alt="Promo Estudiante Viña Stage: 2 Schop Cristal + Pizza Individual por $5.000, lunes a viernes de 14:00 a 20:00 Hrs., solo 60 cupos diarios y TNE vigente." width="1408" height="736" loading="lazy" decoding="async">
      </figure>
      <div class="stage-hero-info">
        <ul class="stage-hero-facts">
          <li>📅 Lunes a viernes</li>
          <li>🕐 14:00 a 20:00 Hrs.</li>
          <li>⚠️ Solo 60 cupos diarios</li>
          <li>🎓 TNE vigente</li>
        </ul>
        <a class="stage-hero-cta" href="#dia">Reserva tu cupo <span aria-hidden="true">↓</span></a>
        <p class="stage-hero-note">Elige día y horario, completa tus datos y confirma tu reserva. Muestra tu código y tu TNE vigente al llegar a Viña Stage.</p>
      </div>
    </div>
  </div>
  <div v-if="!bookingOpen" class="notice" role="alert">Reservas pausadas por el momento. {{siteSettings.booking_notice}}</div>
  <template v-else>
  <section id="dia" class="panel day-panel" v-reveal="0.1"><div class="section-title">SELECCIONA UN DÍA</div><div class="cal-nav"><button type="button" :disabled="!canPrev" aria-label="Mes anterior" @click="moveMonth(-1)">‹</button><strong>{{monthLabel}}</strong><button type="button" :disabled="!canNext" aria-label="Mes siguiente" @click="moveMonth(1)">›</button></div><div class="cal-head"><span v-for="w in WEEK" :key="w">{{w}}</span></div><div v-if="loading" class="loading-row">Consultando disponibilidad…</div><template v-else><div class="cal-grid"><template v-for="c in cells" :key="c.key"><span v-if="c.empty" class="cal-cell empty" aria-hidden="true"></span><button v-else type="button" :class="['cal-cell',{active:c.date===selectedDate,off:!c.bookable,full:c.available<=0}]" :disabled="!c.bookable" :aria-pressed="c.date===selectedDate" :title="c.bookable?(c.available+' cupos disponibles'):'No disponible'" @click="selectDay(c.date)"><span class="cal-day">{{c.d}}</span><span class="cal-cupos">{{c.bookable?(c.used+'/'+c.total):'—'}}</span><span v-if="c.bookable" class="cal-bar"><i :style="{width:pct(c)+'%'}"></i></span></button></template></div><p v-if="!cells.some(c=>c.bookable)" class="empty-note">Sin días disponibles este mes. Revisa el mes siguiente.</p></template><p class="cal-note"><span v-if="startLabel">Reservas desde <b>{{startLabel}}</b> · </span>agenda abierta hasta <b>{{fechaCorta(maxISO())}}</b> · 3 bloques de 2 horas por día.</p></section>
  <section class="panel schedule-panel" v-reveal="0.15"><div class="schedule-heading"><h2>BLOQUES DISPONIBLES <span>•</span> <strong>{{day.name}} {{fechaCorta(day.date)}}</strong></h2><div>{{bloquesDelDia}} bloque{{bloquesDelDia===1?'':'s'}} de 2 horas · Cupos del día: <b>{{day.used}}/{{day.total}}</b></div></div><div v-if="dayLoading" class="loading-row">Cargando bloques…</div><div v-else-if="!day.slots.length" class="empty-note">Sin bloques publicados para este día.</div><div v-else class="slots"><button v-for="(s,i) in slotsBloques" :key="s.slot_id" type="button" :disabled="s.available<=0" :class="['slot',{active:i===selectedSlot},{full:s.available<=0}]" :aria-pressed="i===selectedSlot" @click="selectSlot(i)"><strong v-if="s.nombre">BLOQUE {{s.n}}<span class="solo-desktop"> · {{s.start_time}}</span></strong><strong v-else>{{s.start_time}} - {{s.end_time}}</strong><span class="slot-range">{{s.rango}}</span><span class="slot-cupos">{{s.available}} cupo{{s.available===1?'':'s'}}<span class="solo-desktop"> disponible{{s.available===1?'':'s'}}</span></span><span v-if="i===selectedSlot" class="tick">✓</span></button></div></section>
  <section class="panel form-panel" v-reveal="0.2"><div class="section-title">COMPLETA TUS DATOS</div><p v-if="slot" class="slot-chosen">Bloque elegido: <b>{{bloqueSel.nombre ? bloqueSel.nombre+' · ' : ''}}{{bloqueSel.rango}}</b> · {{day.name}} {{fechaCorta(day.date)}}</p><form @submit.prevent="reserve" novalidate><div class="form-grid"><label class="field"><span aria-hidden="true">♙</span><input v-model.trim="form.nombre" required minlength="2" maxlength="80" autocomplete="given-name" placeholder="Nombre *"></label><label class="field"><span aria-hidden="true">♙</span><input v-model.trim="form.apellido" required minlength="2" maxlength="80" autocomplete="family-name" placeholder="Apellido *"></label><label class="field"><span aria-hidden="true">⌕</span><input v-model.trim="form.whatsapp" required inputmode="tel" autocomplete="tel" maxlength="16" placeholder="WhatsApp *"></label><label class="field"><span aria-hidden="true">✉</span><input v-model.trim="form.email" type="email" autocomplete="email" maxlength="160" placeholder="Email (opcional)"></label></div><label class="consent"><input v-model="form.consent" type="checkbox"><span>Acepto recibir información de Viña Stage por WhatsApp.</span></label><button class="reserve-btn" :class="{saving:saving}" type="submit" :disabled="saving || loading || dayLoading || !slot || (slot && slot.available<=0)"><span aria-hidden="true">▣</span> {{saving?'RESERVANDO...':'RESERVAR CUPO'}}</button></form></section>
  <section v-if="confirmation" class="confirmation" role="status"><div class="check">✓</div><div><strong>¡Reserva confirmada!</strong><span v-if="lastReservation">A nombre de <b>{{lastReservation.name}}</b> · <b>{{lastReservation.day}} {{lastReservation.date}}</b> · <b>{{lastReservation.bloque ? lastReservation.bloque+' · ' : ''}}{{lastReservation.start}} a {{lastReservation.end}} Hrs.</b></span><span>Código de reserva: <b class="res-code">{{confirmation}}</b></span><span>Muestra este código al llegar. Si no puedes asistir, avísanos por WhatsApp.</span><span v-if="demoMode">Modo demostración: se guardó en este navegador.</span><button type="button" class="ghost-btn small confirmation-print" @click="printPage"><span aria-hidden="true">⎙</span> IMPRIMIR CÓDIGO</button></div></section>
  <div v-if="error" class="notice" role="alert">{{error}}</div>
  <div v-if="demoMode" class="notice demo-banner">Estás en modo demostración: los datos se guardan en tu navegador. Conecta el backend PHP/MySQL para producción.</div>
  </template>
</section>

<section id="eventos" class="events-section">
  <div class="section-head" v-reveal="0.05"><div><p class="eyebrow">CARTELERA / 02</p><h2>LO QUE PASA<br><strong>EN STAGE</strong></h2></div><p class="section-dek">Una noche puede empezar con una canción, una risa o una idea que se te ocurra después.</p><span class="section-index" aria-hidden="true">02<br><b>PROGRAMACIÓN</b></span></div>
  <div class="event-stage" v-reveal="0.12" :class="{held:marqueePaused}" role="region" aria-roledescription="carrusel" aria-label="Cartelera de eventos" @pointerdown="holdCarousel" @pointerup="releaseCarousel" @pointercancel="releaseCarousel" @pointerleave="releaseCarousel" @dragstart.prevent @contextmenu.prevent>
    <div class="event-track">
      <article v-for="(event,i) in marqueeEvents" :key="event.id+'-'+i" :class="['event-card',event.tone]" :aria-hidden="i>=events.length">
        <img v-if="event.image" :src="event.image" :alt="event.title" class="flyer-img" loading="lazy" draggable="false" @error="event.image=''" />
        <div class="flyer-noise"></div><span class="flyer-mark">{{event.mark}}</span><div class="flyer-content"><small>{{event.date}}</small><h3>{{event.title}}</h3><p>{{event.type}}</p></div><div class="flyer-footer">VIÑA STAGE · AV. VALPARAÍSO 65</div>
      </article>
    </div>
  </div>
  <div class="event-meta" v-reveal="0.18"><div class="event-meta-status"><span class="live-dot" :class="{paused:marqueePaused}" aria-hidden="true"></span> {{marqueePaused ? 'CARTELERA DETENIDA · PULSA «REANUDAR» PARA CONTINUAR' : 'CARTELERA EN MOVIMIENTO · MANTÉN PRESIONADO PARA DETENER'}}</div><button type="button" class="marquee-toggle" :aria-pressed="marqueePinned" @click="toggleMarquee">{{marqueePinned ? 'REANUDAR' : 'DETENER'}}</button></div>
</section>

<section id="contacto" class="contact-section">
  <div v-reveal="0.08"><p class="eyebrow">CONTACTO</p><h2>ENCUÉNTRANOS<br><strong>EN VIÑA.</strong></h2><p>Av. Valparaíso 65<br>Viña del Mar, Chile</p><a class="primary-btn" href="https://www.google.com/maps/search/?api=1&query=Av.+Valpara%C3%ADso+65,+Vi%C3%B1a+del+Mar" target="_blank" rel="noreferrer">VER EN MAPA ↗</a></div>
  <div class="contact-card" v-reveal="0.16"><span class="contact-icon">⌖</span><small>UBICACIÓN</small><strong>AV. VALPARAÍSO 65</strong><p>En pleno centro de Viña del Mar.</p><div class="contact-map"><iframe :src="'https://www.google.com/maps?q=Av.+Valpara%C3%ADso+65,+Vi%C3%B1a+del+Mar,+Chile&z=16&output=embed&hl=es'" title="Mapa de Av. Valparaíso 65, Viña del Mar" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe><a class="contact-map-link" href="https://www.google.com/maps/search/?api=1&query=Av.+Valpara%C3%ADso+65,+Vi%C3%B1a+del+Mar" target="_blank" rel="noreferrer">ABRIR MAPA ↗</a></div></div>
</section>
</main>

<footer><div class="footer-brand"><span class="crown">♛</span><strong>VIÑA<br>STAGE</strong><small>MÚSICA • AMIGOS • BUENA ONDA</small></div><div>Av. Valparaíso 65 · Viña del Mar · <a href="#/admin" class="admin-link">Admin</a></div></footer>
</div>
</template>