import { Router } from 'express';
import { z } from 'zod';
import { query } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router=Router();
router.use(requireAuth);

router.get('/dashboard',async(req,res,next)=>{
  try{
    const [today,confirmed,total,slots]=await Promise.all([
      query(`SELECT COUNT(*)::int count FROM reservations WHERE reservation_date=CURRENT_DATE AND status='confirmed'`),
      query(`SELECT COUNT(*)::int count FROM reservations WHERE status='confirmed'`),
      query(`SELECT COUNT(*)::int count FROM reservations`),
      query(`SELECT ts.id,st.label,TO_CHAR(ts.start_time,'HH24:MI') start_time,TO_CHAR(ts.end_time,'HH24:MI') end_time,ts.capacity FROM time_slots ts JOIN schedule_templates st ON st.id=ts.template_id WHERE ts.active=true ORDER BY st.weekday,ts.start_time`)
    ]);
    res.json({today:today.rows[0].count,confirmed:confirmed.rows[0].count,total:total.rows[0].count,slots:slots.rows});
  }catch(error){next(error)}
});

router.get('/schedules',async(req,res,next)=>{
  try{
    const result=await query(`SELECT st.id,st.weekday,st.label,st.capacity,st.active,COALESCE(json_agg(json_build_object('id',ts.id,'startTime',TO_CHAR(ts.start_time,'HH24:MI'),'endTime',TO_CHAR(ts.end_time,'HH24:MI'),'capacity',ts.capacity,'active',ts.active) ORDER BY ts.start_time) FILTER (WHERE ts.id IS NOT NULL),'[]') slots FROM schedule_templates st LEFT JOIN time_slots ts ON ts.template_id=st.id GROUP BY st.id ORDER BY st.weekday`);
    res.json({schedules:result.rows});
  }catch(error){next(error)}
});

router.patch('/schedules/:id',async(req,res,next)=>{
  try{
    const data=z.object({capacity:z.number().int().min(1).max(1000).optional(),active:z.boolean().optional()}).parse(req.body);
    const result=await query('UPDATE schedule_templates SET capacity=COALESCE($1,capacity),active=COALESCE($2,active) WHERE id=$3 RETURNING *',[data.capacity??null,data.active??null,req.params.id]);
    if(!result.rowCount) return res.status(404).json({error:'Agenda no encontrada'});
    res.json({schedule:result.rows[0]});
  }catch(error){next(error)}
});

router.patch('/slots/:id',async(req,res,next)=>{
  try{
    const data=z.object({capacity:z.number().int().min(1).max(1000).optional(),active:z.boolean().optional()}).parse(req.body);
    const result=await query('UPDATE time_slots SET capacity=COALESCE($1,capacity),active=COALESCE($2,active) WHERE id=$3 RETURNING *',[data.capacity??null,data.active??null,req.params.id]);
    if(!result.rowCount) return res.status(404).json({error:'Horario no encontrado'});
    res.json({slot:result.rows[0]});
  }catch(error){next(error)}
});

export default router;
