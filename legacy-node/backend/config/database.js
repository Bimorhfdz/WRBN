const Database = require('better-sqlite3');
const path = require('path');
const fs = require('fs');
const env = require('./env');

const dbPath = path.resolve(env.databasePath);
fs.mkdirSync(path.dirname(dbPath), { recursive: true });

const db = new Database(dbPath);
db.pragma('foreign_keys = ON');
db.pragma('journal_mode = WAL');

module.exports = db;
