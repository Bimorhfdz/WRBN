function cleanString(value, max = 5000) {
  if (typeof value !== 'string') return '';
  return value.trim().slice(0, max);
}

function isEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function isValidDate(value) {
  if (!value) return true;
  return !Number.isNaN(Date.parse(value));
}

module.exports = { cleanString, isEmail, isValidDate };
