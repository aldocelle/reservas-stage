let days=[];
let selectedDay=0;
let selectedSlot=0;

const daysEl=document.getElementById("days");
const slotsEl=document.getElementById("slots");
const dayLabel=document.getElementById("selectedDayLabel");
const dayTotal=document.getElementById("dayTotal");
const form=document.getElementById("reservationForm");
const button=form.querySelector(".reserve-btn");
const names=["LUNES","MARTES","MIÉRCOLES","JUEVES","VIERNES"];
const pad=n=>String(n).padStart(2,"0");
function isoDate(d){return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`}
function nextWeekdays(){
  const result=[]; const now=new Date(); now.setHours(12,0,0,0);
  for(let i=0;i<14&&result.length<5;i++){
    const d=new Date(now); d.setDate(now.getDate()+i);
    const dow=d.getDay();
    if(dow>=1&&dow<=5) result.push({date:isoDate(d),name:names[dow-1]});
  }
  return result;
}
async function loadAvailability(){
  button.disabled=true; button.textContent="CARGANDO CUPOS...";
  const base=nextWeekdays();
  const loaded=await Promise.all(base.map(async item=>{
    const r=await fetch(`/api/availability?date=${item.date}`);
    if(!r.ok) throw new Error("No se pudo consultar disponibilidad");
    const data=await r.json();
    return {name:item.name,date:item.date,slots:data.slots||[],used:(data.slots||[]).reduce((n,s)=>n+s.reserved,0),total:(data.slots||[]).reduce((n,s)=>n+s.capacity,0)};
  }));
  days=loaded;
  const firstWithSpace=Math.max(0,days.findIndex(d=>d.slots.some(s=>s.available>0)));
  selectedDay=firstWithSpace<0?0:firstWithSpace;
  selectedSlot=0;
  renderDays(); renderSlots();
  button.disabled=false; button.innerHTML="<span>▣</span> RESERVAR CUPO";
}
function renderDays(){
  daysEl.innerHTML=days.map((day,i)=>{
    const pct=day.total?Math.round(day.used/day.total*100):100;
    return `<button type="button" class="day ${i===selectedDay?"active":""}" data-day="${i}">
      <span class="day-name">${day.name}</span>
      <span class="ring ${pct>=100?"full":""}" style="--pct:${pct}%">
        <span class="ring-content">${day.used}/${day.total}<small>CUPOS</small></span>
      </span>
    </button>`;
  }).join("");
  daysEl.querySelectorAll(".day").forEach(el=>el.addEventListener("click",()=>{
    selectedDay=Number(el.dataset.day); selectedSlot=0; renderDays(); renderSlots();
  }));
}
function renderSlots(){
  const day=days[selectedDay]||{name:"",slots:[],used:0,total:0};
  dayLabel.textContent=day.name;
  dayTotal.textContent=`${day.used}/${day.total}`;
  slotsEl.innerHTML=day.slots.map((slot,i)=>`
    <button type="button" class="slot ${i===selectedSlot&&slot.available>0?"active":""}" ${slot.available<=0?"disabled":""} data-slot="${i}">
      <strong>${slot.start_time} - ${slot.end_time}</strong>
      <span>♟ &nbsp;${slot.available} cupos</span><span class="tick">✓</span>
    </button>`).join("");
  slotsEl.querySelectorAll(".slot:not([disabled])").forEach(el=>el.addEventListener("click",()=>{
    selectedSlot=Number(el.dataset.slot); renderSlots();
  }));
}
form.addEventListener("submit",async e=>{
  e.preventDefault();
  if(!form.reportValidity()) return;
  const day=days[selectedDay], slot=day?.slots[selectedSlot];
  if(!day||!slot||slot.available<=0) return alert("Este horario no tiene cupos disponibles.");
  button.disabled=true; button.textContent="RESERVANDO...";
  const data=new FormData(form);
  try{
    const response=await fetch("/api/reservations",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({
      date:day.date,slotId:slot.slot_id,firstName:data.get("nombre"),lastName:data.get("apellido"),
      whatsapp:data.get("whatsapp"),email:data.get("email")||"",whatsappConsent:data.get("consent")==="on"
    })});
    const result=await response.json();
    if(!response.ok) throw new Error(result.error||"No fue posible crear la reserva");
    const confirmation=document.getElementById("confirmation");
    confirmation.querySelector("span").textContent=`Código: ${result.reservation.reservation_code}. Revisa tu WhatsApp para los detalles.`;
    confirmation.hidden=false; confirmation.scrollIntoView({behavior:"smooth",block:"center"});
    form.reset();
    await loadAvailability();
  }catch(error){alert(error.message)}
  finally{button.disabled=false;button.innerHTML="<span>▣</span> RESERVAR CUPO";}
});
loadAvailability().catch(error=>{
  console.error(error);
  days=nextWeekdays().map(x=>({name:x.name,date:x.date,slots:[],used:0,total:0}));
  renderDays();renderSlots();
  button.disabled=false;button.innerHTML="<span>▣</span> REINTENTAR";
});