const multer = require('multer');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');
const env = require('../config/env');

fs.mkdirSync(path.resolve(env.uploadDir), { recursive:true });

const allowed = new Map([
  ['.pdf',['application/pdf']],
  ['.csv',['text/csv','application/csv','text/plain']],
  ['.xlsx',['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']],
  ['.xls',['application/vnd.ms-excel']],
  ['.doc',['application/msword']],
  ['.docx',['application/vnd.openxmlformats-officedocument.wordprocessingml.document']],
  ['.txt',['text/plain']],
  ['.png',['image/png']],
  ['.jpg',['image/jpeg']],
  ['.jpeg',['image/jpeg']],
  ['.zip',['application/zip','application/x-zip-compressed']]
]);

const storage = multer.diskStorage({
  destination:(req,file,cb)=>cb(null,path.resolve(env.uploadDir)),
  filename:(req,file,cb)=>cb(null,`${crypto.randomUUID()}${path.extname(file.originalname).toLowerCase()}`)
});

const upload = multer({
  storage,
  limits:{fileSize:env.maxFileSizeMb*1024*1024},
  fileFilter:(req,file,cb)=>{
    const ext=path.extname(file.originalname).toLowerCase();
    const mimes=allowed.get(ext);
    if(!mimes || !mimes.includes(file.mimetype)) return cb(new Error('Tipe file tidak diizinkan.'));
    cb(null,true);
  }
});

module.exports = { upload };
