import fs from 'fs/promises';
import { pool } from '../db.js';
const sql=await fs.readFile(new URL('../schema.sql',import.meta.url),'utf8');
await pool.query(sql);
console.log('Base de datos inicializada.');
await pool.end();
