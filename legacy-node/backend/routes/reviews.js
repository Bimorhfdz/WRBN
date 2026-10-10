const router=require('express').Router();
const c=require('../controllers/review-controller');
const {requireAuth}=require('../middleware/auth');
router.get('/',c.list);
router.post('/',requireAuth,c.create);
module.exports=router;
