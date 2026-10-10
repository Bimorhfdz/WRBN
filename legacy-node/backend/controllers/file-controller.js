const fs = require('fs');
const path = require('path');
const db = require('../config/database');
const env = require('../config/env');
const { id } = require('../utils/ids');

function ownedConsultation(req,res){
 const c=db.prepare('SELECT id FROM consultations WHERE id=? AND user_id=?').get(req.params.id,req.user.id);
 if(!c){res.status(404).json({message:'Konsultasi tidak ditemukan.'});return null}
 return c;
}
function upload(req,res){
 if(!ownedConsultation(req,res))return;
 if(!req.file)return res.status(400).json({message:'File wajib dipilih.'});
 const now=new Date().toISOString(), file={id:id(),consultation_id:req.params.id,original_name:req.file.originalname,storage_path:req.file.path,mime_type:req.file.mimetype,size_bytes:req.file.size,created_at:now};
 db.prepare(`INSERT INTO consultation_files(id,consultation_id,original_name,storage_path,mime_type,size_bytes,created_at) VALUES(@id,@consultation_id,@original_name,@storage_path,@mime_type,@size_bytes,@created_at)`).run(file);
 res.status(201).json({file:{id:file.id,originalName:file.original_name,mimeType:file.mime_type,sizeBytes:file.size_bytes,createdAt:file.created_at}});
}
function list(req,res){
 if(!ownedConsultation(req,res))return;
 const files=db.prepare('SELECT id,original_name,mime_type,size_bytes,created_at FROM consultation_files WHERE consultation_id=? ORDER BY created_at DESC').all(req.params.id);
 res.json({files});
}
module.exports={upload,list};
