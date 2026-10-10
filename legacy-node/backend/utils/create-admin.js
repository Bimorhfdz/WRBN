const bcrypt = require('bcryptjs');
const db = require('../config/database');
require('./init-db');
const env = require('../config/env');
const { id } = require('./ids');
const { isEmail } = require('./validation');

if (!env.adminEmail || !isEmail(env.adminEmail) || !env.adminPassword) {
  console.error('Set ADMIN_EMAIL dan ADMIN_PASSWORD di .env terlebih dahulu.');
  process.exit(1);
}
if (env.adminPassword.length < 10) {
  console.error('ADMIN_PASSWORD minimal 10 karakter.');
  process.exit(1);
}

const email = env.adminEmail.trim().toLowerCase();
const now = new Date().toISOString();
const existing = db.prepare('SELECT id FROM admins WHERE email=?').get(email);

if (existing && !env.adminResetPassword) {
  console.log(`Admin sudah ada: ${email}. Tidak ada perubahan.`);
  console.log('Untuk reset password, set ADMIN_RESET_PASSWORD=true lalu jalankan npm run db:admin.');
  process.exit(0);
}

const hash = bcrypt.hashSync(env.adminPassword, 12);
if (existing) {
  db.prepare('UPDATE admins SET password_hash=?,updated_at=? WHERE id=?').run(hash, now, existing.id);
  console.log(`Password admin diperbarui: ${email}`);
} else {
  db.prepare('INSERT INTO admins(id,email,password_hash,name,created_at,updated_at) VALUES(?,?,?,?,?,?)')
    .run(id(), email, hash, 'WRBN Admin', now, now);
  console.log(`Admin berhasil dibuat: ${email}`);
}
