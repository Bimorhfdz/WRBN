require('dotenv').config();

const nodeEnv = process.env.NODE_ENV || 'development';
const jwtSecret = process.env.JWT_SECRET || 'dev-only-change-me';
const adminJwtSecret = process.env.ADMIN_JWT_SECRET || process.env.JWT_SECRET || 'dev-only-change-me-admin';

if (nodeEnv === 'production') {
  const weak = [
    ['JWT_SECRET', jwtSecret],
    ['ADMIN_JWT_SECRET', adminJwtSecret]
  ].filter(([, value]) => !value || value.length < 32 || value.includes('CHANGE_ME') || value.includes('dev-only'));
  if (weak.length) {
    throw new Error(`Production configuration invalid. Set strong secrets for: ${weak.map(([name]) => name).join(', ')}`);
  }
}

module.exports = {
  nodeEnv,
  port: Number(process.env.PORT || 3000),
  frontendOrigin: process.env.FRONTEND_ORIGIN || 'http://localhost:3000',

  // Client session
  jwtSecret,
  cookieName: process.env.COOKIE_NAME || 'wrbn_session',

  // Admin session
  adminJwtSecret,
  adminCookieName: process.env.ADMIN_COOKIE_NAME || 'wrbn_admin_session',

  // Database / storage
  databasePath: process.env.DATABASE_PATH || './data/wrbn.sqlite',
  uploadDir: process.env.UPLOAD_DIR || './backend/uploads',
  maxFileSizeMb: Number(process.env.MAX_FILE_SIZE_MB || 10),

  // Optional integrations
  googleSheetsWebhookUrl: process.env.GOOGLE_SHEETS_WEBHOOK_URL || '',
  paymentProvider: process.env.PAYMENT_PROVIDER || 'dummy',
  paymentApiUrl: process.env.PAYMENT_API_URL || '',
  paymentApiKey: process.env.PAYMENT_API_KEY || '',

  // Admin bootstrap only. Password is hashed before entering the database.
  adminEmail: process.env.ADMIN_EMAIL || '',
  adminPassword: process.env.ADMIN_PASSWORD || '',
  adminResetPassword: String(process.env.ADMIN_RESET_PASSWORD || 'false').toLowerCase() === 'true'
};
