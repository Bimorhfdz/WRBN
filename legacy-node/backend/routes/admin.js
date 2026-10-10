const express = require('express');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const env = require('../config/env');
const db = require('../config/database');
const { requireAdmin } = require('../middleware/admin');
const c = require('../controllers/admin-controller');
const router = express.Router();

router.post('/login', async (req, res) => {
  const email = String(req.body?.email || '').trim().toLowerCase();
  const password = String(req.body?.password || '');
  const admin = db.prepare('SELECT * FROM admins WHERE email=?').get(email);

  if (!admin || !(await bcrypt.compare(password, admin.password_hash))) {
    return res.status(401).json({ message: 'Email atau password admin salah.' });
  }

  const token = jwt.sign(
    { role: 'admin', adminId: admin.id, email: admin.email, name: admin.name },
    env.adminJwtSecret,
    { expiresIn: '8h' }
  );

  res.cookie(env.adminCookieName, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: env.nodeEnv === 'production',
    maxAge: 8 * 60 * 60 * 1000
  });

  res.json({ message: 'Login admin berhasil.', admin: { id: admin.id, email: admin.email, name: admin.name } });
});

router.post('/logout', (req, res) => {
  res.clearCookie(env.adminCookieName, {
    httpOnly: true,
    sameSite: 'lax',
    secure: env.nodeEnv === 'production'
  });
  res.json({ message: 'Logout berhasil.' });
});

router.get('/session', requireAdmin, (req, res) =>
  res.json({ ok: true, role: 'admin', admin: { id: req.admin.adminId, email: req.admin.email, name: req.admin.name } })
);

router.use(requireAdmin);
router.get('/dashboard', c.dashboard);
router.get('/users', c.listUsers);
router.delete('/users', c.deleteUsers);
router.get('/projects', c.listProjects);
router.get('/projects/:id', c.getProject);
router.patch('/projects/:id', c.updateProject);
router.post('/projects/:id/progress', c.addProgress);
router.get('/requests', c.listRequests);
router.patch('/reschedules/:id', c.resolveReschedule);
router.patch('/upgrades/:id', c.resolveUpgrade);
router.get('/payments', c.listPayments);
router.patch('/payments/:id', c.updatePayment);
router.get('/files/:id/download', c.downloadFile);
router.get('/messages', c.listMessages);

module.exports = router;
