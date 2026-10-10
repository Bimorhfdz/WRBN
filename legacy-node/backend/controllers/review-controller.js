const db=require('../config/database');
const {id}=require('../utils/ids');
const {cleanString}=require('../utils/validation');
function list(req,res){
 const reviews=db.prepare(`SELECT id,rating,name,role,text,created_at FROM reviews ORDER BY created_at DESC LIMIT 30`).all();
 res.json({reviews:reviews.map(x=>({...x,initials:x.name.split(/\s+/).filter(Boolean).map(v=>v[0]).join('').slice(0,2).toUpperCase()}))});
}
function create(req,res){
 const rating=Number(req.body.rating),name=cleanString(req.body.name,60),role=cleanString(req.body.role,60)||'Klien',text=cleanString(req.body.text,400);
 if(!Number.isInteger(rating)||rating<1||rating>5||name.length<2||text.length<3)return res.status(400).json({message:'Rating, nama, dan testimoni wajib valid.'});
 const now=new Date().toISOString();
 db.prepare('INSERT INTO reviews(id,user_id,rating,name,role,text,created_at) VALUES(?,?,?,?,?,?,?)').run(id(),req.user?.id||null,rating,name,role,text,now);
 res.status(201).json({message:'Review berhasil dikirim.'});
}
module.exports={list,create};
