const fs = require('fs');
const readline = require('readline');
const db = require('../config/database');
require('./init-db');

const email = String(process.argv[2] || '').trim().toLowerCase();

if (!email) {
  console.error('Cara pakai: npm run db:delete-user -- email@example.com');
  process.exit(1);
}

const user = db.prepare('SELECT id, name, email FROM users WHERE email=?').get(email);

if (!user) {
  console.log(`Akun dengan email ${email} tidak ditemukan.`);
  process.exit(0);
}

const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
rl.question(`Hapus akun ${user.name} <${user.email}> beserta data konsultasinya? Ketik DELETE untuk lanjut: `, answer => {
  rl.close();
  if (answer.trim() !== 'DELETE') {
    console.log('Dibatalkan.');
    process.exit(0);
  }

  // Remove uploaded files from disk before the DB rows are cascaded.
  const files = db.prepare(`
    SELECT cf.storage_path
    FROM consultation_files cf
    JOIN consultations c ON c.id = cf.consultation_id
    WHERE c.user_id=?
  `).all(user.id);

  const removeUser = db.transaction(() => {
    for (const file of files) {
      if (file.storage_path) {
        try {
          if (fs.existsSync(file.storage_path)) fs.unlinkSync(file.storage_path);
        } catch (err) {
          console.warn(`Gagal menghapus file ${file.storage_path}: ${err.message}`);
        }
      }
    }

    db.prepare('DELETE FROM users WHERE id=?').run(user.id);
  });

  removeUser();
  console.log(`Akun ${user.email} berhasil dihapus.`);
});
