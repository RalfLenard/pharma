<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import Pagination from '@/components/Pharmacy/Pagination.vue'

const props = defineProps({
  items: { type: Array, required: true },
  transactions: { type: Array, default: () => [] },
  transfers: { type: Array, default: () => [] },
  labSettings: { type: Object, default: () => ({}) },
})

/* ───────────────────────── date helpers (local time) ───────────────────────── */
function ymd(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
function toDate(v) {
  if (!v) return null
  if (v instanceof Date) return new Date(v.getFullYear(), v.getMonth(), v.getDate())
  const s = String(v)
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (m) return new Date(+m[1], +m[2] - 1, +m[3])
  const d = new Date(s)
  return isNaN(d.getTime()) ? null : new Date(d.getFullYear(), d.getMonth(), d.getDate())
}
function fmtDate(d) {
  if (!d) return '—'
  return `${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}/${d.getFullYear()}`
}
/* e.g. "October 2026" */
function fmtMonthYear(d) {
  if (!d) return '—'
  return d.toLocaleString('en-US', { month: 'long', year: 'numeric' })
}
const fmtInt = (v) => Number(v || 0).toLocaleString('en-US')
const fmtMoney = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const pad = (n, len = 5) => String(n).padStart(len, '0')

/* Tidy up messy remarks such as: Transfer | 2026-08-04 | To: " | RHU III */
function cleanRemark(s) {
  if (!s) return ''
  return String(s)
    .replace(/\s*\|\s*To:\s*["'“”]*\s*(?=\|)/gi, ' ')
    .replace(/\s*\|\s*To:\s*["'“”]*\s*$/gi, '')
    .replace(/\s{2,}/g, ' ')
    .trim()
}

/* ───────────────────────── filters ───────────────────────── */
const today = new Date()
const dateFrom = ref(ymd(new Date(today.getFullYear(), 0, 1)))
const dateTo = ref(ymd(today))
const sec = ref('')
const fund = ref('')
const pickedIds = ref([]) // item ids ticked in the "Items to print" dropdown
const includeArchived = ref(false)
const hideEmpty = ref(false)

const presets = [
  { key: 'month', label: 'This month' },
  { key: 'lastMonth', label: 'Last month' },
  { key: 'quarter', label: 'This quarter' },
  { key: 'year', label: 'Year to date' },
]

function applyPreset(key) {
  const y = today.getFullYear()
  const m = today.getMonth()
  if (key === 'month') {
    dateFrom.value = ymd(new Date(y, m, 1)); dateTo.value = ymd(new Date(y, m + 1, 0))
  } else if (key === 'lastMonth') {
    dateFrom.value = ymd(new Date(y, m - 1, 1)); dateTo.value = ymd(new Date(y, m, 0))
  } else if (key === 'quarter') {
    const q = Math.floor(m / 3) * 3
    dateFrom.value = ymd(new Date(y, q, 1)); dateTo.value = ymd(new Date(y, q + 3, 0))
  } else {
    dateFrom.value = ymd(new Date(y, 0, 1)); dateTo.value = ymd(today)
  }
}

const sections = computed(() => [...new Set(props.items.map((i) => i.sec).filter(Boolean))].sort())
const funds = computed(() => [...new Set(props.items.map((i) => i.fund).filter(Boolean))].sort())

const itemOptions = computed(() => props.items
  .filter((i) => includeArchived.value || !i.archived)
  .map((i) => ({
    value: String(i.id),
    label: [i.name, i.brand, i.lot ? `Lot ${i.lot}` : ''].filter(Boolean).join(' · '),
  }))
  .sort((a, b) => a.label.localeCompare(b.label)))

const rangeError = computed(() => {
  if (!dateFrom.value || !dateTo.value) return 'Choose both dates.'
  if (dateFrom.value > dateTo.value) return '“From” must be on or before “To”.'
  return ''
})

/* ── item dropdown with checkboxes ── */
const dropOpen = ref(false)
const dropSearch = ref('')
const dropRef = ref(null)

const visibleOptions = computed(() => {
  const q = dropSearch.value.trim().toLowerCase()
  return q ? itemOptions.value.filter((o) => o.label.toLowerCase().includes(q)) : itemOptions.value
})

const dropLabel = computed(() => {
  const n = pickedIds.value.length
  if (!n) return 'All items'
  if (n === 1) return itemOptions.value.find((o) => o.value === pickedIds.value[0])?.label || '1 item selected'
  return `${n} items selected`
})

function toggleItem(v) {
  pickedIds.value = pickedIds.value.includes(v)
    ? pickedIds.value.filter((x) => x !== v)
    : [...pickedIds.value, v]
}
function pickAllVisible() {
  pickedIds.value = [...new Set([...pickedIds.value, ...visibleOptions.value.map((o) => o.value)])]
}
function clearPicked() { pickedIds.value = [] }
function onDocClick(e) {
  if (dropOpen.value && dropRef.value && !dropRef.value.contains(e.target)) dropOpen.value = false
}

function clearScope() {
  sec.value = ''; fund.value = ''; pickedIds.value = []
  includeArchived.value = false; hideEmpty.value = false
}

/* ───────────────────────── stock card calculation ───────────────────────── */
const entityName = computed(() => props.labSettings?.name || 'Pharmacy Supplies Inventory')

/* Per item: sorted by date, then Received (in) BEFORE Issued (out), then id */
const txnsByItem = computed(() => {
  const map = new Map()
  props.transactions.forEach((t) => {
    const k = Number(t.item_id)
    if (!map.has(k)) map.set(k, [])
    map.get(k).push(t)
  })
  map.forEach((list) => list.sort((a, b) => {
    const da = (toDate(a.date)?.getTime() || 0) - (toDate(b.date)?.getTime() || 0)
    if (da) return da
    const ia = a.type === 'in' ? 0 : 1
    const ib = b.type === 'in' ? 0 : 1
    if (ia !== ib) return ia - ib
    return Number(a.id) - Number(b.id)
  }))
  return map
})

const transferByTxn = computed(() => {
  const map = new Map()
  props.transfers.forEach((tr) => {
    if (tr.transaction_id != null) map.set(Number(tr.transaction_id), tr)
  })
  return map
})

function buildCard(item, from, to) {
  const unitCost = Number(item.unit_cost) || 0
  const fallbackDate = toDate(item.added_date) || toDate(item.created_at) || toDate(today)
  const entries = []

  /* Only real transactions from the database (no synthetic opening / balance-forward rows) */
  ;(txnsByItem.value.get(Number(item.id)) || []).forEach((t) => {
    const date = toDate(t.date) || fallbackDate
    const isIn = t.type === 'in'
    const qty = Number(t.qty) || 0
    const tr = transferByTxn.value.get(Number(t.id))
    let type, ref, remark
    if (tr) {
      type = 'Commodity Issued (Transfer)'
      ref = `TRF-${date.getFullYear()}-${pad(tr.id)}`
      const dest = tr.destination || tr.to_location || '—'
      remark = `Transferred to ${dest}${tr.remarks ? ` — ${cleanRemark(tr.remarks)}` : ''}`
    } else {
      type = isIn ? 'Commodity Received' : 'Commodity Issued'
      ref = '—'
      remark = cleanRemark(t.note)
    }
    entries.push({
      no: `TXN-${date.getFullYear()}-${pad(t.id)}`,
      type, date,
      received: isIn ? qty : 0,
      issued: isIn ? 0 : qty,
      ref, remark,
    })
  })

  /* Commodity received date = earliest date this item was received */
  let receivedDate = null
  entries.forEach((e) => {
    if (e.received > 0 && (!receivedDate || e.date.getTime() < receivedDate.getTime())) {
      receivedDate = e.date
    }
  })

  let balance = 0
  let received = 0
  let issued = 0
  const rows = []
  const fromT = from.getTime()
  const toT = to.getTime()

  entries.forEach((e) => {
    const t = e.date.getTime()
    if (t > toT) return
    balance += e.received - e.issued // running balance still counts anything before the period
    if (t < fromT) return            // …but earlier rows are not printed (no Balance Forward row)
    received += e.received
    issued += e.issued
    rows.push({ ...e, balance, value: balance * unitCost })
  })

  const description = item.name
    + (item.brand ? ` — ${item.brand}` : '')
    + (item.vol ? ` (${item.vol})` : '')

  return {
    item,
    sku: pad(item.id, 6),
    description,
    uom: item.unit || '—',
    unitCost,
    receivedDate,
    procurementMode: fmtMonthYear(receivedDate), // shown under "Mode of Procurement"
    rows,
    hasMovement: rows.length > 0,
    received, issued,
    closing: balance,
    closingValue: balance * unitCost,
  }
}

const itemsInScope = computed(() => props.items
  .filter((i) => {
    if (!includeArchived.value && i.archived) return false
    if (sec.value && i.sec !== sec.value) return false
    if (fund.value && i.fund !== fund.value) return false
    if (pickedIds.value.length && !pickedIds.value.includes(String(i.id))) return false
    return true
  })
  .sort((a, b) => (a.sec || '').localeCompare(b.sec || '')
    || (a.name || '').localeCompare(b.name || '')
    || String(a.exp || '').localeCompare(String(b.exp || ''))
    || Number(a.id) - Number(b.id)))

const cards = computed(() => {
  if (rangeError.value) return []
  const from = toDate(dateFrom.value)
  const to = toDate(dateTo.value)
  const out = []
  itemsInScope.value.forEach((item) => {
    const c = buildCard(item, from, to)
    if (!c.hasMovement && Math.abs(c.closing) < 1e-9) return // nothing happened, nothing on hand
    if (hideEmpty.value && !c.hasMovement) return
    out.push(c)
  })
  return out
})

/* ───────────────────────── pagination (screen only) ───────────────────────── */
const page = ref(1)
const perPage = ref(10)
const printing = ref(false)

const visibleCards = computed(() => {
  if (printing.value) return cards.value
  const start = (page.value - 1) * perPage.value
  return cards.value.slice(start, start + perPage.value)
})

watch([dateFrom, dateTo, sec, fund, pickedIds, includeArchived, hideEmpty, perPage], () => { page.value = 1 })
watch(() => cards.value.length, (len) => {
  const max = Math.max(1, Math.ceil(len / perPage.value))
  if (page.value > max) page.value = max
})

/* ───────────────────────── print (this tab only) ───────────────────────── */
async function printReport() {
  if (!cards.value.length) return
  printing.value = true
  await nextTick()
  window.print()
  printing.value = false
}
function onAfterPrint() { printing.value = false }
onMounted(() => {
  window.addEventListener('afterprint', onAfterPrint)
  document.addEventListener('mousedown', onDocClick)
})
onUnmounted(() => {
  window.removeEventListener('afterprint', onAfterPrint)
  document.removeEventListener('mousedown', onDocClick)
})

/* ───────────────────────── CSV (Excel) ───────────────────────── */
function csvCell(v) {
  const s = v === null || v === undefined ? '' : String(v)
  return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
}

function downloadCsv() {
  if (!cards.value.length) return
  const header = [
    'Stock Keeping Unit', 'Item Description', 'UOM', 'Funding Source', 'Mode of Procurement',
    'Transaction No', 'Transaction Type', 'Transaction Date', 'Batch No', 'Expiry Date',
    'Category', 'Unit Cost', 'Received Qty', 'Issued Qty', 'Balance Qty',
    'Remark',
  ]
  const lines = [header.map(csvCell).join(',')]
  cards.value.forEach((c) => {
    c.rows.forEach((r) => {
      lines.push([
        c.sku, c.description, c.uom, c.item.fund || '', c.procurementMode,
        r.no, r.type, fmtDate(r.date), c.item.lot || '', c.item.exp ? fmtDate(toDate(c.item.exp)) : '',
        c.item.sec || '', c.unitCost.toFixed(2), r.received ?? '', r.issued ?? '', r.balance,
        r.remark,
      ].map(csvCell).join(','))
    })
  })
  const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `stock-card_${dateFrom.value}_${dateTo.value}.csv`
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 5000)
}
</script>

<template>
  <div class="sc">
    <!-- FILTERS -->
    <div class="panel">
      <div class="block">
        <div class="block-title">Period</div>
        <div class="row">
          <label class="fld">
            <span>From</span>
            <input v-model="dateFrom" type="date" />
          </label>
          <label class="fld">
            <span>To</span>
            <input v-model="dateTo" type="date" />
          </label>
          <div class="chips">
            <button v-for="p in presets" :key="p.key" type="button" class="chip" @click="applyPreset(p.key)">{{ p.label }}</button>
          </div>
        </div>
        <div v-if="rangeError" class="err">{{ rangeError }}</div>
      </div>

      <div class="block">
        <div class="block-title">Items</div>
        <div class="row">
          <label class="fld">
            <span>Category</span>
            <select v-model="sec">
              <option value="">All categories</option>
              <option v-for="s in sections" :key="s" :value="s">{{ s }}</option>
            </select>
          </label>
          <label class="fld">
            <span>Fund source</span>
            <select v-model="fund">
              <option value="">All fund sources</option>
              <option v-for="f in funds" :key="f" :value="f">{{ f }}</option>
            </select>
          </label>
          <div class="fld grow">
            <span>Items to print</span>
            <div ref="dropRef" class="ms">
              <button type="button" class="ms-btn" :aria-expanded="dropOpen" @click="dropOpen = !dropOpen">
                <span class="ms-label">{{ dropLabel }}</span>
                <span class="ms-caret">▾</span>
              </button>
              <div v-if="dropOpen" class="ms-panel">
                <input v-model="dropSearch" type="text" class="ms-search" placeholder="Search items…" />
                <div class="ms-tools">
                  <button type="button" class="link" @click="pickAllVisible">Select all{{ dropSearch ? ' shown' : '' }}</button>
                  <button type="button" class="link" :disabled="!pickedIds.length" @click="clearPicked">Clear</button>
                  <span class="hint">{{ pickedIds.length }} selected</span>
                </div>
                <div class="ms-list">
                  <label v-for="o in visibleOptions" :key="o.value" class="ms-opt">
                    <input type="checkbox" :checked="pickedIds.includes(o.value)" @change="toggleItem(o.value)" />
                    <span>{{ o.label }}</span>
                  </label>
                  <div v-if="!visibleOptions.length" class="ms-none">No items found</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="checks">
          <label><input v-model="hideEmpty" type="checkbox" /> Only items with movement in this period</label>
          <label><input v-model="includeArchived" type="checkbox" /> Include archived items</label>
          <button type="button" class="link" @click="clearScope">Reset items</button>
        </div>
      </div>

      <div class="foot">
        <div class="summary">
          <strong>{{ cards.length }}</strong> stock card{{ cards.length === 1 ? '' : 's' }}
          <span class="hint">· items with no movement and no stock on hand are left out</span>
        </div>
        <div class="actions">
          <button class="btn" :disabled="!cards.length" @click="downloadCsv">Download CSV</button>
          <button class="btn primary" :disabled="!cards.length" @click="printReport">Print all ({{ cards.length }})</button>
        </div>
      </div>
    </div>

    <!-- REPORT (rendered right here in the tab) -->
    <Teleport to="body" :disabled="!printing">
      <div class="sc-sheet" :class="{ 'pharm-print-root': printing }">
        <div class="report-head">
          <img src="/images/doh-logo.png" alt="" onerror="this.style.display='none'" />
          <div class="rh-title">Stock Card</div>
          <div class="rh-range">From: {{ fmtDate(toDate(dateFrom)) }} To: {{ fmtDate(toDate(dateTo)) }}</div>
          <div class="rh-entity">{{ entityName }}</div>
        </div>

        <div v-if="!visibleCards.length" class="sc-empty">
          {{ rangeError || 'No items with stock movement or balance for this period and filters.' }}
        </div>

        <div v-for="c in visibleCards" :key="c.item.id" class="card">
          <div class="card-head">
            <div>
              <div class="kv"><span class="k">Stock Keeping Unit:</span><span>{{ c.sku }}</span></div>
              <div class="kv"><span class="k">Item Description:</span><span>{{ c.description }}</span></div>
              <div class="kv"><span class="k">UOM:</span><span>{{ c.uom }}</span></div>
            </div>
            <div>
              <div class="kv"><span class="k">Entity Name:</span><span>{{ entityName }}</span></div>
              <div class="kv"><span class="k">Funding Source:</span><span>{{ c.item.fund || '—' }}</span></div>
              <div class="kv"><span class="k">Mode of Procurement:</span><span>{{ c.procurementMode }}</span></div>
            </div>
          </div>

          <div class="tbl-scroll">
            <table>
              <colgroup>
                <col style="width:10%"><col style="width:12%"><col style="width:8%"><col style="width:8%">
                <col style="width:8%"><col style="width:10%"><col style="width:7%"><col style="width:7%">
                <col style="width:7%"><col style="width:7%"><col style="width:16%">
              </colgroup>
              <thead>
                <tr>
                  <th>Transaction No</th>
                  <th>Transaction Type</th>
                  <th>Transaction Date</th>
                  <th>Batch No</th>
                  <th>Expiry Date</th>
                  <th>Category</th>
                  <th class="r">Unit Cost</th>
                  <th class="r">Received Qty</th>
                  <th class="r">Issued Qty</th>
                  <th class="r">Balance Qty</th>
                  <th>Remark</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, i) in c.rows" :key="i">
                  <td class="nw">{{ r.no }}</td>
                  <td>{{ r.type }}</td>
                  <td class="nw">{{ fmtDate(r.date) }}</td>
                  <td>{{ c.item.lot || '—' }}</td>
                  <td class="nw">{{ fmtDate(toDate(c.item.exp)) }}</td>
                  <td>{{ c.item.sec || '—' }}</td>
                  <td class="r">{{ fmtMoney(c.unitCost) }}</td>
                  <td class="r">{{ fmtInt(r.received) }}</td>
                  <td class="r">{{ fmtInt(r.issued) }}</td>
                  <td class="r">{{ fmtInt(r.balance) }}</td>
                  <td>{{ r.remark }}</td>
                </tr>
                <tr class="tot">
                  <td colspan="7">Total for period</td>
                  <td class="r">{{ fmtInt(c.received) }}</td>
                  <td class="r">{{ fmtInt(c.issued) }}</td>
                  <td class="r">{{ fmtInt(c.closing) }}</td>
                  <td></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </Teleport>

    <Pagination
      v-model:page="page"
      v-model:perPage="perPage"
      :total="cards.length"
      :options="[10, 20, 50]"
      label="stock cards"
    />
  </div>
</template>

<style scoped>
.sc { margin-top: 16px; }

/* ── filter panel (uses the page's CSS variables) ── */
.panel { background: var(--bg); border: 1px solid var(--c5); border-radius: var(--r2); margin-bottom: 14px; }
.block { padding: 14px 18px; border-bottom: 1px solid var(--c6); }
.block-title { font-size: 12px; font-weight: 600; margin-bottom: 10px; }
.row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.fld { display: flex; flex-direction: column; gap: 4px; min-width: 170px; }
.fld.grow { flex: 1 1 320px; min-width: 260px; }
.fld > span { font-size: 11px; color: var(--c2); }
.fld > select, .fld > input { width: 100%; }
.ms { position: relative; width: 100%; }
.ms-btn { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; height: 34px; padding: 0 10px; box-sizing: border-box; border: 1px solid var(--c5); border-radius: var(--r); background: var(--bg); color: var(--c); font-size: 13px; text-align: left; cursor: pointer; }
.ms-btn:hover { background: var(--c7); }
.ms-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ms-caret { flex: none; color: var(--c3); font-size: 11px; }
.ms-panel { position: absolute; z-index: 50; top: calc(100% + 4px); left: 0; width: 100%; min-width: 320px; box-sizing: border-box; background: var(--bg); border: 1px solid var(--c5); border-radius: var(--r2); box-shadow: 0 8px 24px rgba(0,0,0,.14); padding: 10px; }
.ms-search { display: block; width: 100%; height: 32px; box-sizing: border-box; margin: 0 0 8px; padding: 0 10px; }
.ms-tools { display: flex; align-items: center; gap: 14px; padding: 0 2px 8px; border-bottom: 1px solid var(--c6); }
.ms-tools .link:disabled { opacity: .4; cursor: default; text-decoration: none; }
.ms-tools .hint { margin-left: auto; }
.ms-list { max-height: 260px; overflow-y: auto; padding-top: 6px; }
.ms-opt { display: flex; align-items: flex-start; gap: 10px; padding: 6px 6px; font-size: 12px; line-height: 1.35; cursor: pointer; border-radius: 4px; }
.ms-opt:hover { background: var(--c7); }
.ms-opt span { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.ms-opt input[type=checkbox] { flex: none; width: 15px; height: 15px; margin: 1px 0 0; padding: 0; accent-color: var(--accent); cursor: pointer; }
.ms-none { padding: 10px 6px; font-size: 12px; color: var(--c3); }
.chips { display: flex; gap: 6px; flex-wrap: wrap; margin-left: 4px; }
.chip { height: 30px; padding: 0 12px; border: 1px solid var(--c5); border-radius: 20px; background: var(--c7); color: var(--c2); font-size: 12px; font-weight: 500; cursor: pointer; }
.chip:hover { background: var(--accent2); border-color: var(--accent2); color: var(--accent); }
.err { margin-top: 8px; font-size: 12px; color: var(--red); }
.checks { display: flex; flex-wrap: wrap; align-items: center; gap: 18px; margin-top: 12px; }
.checks label { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer; }
.checks input[type=checkbox] { accent-color: var(--accent); cursor: pointer; }
.link { background: none; border: none; padding: 0; color: var(--accent); font-size: 12px; cursor: pointer; text-decoration: underline; }
.foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 12px 18px; }
.summary { font-size: 13px; }
.hint { color: var(--c3); font-size: 12px; }
.actions { display: flex; gap: 8px; }
.btn { height: 34px; padding: 0 16px; border: 1px solid var(--c5); border-radius: var(--r); background: var(--bg); color: var(--c); font-size: 12px; font-weight: 500; cursor: pointer; white-space: nowrap; }
.btn:hover:not(:disabled) { background: var(--c6); }
.btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
.btn.primary:hover:not(:disabled) { background: #1d3a98; }
.btn:disabled { opacity: .5; cursor: not-allowed; }

/* ── the report sheet (hard-coded colours: it is teleported to <body> when printing) ── */
.sc-sheet { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 18px 8px; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height: 1.35; }

.report-head { text-align: center; padding-bottom: 10px; border-bottom: 1px solid #111; margin-bottom: 12px; }
.report-head img { height: 60px; width: auto; display: block; margin: 0 auto 6px; }
.rh-title { font-size: 13px; font-weight: 700; }
.rh-range { margin-top: 2px; }
.rh-entity { margin-top: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; }

.sc-empty { text-align: center; padding: 40px 0; color: #64748b; font-size: 13px; }

.card { margin-bottom: 18px; }

.card-head { display: grid; grid-template-columns: 1.6fr 1fr; gap: 4px 24px; padding: 2px 4px 8px; break-after: avoid; page-break-after: avoid; }
.kv { display: flex; gap: 4px; }
.k { font-weight: 700; white-space: nowrap; }

.tbl-scroll { overflow-x: auto; }
.card table { width: 100%; min-width: 1100px; border-collapse: collapse; table-layout: fixed; border: 1px solid #111; }
.card thead { display: table-header-group; }
.card tr { break-inside: avoid; page-break-inside: avoid; }
.card th, .card td { border: 1px solid #111; padding: 3px 5px; vertical-align: top; word-wrap: break-word; font-size: 10px; text-align: left; }
.card th { font-weight: 700; background: #f1f5f9; text-transform: none; letter-spacing: 0; }
.card .r { text-align: right; }
.card .nw { white-space: nowrap; }
.card tr.tot td { font-weight: 700; background: #f1f5f9; }
</style>

<!-- GLOBAL print rules: show only the stock card sheet -->
<style>
@media print {
  @page { size: A4 landscape; margin: 10mm; }
  body > *:not(.pharm-print-root) { display: none !important; }
  .pharm-print-root { display: block !important; border: 0 !important; border-radius: 0 !important; padding: 0 !important; margin: 0 !important; }
  .pharm-print-root .tbl-scroll { overflow: visible !important; }
  .pharm-print-root table { min-width: 0 !important; }
  .pharm-print-root th, .pharm-print-root tr.tot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>