const fs=require('fs');
const db=require('../config/database');
const {id}=require('../utils/ids');
const {cleanString,isValidDate}=require('../utils/validation');

const statuses=['submitted','reviewing','in_progress','completed','cancelled'];
const requestStatuses=['pending','approved','rejected'];

function dashboard(req,res){
  const stats={
    users:db.prepare('SELECT COUNT(*) count FROM users').get().count,
    projects:db.prepare('SELECT COUNT(*) count FROM consultations').get().count,
    active:db.prepare("SELECT COUNT(*) count FROM consultations WHERE status IN ('submitted','reviewing','in_progress')").get().count,
    completed:db.prepare("SELECT COUNT(*) count FROM consultations WHERE status='completed'").get().count,
    pendingReschedules:db.prepare("SELECT COUNT(*) count FROM reschedule_requests WHERE status='pending'").get().count,
    pendingUpgrades:db.prepare("SELECT COUNT(*) count FROM upgrade_requests WHERE status='pending'").get().count,
    pendingPayments:db.prepare("SELECT COUNT(*) count FROM payments WHERE status='pending'").get().count,
    messages:db.prepare('SELECT COUNT(*) count FROM contact_messages').get().count
  };
  res.json({stats});
}

function listUsers(req,res){
  const users=db.prepare(`SELECT u.id,u.name,u.email,u.whatsapp,u.created_at,
    (SELECT COUNT(*) FROM consultations c WHERE c.user_id=u.id) AS consultation_count,
    (SELECT COUNT(*) FROM payments p WHERE p.user_id=u.id) AS payment_count
    FROM users u ORDER BY u.created_at DESC`).all();
  res.json({users});
}

function deleteUsers(req,res){
  const ids=Array.isArray(req.body?.ids)?[...new Set(req.body.ids.map(x=>String(x).trim()).filter(Boolean))]:[];
  if(!ids.length)return res.status(400).json({message:'Pilih minimal satu akun.'});
  const ph=ids.map(()=>'?').join(','), users=db.prepare(`SELECT id,email FROM users WHERE id IN (${ph})`).all(...ids);
  if(!users.length)return res.status(404).json({message:'Akun yang dipilih tidak ditemukan.'});
  const userIds=users.map(u=>u.id), uph=userIds.map(()=>'?').join(','), files=db.prepare(`SELECT cf.storage_path FROM consultation_files cf JOIN consultations c ON c.id=cf.consultation_id WHERE c.user_id IN (${uph})`).all(...userIds);
  db.transaction(()=>db.prepare(`DELETE FROM users WHERE id IN (${uph})`).run(...userIds))();
  for(const file of files){if(file.storage_path)try{if(fs.existsSync(file.storage_path))fs.unlinkSync(file.storage_path)}catch(e){console.warn(e.message)}}
  res.json({message:`${users.length} akun berhasil dihapus.`,deleted:users.map(u=>u.email)});
}

function listProjects(req,res){
  const rows=db.prepare(`SELECT c.id,c.project_code,c.title,c.status,c.scheduled_at,c.meeting_url,c.consultant_name,c.contact_person,c.notes,c.created_at,c.updated_at,
    u.name user_name,u.email user_email,u.whatsapp user_whatsapp,s.name service_name,s.price_label,
    (SELECT pu.title FROM project_updates pu WHERE pu.consultation_id=c.id ORDER BY pu.created_at DESC LIMIT 1) latest_update_title,
    (SELECT pu.message FROM project_updates pu WHERE pu.consultation_id=c.id ORDER BY pu.created_at DESC LIMIT 1) latest_update_message
    FROM consultations c JOIN users u ON u.id=c.user_id JOIN services s ON s.id=c.service_id ORDER BY c.updated_at DESC`).all();
  res.json({projects:rows});
}

function getProject(req,res){
  const c=db.prepare(`SELECT c.*,u.name user_name,u.email user_email,u.whatsapp user_whatsapp,s.name service_name,s.price_label FROM consultations c JOIN users u ON u.id=c.user_id JOIN services s ON s.id=c.service_id WHERE c.id=?`).get(req.params.id);
  if(!c)return res.status(404).json({message:'Project tidak ditemukan.'});
  const updates=db.prepare('SELECT id,title,message,status,created_at FROM project_updates WHERE consultation_id=? ORDER BY created_at DESC').all(c.id);
  const files=db.prepare('SELECT id,original_name,mime_type,size_bytes,created_at FROM consultation_files WHERE consultation_id=? ORDER BY created_at DESC').all(c.id);
  const reschedules=db.prepare('SELECT r.*,u.name user_name,u.email user_email FROM reschedule_requests r JOIN users u ON u.id=r.user_id WHERE r.consultation_id=? ORDER BY r.created_at DESC').all(c.id);
  const upgrades=db.prepare(`SELECT r.*,a.name current_service_name,a.price_label current_price_label,b.name requested_service_name,b.price_label requested_price_label FROM upgrade_requests r JOIN services a ON a.id=r.current_service_id JOIN services b ON b.id=r.requested_service_id WHERE r.consultation_id=? ORDER BY r.created_at DESC`).all(c.id);
  const payments=db.prepare(`SELECT p.*,s.name service_name FROM payments p JOIN services s ON s.id=p.service_id WHERE p.user_id=? ORDER BY p.created_at DESC`).all(c.user_id);
  res.json({project:c,updates,files,reschedules,upgrades,payments});
}

function updateProject(req,res){
  const c=db.prepare('SELECT * FROM consultations WHERE id=?').get(req.params.id);
  if(!c)return res.status(404).json({message:'Project tidak ditemukan.'});
  const status=req.body.status===undefined?c.status:cleanString(req.body.status,30);
  const scheduledAt=req.body.scheduledAt===undefined?c.scheduled_at:cleanString(req.body.scheduledAt,80);
  const meetingUrl=req.body.meetingUrl===undefined?c.meeting_url:cleanString(req.body.meetingUrl,500);
  const consultantName=req.body.consultantName===undefined?c.consultant_name:cleanString(req.body.consultantName,160);
  const contactPerson=req.body.contactPerson===undefined?c.contact_person:cleanString(req.body.contactPerson,160);
  const notes=req.body.notes===undefined?c.notes:cleanString(req.body.notes,3000);
  if(!statuses.includes(status))return res.status(400).json({message:'Status project tidak valid.'});
  if(scheduledAt && !isValidDate(scheduledAt))return res.status(400).json({message:'Jadwal tidak valid.'});
  const now=new Date().toISOString();
  const changed=[];
  if(status!==c.status)changed.push(`Status: ${status}`);
  if(scheduledAt!==c.scheduled_at)changed.push(`Jadwal: ${scheduledAt||'dihapus'}`);
  if(meetingUrl!==c.meeting_url)changed.push('Link meeting diperbarui');
  if(consultantName!==c.consultant_name)changed.push(`Konsultan: ${consultantName||'belum ditentukan'}`);
  if(contactPerson!==c.contact_person)changed.push(`Kontak: ${contactPerson||'belum ditentukan'}`);
  db.transaction(()=>{
    db.prepare(`UPDATE consultations SET status=?,scheduled_at=?,meeting_url=?,consultant_name=?,contact_person=?,notes=?,updated_at=? WHERE id=?`).run(status,scheduledAt||null,meetingUrl||null,consultantName||null,contactPerson||null,notes||null,now,c.id);
    if(changed.length)db.prepare(`INSERT INTO project_updates(id,consultation_id,title,message,status,created_at) VALUES(?,?,?,?,?,?)`).run(id(),c.id,'Project diperbarui oleh admin',changed.join(' · '),status,now);
  })();
  res.json({message:'Project berhasil diperbarui.'});
}

function addProgress(req,res){
  const c=db.prepare('SELECT id,status FROM consultations WHERE id=?').get(req.params.id);
  if(!c)return res.status(404).json({message:'Project tidak ditemukan.'});
  const title=cleanString(req.body.title,160), message=cleanString(req.body.message,5000), status=req.body.status?cleanString(req.body.status,30):c.status;
  if(title.length<2||message.length<2)return res.status(400).json({message:'Judul dan isi progress wajib diisi.'});
  if(!statuses.includes(status))return res.status(400).json({message:'Status progress tidak valid.'});
  const now=new Date().toISOString();
  db.transaction(()=>{
    db.prepare('UPDATE consultations SET status=?,updated_at=? WHERE id=?').run(status,now,c.id);
    db.prepare('INSERT INTO project_updates(id,consultation_id,title,message,status,created_at) VALUES(?,?,?,?,?,?)').run(id(),c.id,title,message,status,now);
  })();
  res.status(201).json({message:'Progress berhasil dikirim ke Client Area.'});
}

function resolveReschedule(req,res){
  const action=cleanString(req.body.action,20); if(!requestStatuses.includes(action)||action==='pending')return res.status(400).json({message:'Aksi harus approved atau rejected.'});
  const r=db.prepare('SELECT * FROM reschedule_requests WHERE id=?').get(req.params.id); if(!r)return res.status(404).json({message:'Pengajuan reschedule tidak ditemukan.'});
  if(r.status!=='pending')return res.status(409).json({message:'Pengajuan ini sudah diproses.'});
  const c=db.prepare('SELECT * FROM consultations WHERE id=?').get(r.consultation_id); const now=new Date().toISOString();
  db.transaction(()=>{
    db.prepare('UPDATE reschedule_requests SET status=?,updated_at=? WHERE id=?').run(action,now,r.id);
    if(action==='approved')db.prepare('UPDATE consultations SET scheduled_at=?,updated_at=? WHERE id=?').run(r.requested_scheduled_at,now,c.id);
    db.prepare('INSERT INTO project_updates(id,consultation_id,title,message,status,created_at) VALUES(?,?,?,?,?,?)').run(id(),c.id,action==='approved'?'Reschedule disetujui':'Reschedule ditolak',action==='approved'?`Jadwal baru: ${r.requested_scheduled_at}`:'Pengajuan perubahan jadwal ditolak oleh admin.',c.status,now);
  })();
  res.json({message:`Reschedule ${action==='approved'?'disetujui':'ditolak'}.`});
}

function resolveUpgrade(req,res){
  const action=cleanString(req.body.action,20); if(!['approved','rejected'].includes(action))return res.status(400).json({message:'Aksi harus approved atau rejected.'});
  const r=db.prepare('SELECT * FROM upgrade_requests WHERE id=?').get(req.params.id); if(!r)return res.status(404).json({message:'Pengajuan upgrade tidak ditemukan.'});
  if(r.status!=='pending')return res.status(409).json({message:'Pengajuan ini sudah diproses.'});
  const c=db.prepare('SELECT * FROM consultations WHERE id=?').get(r.consultation_id); const target=db.prepare('SELECT name FROM services WHERE id=?').get(r.requested_service_id); const now=new Date().toISOString();
  db.transaction(()=>{
    db.prepare('UPDATE upgrade_requests SET status=?,updated_at=? WHERE id=?').run(action,now,r.id);
    if(action==='approved')db.prepare('UPDATE consultations SET service_id=?,updated_at=? WHERE id=?').run(r.requested_service_id,now,c.id);
    db.prepare('INSERT INTO project_updates(id,consultation_id,title,message,status,created_at) VALUES(?,?,?,?,?,?)').run(id(),c.id,action==='approved'?'Upgrade disetujui':'Upgrade ditolak',action==='approved'?`Paket project diperbarui menjadi ${target.name}.`:'Pengajuan upgrade ditolak oleh admin.',c.status,now);
  })();
  res.json({message:`Upgrade ${action==='approved'?'disetujui':'ditolak'}.`});
}


function downloadFile(req,res){
  const f=db.prepare('SELECT original_name,storage_path FROM consultation_files WHERE id=?').get(req.params.id);
  if(!f)return res.status(404).json({message:'File tidak ditemukan.'});
  if(!f.storage_path || !fs.existsSync(f.storage_path))return res.status(404).json({message:'File tidak tersedia di server.'});
  return res.download(f.storage_path,f.original_name);
}

function listMessages(req,res){res.json({messages:db.prepare('SELECT * FROM contact_messages ORDER BY created_at DESC').all()});}
function listRequests(req,res){
  const reschedules=db.prepare(`SELECT r.*,c.project_code,c.title,u.name user_name,u.email user_email FROM reschedule_requests r JOIN consultations c ON c.id=r.consultation_id JOIN users u ON u.id=r.user_id WHERE r.status='pending' ORDER BY r.created_at DESC`).all();
  const upgrades=db.prepare(`SELECT r.*,c.project_code,c.title,u.name user_name,u.email user_email,a.name current_service_name,b.name requested_service_name,b.price_label requested_price_label FROM upgrade_requests r JOIN consultations c ON c.id=r.consultation_id JOIN users u ON u.id=r.user_id JOIN services a ON a.id=r.current_service_id JOIN services b ON b.id=r.requested_service_id WHERE r.status='pending' ORDER BY r.created_at DESC`).all();
  res.json({reschedules,upgrades});
}
function listPayments(req,res){res.json({payments:db.prepare(`SELECT p.*,u.name user_name,u.email user_email,s.name service_name FROM payments p JOIN users u ON u.id=p.user_id JOIN services s ON s.id=p.service_id ORDER BY p.created_at DESC`).all()});}
function updatePayment(req,res){
  const status=cleanString(req.body.status,20); if(!['pending','paid','failed','expired'].includes(status))return res.status(400).json({message:'Status payment tidak valid.'});
  const p=db.prepare('SELECT id FROM payments WHERE id=?').get(req.params.id); if(!p)return res.status(404).json({message:'Payment tidak ditemukan.'});
  const now=new Date().toISOString(); db.prepare('UPDATE payments SET status=?,paid_at=?,updated_at=? WHERE id=?').run(status,status==='paid'?now:null,now,p.id); res.json({message:'Status payment diperbarui.'});
}

module.exports={dashboard,listUsers,deleteUsers,listProjects,getProject,updateProject,addProgress,resolveReschedule,resolveUpgrade,downloadFile,listMessages,listRequests,listPayments,updatePayment};
