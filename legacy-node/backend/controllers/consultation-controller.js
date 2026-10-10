const db = require('../config/database');
const { id } = require('../utils/ids');
const { cleanString, isValidDate } = require('../utils/validation');

const statuses=['submitted','reviewing','in_progress','completed','cancelled'];
const requestStatuses=['pending','approved','rejected'];

function makeProjectCode(createdAt, projectId){
  const date=new Date(createdAt).toISOString().slice(0,10).replace(/-/g,'');
  return `WRBN-${date}-${projectId.replace(/-/g,'').slice(0,6).toUpperCase()}`;
}

function getOwned(req,idValue){
  return db.prepare(`SELECT c.*,s.name service_name,s.price_label FROM consultations c JOIN services s ON s.id=c.service_id WHERE c.id=? AND c.user_id=?`).get(idValue,req.user.id);
}

function create(req,res){
 const serviceId=cleanString(req.body.serviceId,100), title=cleanString(req.body.title,160), description=cleanString(req.body.description,5000), scheduledAt=cleanString(req.body.scheduledAt,80), notes=cleanString(req.body.notes,3000);
 if(!serviceId || title.length<3 || description.length<5) return res.status(400).json({message:'Paket, judul, dan deskripsi wajib diisi.'});
 if(scheduledAt && !isValidDate(scheduledAt)) return res.status(400).json({message:'Jadwal tidak valid.'});
 const service=db.prepare('SELECT id,name FROM services WHERE id=? AND active=1').get(serviceId);
 if(!service)return res.status(404).json({message:'Paket tidak ditemukan.'});
 const paid=db.prepare(`SELECT id FROM payments WHERE user_id=? AND service_id=? AND status='paid' ORDER BY paid_at DESC LIMIT 1`).get(req.user.id,serviceId);
 if(!paid)return res.status(403).json({message:'Paket belum dibayar. Selesaikan payment terlebih dahulu.'});
 const used=db.prepare('SELECT id FROM consultations WHERE payment_id=? LIMIT 1').get(paid.id);
 if(used)return res.status(409).json({message:'Pembayaran ini sudah dipakai untuk project. Buat payment baru untuk project berikutnya.'});
 const now=new Date().toISOString(), projectId=id(), projectCode=makeProjectCode(now,projectId);
 const consultation={id:projectId,project_code:projectCode,user_id:req.user.id,service_id:serviceId,payment_id:paid.id,title,description,scheduled_at:scheduledAt||null,meeting_url:null,consultant_name:null,contact_person:null,notes,status:'submitted',created_at:now,updated_at:now};
 const tx=db.transaction(()=>{
   db.prepare(`INSERT INTO consultations(id,project_code,user_id,service_id,payment_id,title,description,scheduled_at,meeting_url,consultant_name,contact_person,notes,status,created_at,updated_at) VALUES(@id,@project_code,@user_id,@service_id,@payment_id,@title,@description,@scheduled_at,@meeting_url,@consultant_name,@contact_person,@notes,@status,@created_at,@updated_at)`).run(consultation);
   db.prepare(`INSERT INTO project_updates(id,consultation_id,title,message,status,created_at) VALUES(?,?,?,?,?,?)`).run(id(),projectId,'Project diterima','Pengajuan project sudah diterima dan menunggu ditinjau oleh konsultan.', 'submitted', now);
 });
 tx();
 res.status(201).json({consultation});
}

function list(req,res){
 const rows=db.prepare(`SELECT c.*,s.name service_name,s.price_label,
   (SELECT pu.title FROM project_updates pu WHERE pu.consultation_id=c.id ORDER BY pu.created_at DESC LIMIT 1) AS latest_update_title,
   (SELECT pu.message FROM project_updates pu WHERE pu.consultation_id=c.id ORDER BY pu.created_at DESC LIMIT 1) AS latest_update_message
   FROM consultations c JOIN services s ON s.id=c.service_id WHERE c.user_id=? ORDER BY c.created_at DESC`).all(req.user.id);
 res.json({consultations:rows});
}

function getOne(req,res){
 const c=getOwned(req,req.params.id);
 if(!c)return res.status(404).json({message:'Project tidak ditemukan.'});
 const updates=db.prepare('SELECT id,title,message,status,created_at FROM project_updates WHERE consultation_id=? ORDER BY created_at DESC').all(c.id);
 const files=db.prepare('SELECT id,original_name,mime_type,size_bytes,created_at FROM consultation_files WHERE consultation_id=? ORDER BY created_at DESC').all(c.id);
 const reschedules=db.prepare('SELECT id,current_scheduled_at,requested_scheduled_at,reason,status,created_at,updated_at FROM reschedule_requests WHERE consultation_id=? ORDER BY created_at DESC').all(c.id);
 const upgrades=db.prepare(`SELECT ur.id,ur.reason,ur.status,ur.created_at,ur.updated_at, s.name requested_service_name,s.price_label requested_price_label FROM upgrade_requests ur JOIN services s ON s.id=ur.requested_service_id WHERE ur.consultation_id=? ORDER BY ur.created_at DESC`).all(c.id);
 res.json({consultation:c,updates,files,reschedules,upgrades});
}

function cancel(req,res){
 const c=getOwned(req,req.params.id);
 if(!c)return res.status(404).json({message:'Project tidak ditemukan.'});
 if(['cancelled','completed'].includes(c.status))return res.status(409).json({message:'Project ini sudah tidak dapat dibatalkan.'});
 const now=new Date().toISOString();
 const tx=db.transaction(()=>{
   db.prepare('UPDATE consultations SET status=?,updated_at=? WHERE id=?').run('cancelled',now,c.id);
   db.prepare(`INSERT INTO project_updates(id,consultation_id,title,message,status,created_at) VALUES(?,?,?,?,?,?)`).run(id(),c.id,'Project dibatalkan','Project dibatalkan oleh client.', 'cancelled', now);
 });
 tx();
 res.json({consultation:getOwned(req,c.id)});
}

function requestReschedule(req,res){
 const c=getOwned(req,req.params.id);
 if(!c)return res.status(404).json({message:'Project tidak ditemukan.'});
 if(['cancelled','completed'].includes(c.status))return res.status(409).json({message:'Project ini tidak dapat dijadwalkan ulang.'});
 const requested=cleanString(req.body.requestedScheduledAt,80), reason=cleanString(req.body.reason,1000);
 if(!requested || !isValidDate(requested))return res.status(400).json({message:'Tanggal/jam reschedule tidak valid.'});
 const pending=db.prepare("SELECT id FROM reschedule_requests WHERE consultation_id=? AND status='pending' LIMIT 1").get(c.id);
 if(pending)return res.status(409).json({message:'Masih ada pengajuan reschedule yang menunggu diproses.'});
 const now=new Date().toISOString();
 db.prepare(`INSERT INTO reschedule_requests(id,consultation_id,user_id,current_scheduled_at,requested_scheduled_at,reason,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)`).run(id(),c.id,req.user.id,c.scheduled_at||null,requested,reason||null,'pending',now,now);
 res.status(201).json({message:'Pengajuan reschedule berhasil dikirim dan menunggu konfirmasi.',status:'pending'});
}

function requestUpgrade(req,res){
 const c=getOwned(req,req.params.id);
 if(!c)return res.status(404).json({message:'Project tidak ditemukan.'});
 if(['cancelled','completed'].includes(c.status))return res.status(409).json({message:'Project ini tidak dapat diajukan upgrade.'});
 const requestedServiceId=cleanString(req.body.requestedServiceId,100), reason=cleanString(req.body.reason,1000);
 const target=db.prepare('SELECT id,name FROM services WHERE id=? AND active=1').get(requestedServiceId);
 if(!target)return res.status(404).json({message:'Paket tujuan tidak ditemukan.'});
 if(target.id===c.service_id)return res.status(400).json({message:'Paket tujuan sama dengan paket saat ini.'});
 const pending=db.prepare("SELECT id FROM upgrade_requests WHERE consultation_id=? AND status='pending' LIMIT 1").get(c.id);
 if(pending)return res.status(409).json({message:'Masih ada pengajuan upgrade yang menunggu diproses.'});
 const now=new Date().toISOString();
 db.prepare(`INSERT INTO upgrade_requests(id,consultation_id,user_id,current_service_id,requested_service_id,reason,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)`).run(id(),c.id,req.user.id,c.service_id,target.id,reason||null,'pending',now,now);
 res.status(201).json({message:'Pengajuan upgrade berhasil dikirim dan menunggu konfirmasi.',status:'pending'});
}

function update(req,res){
 const allowed=cleanString(req.body.status,30);
 if(allowed==='cancelled')return cancel(req,res);
 return res.status(403).json({message:'Status project dikelola oleh konsultan.'});
}

module.exports={create,list,getOne,update,requestReschedule,requestUpgrade};
