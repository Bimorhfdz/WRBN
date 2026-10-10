const jwt = require('jsonwebtoken');
const env = require('../config/env');

function requireAdmin(req, res, next) {
  const token = req.cookies?.[env.adminCookieName];
  if (!token) return res.status(401).json({ message: 'Admin belum login.' });

  try {
    const payload = jwt.verify(token, env.adminJwtSecret);
    if (payload?.role !== 'admin' || !payload?.adminId) throw new Error('invalid-admin');
    req.admin = payload;
    next();
  } catch {
    return res.status(401).json({ message: 'Sesi admin tidak valid atau sudah berakhir.' });
  }
}

module.exports = { requireAdmin };
