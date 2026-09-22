import { Router } from 'express';
import { query } from '../db.js';
import { z } from 'zod';

const router=Router();
const dateSchema=z.string().date();

router.get('/',async(req,res,next)=>{
  try{
    const date=req.query.date ? dateSchema.parse(req.query.date) : new Date().toISOString().slice(0,10);
    const result=await query(`
      SELECT st.id template_id,st.weekday,st.label,ts.id slot_id,
             TO_CHAR(ts.start_time,'HH24:MI') start_time,TO_CHAR(ts.end_time,'HH24:MI') end_time,
             ts.capacity,
             COUNT(r.id) FILTER (WHERE r.status IN ('confirmed','attended'))::int reserved
      FROM schedule_templates st
      JOIN time_slots ts ON ts.template_id=st.id
      LEFT JOIN reservations r ON r.slot_id=ts.id AND r.reservation_date=$1::date
      WHERE st.active=true AND ts.active=true AND EXTRACT(ISODOW FROM $1::date)=st.weekday
      GROUP BY st.id,ts.id
      ORDER BY ts.start_time
    `,[date]);
    const rows=result.rows.map(x=>({...x,reserved:Number(x.reserved),available:Math.max(0,x.capacity-x.reserved)}));
    res.json({date,weekday:rows[0]?.weekday||null,day:rows[0]?.label||null,slots:rows});
  }catch(error){next(error)}
});
export default router;
