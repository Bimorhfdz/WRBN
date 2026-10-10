const fs=require('fs');
const path=require('path');
const db=require('../config/database');
const env=require('../config/env');

const source=path.resolve(env.databasePath);
if(!fs.existsSync(source)){console.error('Database belum ada:',source);process.exit(1);}
const backupDir=path.resolve('./backups');
fs.mkdirSync(backupDir,{recursive:true});
const stamp=new Date().toISOString().replace(/[:.]/g,'-');
const target=path.join(backupDir,`wrbn-${stamp}.sqlite`);
db.backup(target).then(()=>{console.log('Backup berhasil:',target);db.close();}).catch(err=>{console.error('Backup gagal:',err.message);try{db.close()}catch{};process.exit(1)});
