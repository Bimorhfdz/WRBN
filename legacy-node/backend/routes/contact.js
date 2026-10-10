const router=require('express').Router();
const c=require('../controllers/contact-controller');
router.post('/',c.create);
module.exports=router;
