import { Router } from 'express';
import crypto from 'crypto';
import { z } from 'zod';
import { withTransaction, query } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router=Router();

const schema=z.object({
  date:z.string().date(),
  slotId:z.string().uuid(),
  firstName:z.string().trim().min(2).max(80),
  lastName:z.string().trim().min(2).max(80),
  whatsapp:z.string().trim().min(8).max(30),
  email:z.string().email().max(160).optional().or(z.literal('')),
  whatsappConsent:z.boolean().default(false)
});

function code(){return 'VS-'+crypto.randomBytes(4).toString('hex').toUpperCase()}

router.post('/',async(req,res,next)=>{
  try{
    const data=schema.parse(req.body);
    const result=await withTransaction(async(client)=>{
      const slotResult=await client.query(`
        SELECT ts.id,ts.capacity,st.weekday,st.active template_active,ts.active
        FROM time_slots ts JOIN schedule_templates st ON st.id=ts.template_id
        WHERE ts.id=$1 FOR UPDATE
      `,[data.slotId]);
      const slot=slotResult.rows[0];
      if(!slot || !slot.active || !slot.template_active) throw Object.assign(new Error('Horario no disponible'),{status:409});
      const day=(new Date(data.date+'T12:00:00Z').getUTCDay()+6)%7+1;
      if(day!==Number(slot.weekday)) throw Object.assign(new Error('El horario no corresponde al día seleccionado'),{status:409});
      const count=await client.query(`SELECT COUNT(*)::int reserved FROM reservations WHERE reservation_date=$1 AND slot_id=$2 AND status IN ('confirmed','attended')`,[data.date,data.slotId]);
      if(count.rows[0].reserved>=slot.capacity) throw Object.assign(new Error('No quedan cupos disponibles'),{status:409});
      let reservationCode=code();
      for(let i=0;i<3;i++){
        const exists=await client.query('SELECT 1 FROM reservations WHERE reservation_code=$1',[reservationCode]);
        if(!exists.rowCount) break;
        reservationCode=code();
      }
      const inserted=await client.query(`
        INSERT INTO reservations(reservation_code,reservation_date,slot_id,first_name,last_name,whatsapp,email,whatsapp_consent)
        VALUES($1,$2,$3,$4,$5,$6,$7,$8)
        RETURNING id,reservation_code,reservation_date,slot_id,first_name,last_name,whatsapp,email,status,created_at
      `,[reservationCode,data.date,data.slotId,data.firstName,data.lastName,data.whatsapp,data.email||null,data.whatsappConsent]);
      return inserted.rows[0];
    });
    res.status(201).json({reservation:result});
  }catch(error){next(error)}
});

router.get('/code/:code',async(req,res,next)=>{
  try{
    const result=await query(`
      SELECT r.reservation_code,r.reservation_date,r.first_name,r.last_name,r.whatsapp,r.email,r.status,
             TO_CHAR(ts.start_time,'HH24:MI') start_time,TO_CHAR(ts.end_time,'HH24:MI') end_time
      FROM reservations r JOIN time_slots ts ON ts.id=r.slot_id
      WHERE r.reservation_code=$1
    `,[req.params.code]);
    if(!result.rowCount) return res.status(404).json({error:'Reserva no encontrada'});
    res.json({reservation:result.rows[0]});
  }catch(error){next(error)}
});

router.patch('/:id/cancel',async(req,res,next)=>{
  try{
    const result=await query(`UPDATE reservations SET status='cancelled',cancelled_at=NOW(),updated_at=NOW() WHERE id=$1 AND status='confirmed' RETURNING id,reservation_code,status`,[req.params.id]);
    if(!result.rowCount) return res.status(404).json({error:'Reserva no encontrada o ya cancelada'});
    res.json({reservation:result.rows[0]});
  }catch(error){next(error)}
});

router.get('/',requireAuth,async(req,res,next)=>{
  try{
    const result=await query(`
      SELECT r.*,TO_CHAR(ts.start_time,'HH24:MI') start_time,TO_CHAR(ts.end_time,'HH24:MI') end_time
      FROM reservations r JOIN time_slots ts ON ts.id=r.slot_id
      WHERE ($1::date IS NULL OR r.reservation_date=$1)
      ORDER BY r.reservation_date DESC,ts.start_time,r.created_at DESC
      LIMIT 500
    `,[req.query.date||null]);
    res.json({reservations:result.rows});
  }catch(error){next(error)}
});

router.patch('/:id/status',requireAuth,async(req,res,next)=>{
  try{
    const status=z.enum(['confirmed','cancelled','attended','no_show']).parse(req.body.status);
    const result=await query('UPDATE reservations SET status=$1,updated_at=NOW(),cancelled_at=CASE WHEN $1=\'cancelled\' THEN NOW() ELSE cancelled_at END WHERE id=$2 RETURNING *',[status,req.params.id]);
    if(!result.rowCount) return res.status(404).json({error:'Reserva no encontrada'});
    await query('INSERT INTO audit_logs(admin_id,action,entity,entity_id,metadata) VALUES($1,$2,$3,$4,$5)',[req.admin.id,'status_change','reservation',req.params.id,JSON.stringify({status})]);
    res.json({reservation:result.rows[0]});
  }catch(error){next(error)}
});

export default router;
