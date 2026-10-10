const db=require('../config/database');
const {id}=require('../utils/ids');
const {cleanString,isEmail}=require('../utils/validation');
function create(req,res){
 const name=cleanString(req.body.name,100),email=cleanString(req.body.email,160).toLowerCase(),need=cleanString(req.body.need,160),message=cleanString(req.body.message,5000);
 if(name.length<2||!isEmail(email)||!need||message.length<5)return res.status(400).json({message:'Data kontak belum lengkap atau tidak valid.'});
 const now=new Date().toISOString();
 db.prepare('INSERT INTO contact_messages(id,name,email,need,message,created_at) VALUES(?,?,?,?,?,?)').run(id(),name,email,need,message,now);
 res.status(201).json({message:'Brief berhasil diterima.'});
}
module.exports={create};
