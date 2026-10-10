const router=require('express').Router();
const c=require('../controllers/auth-controller');
router.post('/register',c.register);
router.post('/login',c.login);
router.post('/logout',c.logout);
router.get('/me',require('../middleware/auth').requireAuth,c.me);
module.exports=router;
