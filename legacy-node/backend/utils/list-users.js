const db = require('../config/database');
require('./init-db');

const users = db.prepare(`
  SELECT id, name, email, whatsapp, created_at
  FROM users
  ORDER BY datetime(created_at) DESC
`).all();

if (!users.length) {
  console.log('Belum ada akun yang terdaftar.');
  process.exit(0);
}

console.table(users.map((u, i) => ({
  No: i + 1,
  ID: u.id,
  Nama: u.name,
  Email: u.email,
  WhatsApp: u.whatsapp || '-',
  Terdaftar: u.created_at
})));
