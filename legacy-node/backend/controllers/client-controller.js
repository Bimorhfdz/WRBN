const db = require('../config/database');
function dashboard(req,res){
 const paid=db.prepare(`SELECT COUNT(*) count FROM payments WHERE user_id=? AND status='paid'`).get(req.user.id).count;
 const pending=db.prepare(`SELECT COUNT(*) count FROM payments WHERE user_id=? AND status='pending'`).get(req.user.id).count;
 const consultations=db.prepare('SELECT COUNT(*) count FROM consultations WHERE user_id=?').get(req.user.id).count;
 const latest=db.prepare(`SELECT c.id,c.title,c.status,c.updated_at,s.name service_name FROM consultations c JOIN services s ON s.id=c.service_id WHERE c.user_id=? ORDER BY c.updated_at DESC LIMIT 5`).all(req.user.id);
 res.json({user:req.user,stats:{paidPayments:paid,pendingPayments:pending,consultations},latestConsultations:latest});
}
module.exports={dashboard};
