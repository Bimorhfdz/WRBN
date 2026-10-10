const bcrypt = require('bcryptjs');
const db = require('../config/database');
const env = require('../config/env');
const { id } = require('../utils/ids');
const { cleanString, isEmail } = require('../utils/validation');
const { signSession, setSessionCookie, clearSessionCookie } = require('../middleware/auth');

function publicUser(user) {
  return {id:user.id,name:user.name,email:user.email,whatsapp:user.whatsapp,created_at:user.created_at};
}

async function register(req,res) {
  const name=cleanString(req.body.name,100), email=cleanString(req.body.email,160).toLowerCase(), password=String(req.body.password||'');
  if(name.length<2) return res.status(400).json({message:'Nama minimal 2 karakter.'});
  if(!isEmail(email)) return res.status(400).json({message:'Email tidak valid.'});
  if(password.length<8) return res.status(400).json({message:'Password minimal 8 karakter.'});
  const exists=db.prepare('SELECT id FROM users WHERE email=?').get(email);
  if(exists) return res.status(409).json({message:'Email sudah terdaftar.'});
  const now=new Date().toISOString(), user={id:id(),name,email,password_hash:await bcrypt.hash(password,12),whatsapp:null,created_at:now,updated_at:now};
  db.prepare(`INSERT INTO users(id,name,email,password_hash,whatsapp,created_at,updated_at) VALUES(?,?,?,?,?,?,?)`).run(user.id,user.name,user.email,user.password_hash,null,now,now);
  setSessionCookie(res,signSession(user));
  res.status(201).json({user:publicUser(user)});
}

async function login(req,res) {
  const email=cleanString(req.body.email,160).toLowerCase(), password=String(req.body.password||'');
  const user=db.prepare('SELECT * FROM users WHERE email=?').get(email);
  if(!user || !(await bcrypt.compare(password,user.password_hash))) return res.status(401).json({message:'Email atau password salah.'});
  setSessionCookie(res,signSession(user));
  res.json({user:publicUser(user)});
}

function logout(req,res){ clearSessionCookie(res); res.json({message:'Logout berhasil.'}); }

function me(req,res){res.json({user:publicUser(req.user)});}

module.exports={register,login,logout,me};
