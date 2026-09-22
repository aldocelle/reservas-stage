import 'dotenv/config';
import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import path from 'path';
import { fileURLToPath } from 'url';
import authRoutes from './routes/auth.js';
import availabilityRoutes from './routes/availability.js';
import reservationRoutes from './routes/reservations.js';
import adminRoutes from './routes/admin.js';

const __dirname=path.dirname(fileURLToPath(import.meta.url));
const app=express();
const port=Number(process.env.PORT||3000);

app.use(helmet({contentSecurityPolicy:false}));
app.use(cors({origin:process.env.CORS_ORIGIN?.split(',').map(x=>x.trim())||true}));
app.use(express.json({limit:'100kb'}));

const publicLimiter=rateLimit({windowMs:15*60*1000,max:150,standardHeaders:true,legacyHeaders:false});
app.use('/api/auth',rateLimit({windowMs:15*60*1000,max:20,standardHeaders:true,legacyHeaders:false}));
app.use('/api/reservations',publicLimiter);

app.get('/api/health',async(req,res)=>res.json({ok:true,service:'reservas-stage',timestamp:new Date().toISOString()}));
app.use('/api/auth',authRoutes);
app.use('/api/availability',availabilityRoutes);
app.use('/api/reservations',reservationRoutes);
app.use('/api/admin',adminRoutes);

app.use(express.static(__dirname));
app.get('*',(req,res)=>{
  if(req.path.startsWith('/api/')) return res.status(404).json({error:'Endpoint no encontrado'});
  res.sendFile(path.join(__dirname,'index.html'));
});

app.use((error,req,res,next)=>{
  console.error(error);
  if(error.name==='ZodError') return res.status(400).json({error:'Datos inválidos',details:error.issues});
  const status=error.status||500;
  res.status(status).json({error:status===500?'Error interno del servidor':error.message});
});

app.listen(port,()=>console.log(`Viña Stage backend escuchando en http://localhost:${port}`));
