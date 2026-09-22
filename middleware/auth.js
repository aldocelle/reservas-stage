import jwt from 'jsonwebtoken';
import { query } from '../db.js';

export async function requireAuth(req,res,next){
  try{
    const header=req.headers.authorization || '';
    const token=header.startsWith('Bearer ') ? header.slice(7) : null;
    if(!token) return res.status(401).json({error:'No autenticado'});
    const payload=jwt.verify(token, process.env.JWT_SECRET);
    const result=await query('SELECT id,email,role,active FROM admins WHERE id=$1',[payload.sub]);
    const admin=result.rows[0];
    if(!admin || !admin.active) return res.status(401).json({error:'Sesión inválida'});
    req.admin=admin;
    next();
  }catch{
    res.status(401).json({error:'Token inválido o expirado'});
  }
}
