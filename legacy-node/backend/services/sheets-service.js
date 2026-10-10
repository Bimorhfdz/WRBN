const env = require('../config/env');

async function mirrorBooking(payload) {
  if (!env.googleSheetsWebhookUrl) return { skipped:true };
  try {
    const response = await fetch(env.googleSheetsWebhookUrl, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(payload)
    });
    return { skipped:false, ok:response.ok };
  } catch (error) {
    console.error('Google Sheets mirror failed:', error.message);
    return { skipped:false, ok:false };
  }
}

module.exports = { mirrorBooking };
