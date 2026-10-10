const db = require('../config/database');
function list(req,res){
  const services=db.prepare('SELECT id,slug,name,category,description,price,price_label AS priceLabel FROM services WHERE active=1 ORDER BY rowid').all();
  res.json({services});
}
module.exports={list};
