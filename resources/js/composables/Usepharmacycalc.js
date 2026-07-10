// Pure helper functions ported 1:1 from the original PHARMACY_INVENTORY_V1.html
// so behaviour (stock math, status thresholds, expiry logic) matches exactly.

export const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

export const FUND_SOURCES = [
  'General Fund (MOOE)', 'Trust Fund', 'DOH Subsidy',
  'Local Fund', 'Donation/Grant', 'Others',
];

export function parseLocalDate(str) {
  if (!str) return new Date();
  const p = String(str).split('-');
  return new Date(parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2]));
}

export function localDateStr(d = new Date()) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export function fmtDate(d) {
  if (!d) return '—';
  const p = String(d).split('-');
  if (p.length !== 3) return d;
  return `${p[1]}/${p[2]}/${p[0]}`;
}

export function monthKey(y, m) {
  return y * 100 + m;
}

export function monthLabel(y, m) {
  return `${MONTHS[m]} ${y}`;
}

export function prevMonthKey(key) {
  let y = Math.floor(key / 100);
  let m = key % 100;
  if (m === 0) { m = 11; y--; } else m--;
  return monthKey(y, m);
}

export function daysUntilExpiry(exp) {
  if (!exp) return null;
  return Math.ceil((new Date(exp) - new Date()) / 864e5);
}

export function expClass(d) {
  if (d === null) return 'ok';
  if (d < 0) return 'bad';
  if (d <= 30) return 'w30';
  if (d <= 60) return 'w60';
  return 'ok';
}

export function expLabel(d) {
  if (d === null) return '';
  if (d < 0) return `Expired ${Math.abs(d)} day${Math.abs(d) !== 1 ? 's' : ''} ago`;
  if (d === 0) return 'Expires today!';
  if (d <= 60) return `${d} day${d !== 1 ? 's' : ''} until expiry`;
  return '';
}

/** Cumulative stock for an item up to (and including) a given month key. */
export function stockForItem(txns, itemId, upToKey) {
  let ti = 0, to = 0;
  txns.forEach((t) => {
    const d = parseLocalDate(t.date);
    const k = monthKey(d.getFullYear(), d.getMonth());
    if (t.item_id === itemId && k <= upToKey) {
      if (t.type === 'in' || t.type === 'adj') ti += t.qty;
      else if (t.type === 'out') to += t.qty;
    }
  });
  return { in: ti, out: to, curr: Math.max(0, ti - to) };
}

/** In/out that happened strictly within one month. */
export function stockMonthOnly(txns, itemId, key) {
  let mi = 0, mo = 0;
  txns.forEach((t) => {
    const d = parseLocalDate(t.date);
    const k = monthKey(d.getFullYear(), d.getMonth());
    if (t.item_id === itemId && k === key) {
      if (t.type === 'in' || t.type === 'adj') mi += t.qty;
      else if (t.type === 'out') mo += t.qty;
    }
  });
  return { in: mi, out: mo };
}

/** Full month snapshot: carry-forward + received - consumed = current. */
export function stockForMonth(txns, itemId, key) {
  const prevKey = prevMonthKey(key);
  const prevClose = stockForItem(txns, itemId, prevKey).curr;
  const thisMonth = stockMonthOnly(txns, itemId, key);
  const stockIn = prevClose + thisMonth.in;
  const stockOut = thisMonth.out;
  return {
    in: stockIn,
    out: stockOut,
    curr: Math.max(0, stockIn - stockOut),
    carryForward: prevClose,
    received: thisMonth.in,
  };
}

export function itemStatus(txns, item, curKey, combinedStock) {
  const ms = stockForMonth(txns, item.id, curKey);
  const stock = combinedStock !== undefined ? combinedStock : ms.curr;
  if (item.exp && parseLocalDate(item.exp) < new Date()) {
    return ms.curr > 0 ? 'consume' : 'expired';
  }
  if (stock === 0) return 'critical';
  if (stock < item.min) return stock <= Math.floor(item.min * 0.4) ? 'critical' : 'low';
  return 'ok';
}

export function statusLabel(s) {
  return s === 'ok' ? 'Adequate'
    : s === 'low' ? 'Low stock'
    : s === 'critical' ? 'Critical'
    : s === 'consume' ? 'Discard'
    : 'Expired';
}