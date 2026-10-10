const db = require('../config/database');
const { id } = require('../utils/ids');
const { cleanString, isValidDate } = require('../utils/validation');
const { getPaymentService } = require('../services/payment-service');
const env = require('../config/env');
const { mirrorBooking } = require('../services/sheets-service');

function serialize(p){return {...p, amount:Number(p.amount)}}

async function create(req,res,next){
 try{
  const serviceId=cleanString(req.body.serviceId,100), scheduledAt=cleanString(req.body.scheduledAt,80), notes=cleanString(req.body.notes,2000), whatsapp=cleanString(req.body.whatsapp,30);
  if(!serviceId) return res.status(400).json({message:'Service wajib dipilih.'});
  if(scheduledAt && !isValidDate(scheduledAt)) return res.status(400).json({message:'Jadwal tidak valid.'});
  const service=db.prepare('SELECT * FROM services WHERE id=? AND active=1').get(serviceId);
  if(!service) return res.status(404).json({message:'Service tidak ditemukan.'});
  const now=new Date().toISOString(), payment={id:id(),user_id:req.user.id,service_id:service.id,amount:service.price,currency:'IDR',status:'pending',provider:env.paymentProvider,provider_reference:null,scheduled_at:scheduledAt||null,whatsapp,notes,created_at:now,updated_at:now,paid_at:null};
  db.prepare(`INSERT INTO payments(id,user_id,service_id,amount,currency,status,provider,provider_reference,scheduled_at,whatsapp,notes,created_at,updated_at,paid_at) VALUES(@id,@user_id,@service_id,@amount,@currency,@status,@provider,@provider_reference,@scheduled_at,@whatsapp,@notes,@created_at,@updated_at,@paid_at)`).run(payment);
  const provider=getPaymentService(env), created=await provider.createPayment(payment);
  db.prepare('UPDATE payments SET provider_reference=?,updated_at=? WHERE id=?').run(created.providerReference,now,payment.id);
  mirrorBooking({paymentId:payment.id,userId:req.user.id,email:req.user.email,nama:req.user.name,whatsapp,service:service.name,paket:service.name,jadwal:scheduledAt,catatan:notes,status:'pending'}).catch(()=>{});
  res.status(201).json({payment:serialize({...payment,provider_reference:created.providerReference})});
 }catch(e){next(e)}
}

function getOne(req,res){
 const p=db.prepare(`SELECT p.*,s.name service_name,s.price_label FROM payments p JOIN services s ON s.id=p.service_id WHERE p.id=? AND p.user_id=?`).get(req.params.id,req.user.id);
 if(!p)return res.status(404).json({message:'Payment tidak ditemukan.'});
 res.json({payment:serialize(p)});
}
function list(req,res){
 const payments=db.prepare(`SELECT p.id,p.amount,p.currency,p.status,p.provider,p.scheduled_at,p.created_at,p.updated_at,p.paid_at,s.id service_id,s.name service_name,s.price_label FROM payments p JOIN services s ON s.id=p.service_id WHERE p.user_id=? ORDER BY p.created_at DESC`).all(req.user.id);
 res.json({payments:payments.map(serialize)});
}
async function dummyPay(req,res,next){
 try{
  const p=db.prepare('SELECT * FROM payments WHERE id=? AND user_id=?').get(req.params.id,req.user.id);
  if(!p)return res.status(404).json({message:'Payment tidak ditemukan.'});
  if(p.status==='paid')return res.json({payment:serialize(p)});
  if(p.status!=='pending')return res.status(409).json({message:`Payment berstatus ${p.status}.`});
  const result=await getPaymentService(env).pay(p,cleanString(req.body.method,30));
  const now=new Date().toISOString();
  db.prepare('UPDATE payments SET status=?,provider_reference=?,updated_at=?,paid_at=? WHERE id=?').run(result.status,result.providerReference,now,result.status==='paid'?now:null,p.id);
  const updated=db.prepare('SELECT p.*,s.name service_name,s.price_label FROM payments p JOIN services s ON s.id=p.service_id WHERE p.id=?').get(p.id);
  mirrorBooking({paymentId:p.id,userId:req.user.id,email:req.user.email,status:result.status}).catch(()=>{});
  res.json({payment:serialize(updated)});
 }catch(e){next(e)}
}
module.exports={create,getOne,list,dummyPay};
