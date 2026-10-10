const router=require('express').Router();
const c=require('../controllers/payment-controller');
const {requireAuth}=require('../middleware/auth');
router.use(requireAuth);
router.post('/',c.create);
router.get('/',c.list);
router.get('/:id',c.getOne);
router.post('/:id/dummy-pay',c.dummyPay);
module.exports=router;
