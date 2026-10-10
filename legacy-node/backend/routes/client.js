const router=require('express').Router();
const c=require('../controllers/client-controller');
const {requireAuth}=require('../middleware/auth');
router.use(requireAuth);
router.get('/dashboard',c.dashboard);
module.exports=router;
