const jwt = require('jsonwebtoken');
const env = require('../config/env');
const db = require('../config/database');

function signSession(user) {
  return jwt.sign({ sub:user.id, email:user.email }, env.jwtSecret, { expiresIn:'7d' });
}

function setSessionCookie(res, token) {
  res.cookie(env.cookieName, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: env.nodeEnv === 'production',
    maxAge: 7 * 24 * 60 * 60 * 1000,
    path: '/'
  });
}

function clearSessionCookie(res) {
  res.clearCookie(env.cookieName, {
    httpOnly: true,
    sameSite: 'lax',
    secure: env.nodeEnv === 'production',
    path: '/'
  });
}

function requireAuth(req, res, next) {
  const token = req.cookies?.[env.cookieName];
  if (!token) return res.status(401).json({message:'Authentication diperlukan.'});
  try {
    const payload = jwt.verify(token, env.jwtSecret);
    const user = db.prepare('SELECT id,name,email,whatsapp,created_at,updated_at FROM users WHERE id=?').get(payload.sub);
    if (!user) return res.status(401).json({message:'Session tidak valid.'});
    req.user = user;
    next();
  } catch {
    return res.status(401).json({message:'Session tidak valid atau sudah kedaluwarsa.'});
  }
}

module.exports = { signSession, setSessionCookie, clearSessionCookie, requireAuth };
