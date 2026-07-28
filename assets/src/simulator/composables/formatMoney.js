const wholeMoneyOptions = { minimumFractionDigits: 0, maximumFractionDigits: 0 };
const fmt = new Intl.NumberFormat('es-CO', wholeMoneyOptions);
const fmtShort = new Intl.NumberFormat('es-CO', wholeMoneyOptions);

export function formatMoney(value) {
  return fmt.format(Number(value) || 0);
}

export function formatMoneyShort(value) {
  return fmtShort.format(Number(value) || 0);
}

export function parseMoney(str) {
  if (typeof str === 'number') return str;
  return Number(String(str).replace(/[^0-9,.-]/g, '').replace(',', '.')) || 0;
}

export function safeMoney(value) {
  return Math.round(Number(value) || 0);
}
