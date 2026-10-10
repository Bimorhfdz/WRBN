const crypto = require('crypto');
const id = () => crypto.randomUUID();
module.exports = { id };
