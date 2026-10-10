const fs = require('fs');
const path = require('path');
const db = require('../config/database');
const { id } = require('./ids');

const schema = fs.readFileSync(path.join(__dirname, '../models/schema.sql'), 'utf8');
db.exec(schema);

// Lightweight migrations so an existing local database can be upgraded safely.
const columns = db.prepare("PRAGMA table_info(consultations)").all().map(x => x.name);
const addColumn = (name, sql) => {
  if (!columns.includes(name)) db.exec(`ALTER TABLE consultations ADD COLUMN ${sql}`);
};
addColumn('project_code', 'project_code TEXT');
addColumn('meeting_url', 'meeting_url TEXT');
addColumn('consultant_name', 'consultant_name TEXT');
addColumn('contact_person', 'contact_person TEXT');

const services = [
  { slug:'basic', name:'Basic', category:'package', description:'Paket dasar untuk pengolahan data, perhitungan statistik, hasil analisis, kesimpulan, dan 1x revisi.', price:199000, price_label:'Rp199K mulai' },
  { slug:'vip', name:'VIP', category:'package', description:'Paket lengkap dengan data cleaning, EDA, visualisasi, interpretasi, insight, prediction/forecasting jika relevan, rekomendasi, laporan, dan 2x revisi.', price:399000, price_label:'Rp399K mulai' },
  { slug:'custom', name:'Custom', category:'package', description:'Paket fleksibel sesuai metode, output, dan kompleksitas kebutuhan klien. Harga mulai Rp199K+.', price:199000, price_label:'Rp199K+' }
];

const now = new Date().toISOString();
const insert = db.prepare(`
 INSERT INTO services (id,slug,name,category,description,price,price_label,active,created_at,updated_at)
 VALUES (@id,@slug,@name,@category,@description,@price,@price_label,1,@created_at,@updated_at)
 ON CONFLICT(slug) DO UPDATE SET
 name=excluded.name,category=excluded.category,description=excluded.description,
 price=excluded.price,price_label=excluded.price_label,active=1,updated_at=excluded.updated_at
`);
const tx = db.transaction(() => services.forEach(s => insert.run({...s,id:id(),created_at:now,updated_at:now})));
tx();

// Give older projects a stable project code for the new client area.
const oldProjects = db.prepare("SELECT id, created_at FROM consultations WHERE project_code IS NULL OR project_code='' ").all();
const updateCode = db.prepare('UPDATE consultations SET project_code=? WHERE id=?');
for (const p of oldProjects) {
  const date = new Date(p.created_at || now).toISOString().slice(0,10).replace(/-/g,'');
  updateCode.run(`WRBN-${date}-${p.id.slice(0,6).toUpperCase()}`, p.id);
}

db.exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_consultations_project_code_unique ON consultations(project_code)");

console.log('Database ready:', path.resolve(process.env.DATABASE_PATH || './data/wrbn.sqlite'));
