const express=require('express');
const path=require('path');
const fs=require('fs');
const helmet=require('helmet');
const cors=require('cors');
const cookieParser=require('cookie-parser');
const rateLimit=require('express-rate-limit');
const env=require('./config/env');
const db=require('./config/database');
const {errorHandler}=require('./middleware/error');
const {requireAuth}=require('./middleware/auth');

// Always prepare the database before mounting routes. This makes `npm start`
// safe on a fresh install instead of requiring a separate db:init step.
require('./utils/init-db');

const authRoutes=require('./routes/auth');
const serviceRoutes=require('./routes/services');
const paymentRoutes=require('./routes/payments');
const consultationRoutes=require('./routes/consultations');
const clientRoutes=require('./routes/client');
const contactRoutes=require('./routes/contact');
const reviewRoutes=require('./routes/reviews');
const adminRoutes=require('./routes/admin');

const app=express();
app.set('trust proxy', env.nodeEnv === 'production' ? 1 : false);
const publicDir=path.resolve(__dirname,'../public');
const uploadDir=path.resolve(env.uploadDir);
fs.mkdirSync(uploadDir,{recursive:true});

app.disable('x-powered-by');
app.use(helmet({
  crossOriginResourcePolicy:{policy:'same-site'},
  contentSecurityPolicy:false
}));
const allowedOrigins = new Set([env.frontendOrigin, 'http://localhost:3000', 'http://127.0.0.1:3000']);
app.use(cors({
  origin:(origin,cb)=>{
    if(!origin || env.frontendOrigin==='*' || allowedOrigins.has(origin)) return cb(null,true);
    return cb(new Error('Origin tidak diizinkan oleh CORS.'));
  },
  credentials:true
}));
app.use(express.json({limit:'1mb'}));
app.use(express.urlencoded({extended:false,limit:'1mb'}));
app.use(cookieParser());

const authLimiter=rateLimit({windowMs:15*60*1000,max:30,standardHeaders:true,legacyHeaders:false,message:{message:'Terlalu banyak percobaan. Coba lagi nanti.'}});
const adminLoginLimiter=rateLimit({windowMs:15*60*1000,max:15,standardHeaders:true,legacyHeaders:false,message:{message:'Terlalu banyak percobaan login admin. Coba lagi nanti.'}});
app.use('/api/auth',authLimiter);
app.use('/api',rateLimit({windowMs:60*1000,max:120,standardHeaders:true,legacyHeaders:false}));

app.get('/api/health',(req,res)=>{
  try { db.prepare('SELECT 1').get(); return res.json({ok:true,service:'wrbn-backend',database:'ok'}); }
  catch { return res.status(503).json({ok:false,service:'wrbn-backend',database:'error'}); }
});
app.use('/api/auth',authRoutes);
app.use('/api/services',serviceRoutes);
app.use('/api/payments',paymentRoutes);
app.use('/api/client',clientRoutes);
app.use('/api/consultations',consultationRoutes);
app.use('/api/contact',contactRoutes);
app.use('/api/reviews',reviewRoutes);
app.use('/api/admin/login', adminLoginLimiter);
app.use('/api/admin',adminRoutes);

app.get('/admin.html',(req,res)=>res.sendFile(path.join(publicDir,'admin.html')));

app.get('/client.html', requireAuth, (req,res)=>{
  // Client Area dapat dibuka segera setelah login.
  // User tanpa payment tetap bisa melihat dashboard kosong dan mengajukan project setelah payment tersedia.
  return res.sendFile(path.join(publicDir,'client.html'));
});

app.use(express.static(publicDir,{index:'index.html'}));
app.use((req,res,next)=>{
 if(req.method==='GET' && !req.path.startsWith('/api/')) return res.sendFile(path.join(publicDir,'index.html'));
 next();
});
app.use(errorHandler);

const server=app.listen(env.port,()=>console.log(`WRBN running at http://localhost:${env.port}`));

function shutdown(signal){
  console.log(`${signal}: shutting down WRBN...`);
  server.close(()=>{
    try{ db.close(); }catch{}
    process.exit(0);
  });
  setTimeout(()=>process.exit(1),10000).unref();
}
process.on('SIGTERM',()=>shutdown('SIGTERM'));
process.on('SIGINT',()=>shutdown('SIGINT'));
