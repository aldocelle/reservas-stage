import bcrypt from 'bcryptjs';
import dotenv from 'dotenv';
import { query,pool } from '../db.js';
dotenv.config();

const email=process.env.ADMIN_EMAIL;
const password=process.env.ADMIN_PASSWORD;
if(!email||!password) throw new Error('Define ADMIN_EMAIL y ADMIN_PASSWORD en .env');
const hash=await bcrypt.hash(password,12);
await query(`INSERT INTO admins(email,password_hash) VALUES($1,$2) ON CONFLICT(email) DO UPDATE SET password_hash=EXCLUDED.password_hash,active=true`,[email,hash]);
console.log('Administrador creado/actualizado:',email);
await pool.end();
