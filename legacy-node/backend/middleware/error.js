function errorHandler(err, req, res, next) {
  console.error(err);
  if (err.code === 'LIMIT_FILE_SIZE') return res.status(413).json({message:'Ukuran file terlalu besar.'});
  if (err.name === 'MulterError') return res.status(400).json({message:err.message});
  return res.status(err.status || 500).json({message:err.message || 'Internal server error.'});
}
module.exports = { errorHandler };
