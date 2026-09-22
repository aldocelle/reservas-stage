import { Router } from 'express';
import { query } from '../db.js';

const router=Router();

router.get('/',async(req,res,next)=>{
  try{
    const date=req.query.date;
    const from=req.query.from;
    const to=req.query.to;
    const params=[];
    let where='st.active=true AND ts.active=true';
    if(date){params.push(date);where+=' AND EXTRACT(ISODOW FROM $'+params.length+'::date)=st.weekday';}
    const result=await query(`
      SELECT st.id template_id,st.weekday,st.label,ts.id slot_id,
             TO_CHAR(ts.start_time,'HH24:MI') start_time,TO_CHAR(ts.end_time,'HH24:MI') end_time,
             ts.capacity,
             COUNT(r.id) FILTER (WHERE r.status IN ('confirmed','attended'))::int reserved
      FROM schedule_templates st
      JOIN time_slots ts ON ts.template_id=st.id
      LEFT JOIN reservations r ON r.slot_id=ts.id
        AND r.reservation_date=COALESCE($1::date,CURRENT_DATE + ((st.weekday-EXTRACT(ISODOW FROM CURRENT_DATE)+7)::int))
      WHERE ${where}
      GROUP BY st.id,ts.id
      ORDER BY st.weekday,ts.start_time
    `,date?[date]:[]);
    const rows=result.rows.map(x=>({...x,available:Math.max(0,x.capacity-x.reserved)}));
    res.json({date:date||null,days:rows});
  }catch(error){next(error)}
});

export default router;
