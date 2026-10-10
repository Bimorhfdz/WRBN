const router=require('express').Router();
const c=require('../controllers/service-controller');
router.get('/',c.list);
module.exports=router;
