const days=[
 {name:"LUNES",used:32,total:40,slots:[["14:00 - 16:00",10],["16:00 - 18:00",12],["18:00 - 20:00",10]]},
 {name:"MARTES",used:18,total:40,slots:[["14:00 - 16:00",8],["16:00 - 18:00",6],["18:00 - 20:00",4]]},
 {name:"MIÉRCOLES",used:40,total:40,slots:[["14:00 - 16:00",0],["16:00 - 18:00",0],["18:00 - 20:00",0]]},
 {name:"JUEVES",used:25,total:40,slots:[["14:00 - 16:00",8],["16:00 - 18:00",9],["18:00 - 20:00",8]]},
 {name:"VIERNES",used:11,total:40,slots:[["14:00 - 16:00",4],["16:00 - 18:00",4],["18:00 - 20:00",3]]}
];

let selectedDay=0;
let selectedSlot=0;

const daysEl=document.getElementById("days");
const slotsEl=document.getElementById("slots");
const dayLabel=document.getElementById("selectedDayLabel");
const dayTotal=document.getElementById("dayTotal");

function renderDays(){
  daysEl.innerHTML=days.map((day,i)=>{
    const pct=Math.round(day.used/day.total*100);
    return `<button class="day ${i===selectedDay?"active":""}" data-day="${i}">
      <span class="day-name">${day.name}</span>
      <span class="ring ${pct===100?"full":""}" style="--pct:${pct}%">
        <span class="ring-content">${day.used}/${day.total}<small>CUPOS</small></span>
      </span>
    </button>`;
  }).join("");
  daysEl.querySelectorAll(".day").forEach(el=>el.addEventListener("click",()=>{
    selectedDay=Number(el.dataset.day);
    selectedSlot=0;
    renderDays();
    renderSlots();
  }));
}

function renderSlots(){
  const day=days[selectedDay];
  dayLabel.textContent=day.name;
  dayTotal.textContent=`${day.used}/${day.total}`;
  slotsEl.innerHTML=day.slots.map((slot,i)=>`
    <button class="slot ${i===selectedSlot&&slot[1]>0?"active":""}" ${slot[1]===0?"disabled":""} data-slot="${i}">
      <strong>${slot[0]}</strong>
      <span>♟ &nbsp;${slot[1]} cupos</span>
      <span class="tick">✓</span>
    </button>`).join("");
  slotsEl.querySelectorAll(".slot:not([disabled])").forEach(el=>el.addEventListener("click",()=>{
    selectedSlot=Number(el.dataset.slot);
    renderSlots();
  }));
}

document.getElementById("reservationForm").addEventListener("submit",e=>{
  e.preventDefault();
  const form=e.currentTarget;
  if(!form.reportValidity()) return;
  const day=days[selectedDay];
  const slot=day.slots[selectedSlot];
  if(!slot || slot[1]===0){alert("Este horario no tiene cupos disponibles.");return;}
  const confirmation=document.getElementById("confirmation");
  confirmation.hidden=false;
  confirmation.scrollIntoView({behavior:"smooth",block:"center"});
});

renderDays();
renderSlots();