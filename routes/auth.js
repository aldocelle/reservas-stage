import { Router } from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { z } from 'zod';
import { query } from '../db.js';

const router=Router();
const loginSchema=z.object({email:z.string().email().max(160),password:z.string().min(8).max(200)});

router.post('/login',async(req,res,next)=>{
  try{
    const data=loginSchema.parse(req.body);
    const result=await query('SELECT id,email,password_hash,role,active FROM admins WHERE LOWER(email)=LOWER($1)',[data.email]);
    const admin=result.rows[0];
    if(!admin || !admin.active || !(await bcrypt.compare(data.password,admin.password_hash))){
      return res.status(401).json({error:'Credenciales incorrectas'});
    }
    const token=jwt.sign({sub:admin.id,role:admin.role},process.env.JWT_SECRET,{expiresIn:'8h'});
    res.json({token,admin:{id:admin.id,email:admin.email,role:admin.role}});
  }catch(error){next(error)}
});

export default router;
