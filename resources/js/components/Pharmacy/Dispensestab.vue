<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import DispenseModal from '@/components/Pharmacy/modals/Dispensemodal.vue'
import Pagination from '@/components/Pharmacy/Pagination.vue'

const props = defineProps({
  dispenses: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  transactions: { type: Array, default: () => [] },
  labSettings: { type: Object, default: () => ({}) },
})

const showModal = ref(false)
const editing = ref(null)

function openNew() {
  editing.value = null
  showModal.value = true
}
function openEdit(d) {
  editing.value = d
  showModal.value = true
}
const q = ref('')
const fPhil = ref('')
const fMonth = ref('')
const fItem = ref('')

function ageOf(dob) {
  if (!dob) return '—'
  const d = new Date(dob)
  const n = new Date()
  let a = n.getFullYear() - d.getFullYear()
  const m = n.getMonth() - d.getMonth()
  if (m < 0 || (m === 0 && n.getDate() < d.getDate())) a--
  return a
}

function fmtDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function monthKeyOf(v) {
  const dt = new Date(v)
  return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}`
}

const months = computed(() => {
  const map = new Map()
  props.dispenses.forEach((d) => {
    const key = monthKeyOf(d.created_at)
    if (!map.has(key)) {
      map.set(key, new Date(d.created_at).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' }))
    }
  })
  return [...map.entries()]
    .sort((a, b) => b[0].localeCompare(a[0]))
    .map(([value, label]) => ({ value, label }))
})

/* items that actually appear in dispense records, for the filter dropdown */
const itemOptions = computed(() => {
  const map = new Map()
  props.dispenses.forEach((d) => {
    ;(d.dispense_items || []).forEach((l) => {
      const key = String(l.item_id)
      if (!map.has(key)) map.set(key, { value: key, label: l.item?.name || 'Deleted item', count: 0 })
      map.get(key).count++
    })
  })
  return [...map.values()].sort((a, b) => a.label.localeCompare(b.label))
})

const hasFilters = computed(() => !!(q.value || fPhil.value || fMonth.value || fItem.value))
function clearFilters() {
  q.value = ''
  fPhil.value = ''
  fMonth.value = ''
  fItem.value = ''
}

const rows = computed(() => {
  const s = q.value.trim().toLowerCase()
  return props.dispenses
    .filter((d) => {
      const mQ = !s
        || d.full_name?.toLowerCase().includes(s)
        || (d.brgy || '').toLowerCase().includes(s)
        || (d.dispense_by || '').toLowerCase().includes(s)
        || (d.received_by || '').toLowerCase().includes(s)
        || (d.philhealth_number || '').includes(s)
        || (d.reference_no || '').toLowerCase().includes(s)
        || (d.dispense_items || []).some((l) => (l.item?.name || '').toLowerCase().includes(s))
      const mP = fPhil.value === '' || (fPhil.value === '1') === !!d.has_philhealth
      const mM = !fMonth.value || monthKeyOf(d.created_at) === fMonth.value
      const mI = !fItem.value || (d.dispense_items || []).some((l) => String(l.item_id) === fItem.value)
      return mQ && mP && mM && mI
    })
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

/* ── Pagination (works together with search + filters) ── */
const page = ref(1)
const perPage = ref(20)

const pagedRows = computed(() => {
  const start = (page.value - 1) * perPage.value
  return rows.value.slice(start, start + perPage.value)
})

// any search / filter / page-size change jumps back to page 1
watch([q, fPhil, fMonth, fItem, perPage], () => { page.value = 1 })

// if records are deleted and the current page no longer exists
watch(() => rows.value.length, (len) => {
  const max = Math.max(1, Math.ceil(len / perPage.value))
  if (page.value > max) page.value = max
})

// with an item filter, count only that item's quantity (stats use ALL matching rows, not just this page)
const totalQty = computed(() => rows.value.reduce((sum, d) => {
  if (!fItem.value) return sum + (Number(d.qty) || 0)
  return sum + (d.dispense_items || [])
    .filter((l) => String(l.item_id) === fItem.value)
    .reduce((x, l) => x + (Number(l.qty) || 0), 0)
}, 0))
const withPhil = computed(() => rows.value.filter((r) => r.has_philhealth).length)
const withoutPhil = computed(() => rows.value.length - withPhil.value)

/* ── print: opens the Blade slip in a new tab ── */
/* ── print: fallback to relative URL if Ziggy route is missing ── */
function printSlip(d) {
  try {
    window.open(route('pharmacy.dispenses.print', d.id), '_blank')
  } catch (e) {
    window.open(`/pharmacy/dispenses/${d.id}/print`, '_blank')
  }
}

/* ── Item report: pick an item + date range, shown inline, printable / CSV ── */
const reportOpen = ref(false)
const rItem = ref('')
const rFrom = ref('')
const rTo = ref('')
const rPage = ref(1)
const rPerPage = ref(20)
const reportPrinting = ref(false)

const todayD = new Date()
function ymd(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
function fmtMDY(v) {
  if (!v) return '—'
  const d = v instanceof Date ? v : new Date(v)
  if (isNaN(d.getTime())) return '—'
  return `${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}/${d.getFullYear()}`
}
function ymdToDate(str) {
  const [y, m, d] = str.split('-').map(Number)
  return new Date(y, m - 1, d)
}

const rangeChips = [
  { key: 'month', label: 'This month' },
  { key: 'lastMonth', label: 'Last month' },
  { key: 'quarter', label: 'This quarter' },
  { key: 'year', label: 'Year to date' },
  { key: 'all', label: 'All dates' },
]

function applyRange(key) {
  const y = todayD.getFullYear()
  const m = todayD.getMonth()
  if (key === 'month') {
    rFrom.value = ymd(new Date(y, m, 1)); rTo.value = ymd(new Date(y, m + 1, 0))
  } else if (key === 'lastMonth') {
    rFrom.value = ymd(new Date(y, m - 1, 1)); rTo.value = ymd(new Date(y, m, 0))
  } else if (key === 'quarter') {
    const q = Math.floor(m / 3) * 3
    rFrom.value = ymd(new Date(y, q, 1)); rTo.value = ymd(new Date(y, q + 3, 0))
  } else if (key === 'year') {
    rFrom.value = ymd(new Date(y, 0, 1)); rTo.value = ymd(todayD)
  } else {
    rFrom.value = ''; rTo.value = ''
  }
}

function toggleReport() {
  reportOpen.value = !reportOpen.value
  if (!reportOpen.value) return
  if (fItem.value) rItem.value = fItem.value // start from the item picked in the toolbar
  if (fMonth.value) {
    const [y, m] = fMonth.value.split('-').map(Number)
    rFrom.value = ymd(new Date(y, m - 1, 1))
    rTo.value = ymd(new Date(y, m, 0))
  } else if (!rFrom.value && !rTo.value) {
    applyRange('month')
  }
}

const rangeError = computed(() => (
  rFrom.value && rTo.value && rFrom.value > rTo.value ? '“From” must be on or before “To”.' : ''
))

const reportItem = computed(() => {
  if (!rItem.value) return null
  const found = props.items.find((i) => String(i.id) === rItem.value)
  const opt = itemOptions.value.find((o) => o.value === rItem.value)
  return {
    name: found?.name || opt?.label || 'Item',
    brand: found?.brand || '',
    lot: found?.lot || '',
    vol: found?.vol || '',
    unit: found?.unit || '',
  }
})

const reportRows = computed(() => {
  if (!rItem.value || rangeError.value) return []
  const out = []
  props.dispenses.forEach((d) => {
    const lines = (d.dispense_items || []).filter((l) => String(l.item_id) === rItem.value)
    if (!lines.length) return
    const dt = new Date(d.created_at)
    const key = ymd(dt)
    if (rFrom.value && key < rFrom.value) return
    if (rTo.value && key > rTo.value) return
    out.push({ d, date: dt, qty: lines.reduce((sum, l) => sum + (Number(l.qty) || 0), 0) })
  })
  return out.sort((a, b) => a.date - b.date || a.d.id - b.d.id)
})

const reportTotalQty = computed(() => reportRows.value.reduce((sum, r) => sum + r.qty, 0))
const reportPeriod = computed(() => {
  if (!rFrom.value && !rTo.value) return 'All dates'
  const f = rFrom.value ? fmtMDY(ymdToDate(rFrom.value)) : 'Beginning'
  const t = rTo.value ? fmtMDY(ymdToDate(rTo.value)) : 'Present'
  return `From: ${f}  To: ${t}`
})

const visibleReportRows = computed(() => {
  if (reportPrinting.value) return reportRows.value
  const start = (rPage.value - 1) * rPerPage.value
  return reportRows.value.slice(start, start + rPerPage.value)
})

watch([rItem, rFrom, rTo, rPerPage], () => { rPage.value = 1 })
watch(() => reportRows.value.length, (len) => {
  const max = Math.max(1, Math.ceil(len / rPerPage.value))
  if (rPage.value > max) rPage.value = max
})

/* While printing, the report sheet is teleported to <body>; the global print CSS at the
   bottom of this file hides everything else. */
async function printReport() {
  if (!reportRows.value.length) return
  reportPrinting.value = true
  await nextTick()
  window.print()
  reportPrinting.value = false
}
function onAfterPrint() { reportPrinting.value = false }
onMounted(() => window.addEventListener('afterprint', onAfterPrint))
onUnmounted(() => window.removeEventListener('afterprint', onAfterPrint))

function csvCell(v) {
  const s = v === null || v === undefined ? '' : String(v)
  return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
}
function downloadReportCsv() {
  if (!reportRows.value.length) return
  const header = ['No.', 'Date', 'Ref No.', 'Patient', 'Brgy', 'Age', 'Sex', 'PhilHealth', 'PhilHealth No.', 'Qty', 'Received By', 'Relationship', 'Dispensed By']
  const lines = [header.map(csvCell).join(',')]
  reportRows.value.forEach((r, i) => {
    const d = r.d
    lines.push([
      i + 1, fmtMDY(r.date), d.reference_no || '', d.full_name, d.brgy || '', ageOf(d.date_of_birth), d.sex || '',
      d.has_philhealth ? 'Yes' : 'No', d.philhealth_number || '', r.qty, d.received_by || '', d.receiver_relationship || '', d.dispense_by || '',
    ].map(csvCell).join(','))
  })
  const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `item-report_${(reportItem.value?.name || 'item').replace(/[^\w-]+/g, '_')}_${rFrom.value || 'start'}_${rTo.value || 'now'}.csv`
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 5000)
}

function remove(d) {
  if (!confirm(`Delete dispense record for "${d.full_name}"?`)) return
  router.delete(route('pharmacy.dispenses.destroy', d.id), { preserveScroll: true })
}
</script>

<template>
  <div class="dsp">
    <!-- STATS -->
    <div class="stats">
      <div class="stat"><div class="stat-n">Records</div><div class="stat-v b">{{ rows.length }}</div></div>
      <div class="stat"><div class="stat-n">{{ fItem ? 'Qty of selected item' : 'Total qty dispensed' }}</div><div class="stat-v a">{{ totalQty }}</div></div>
      <div class="stat"><div class="stat-n">With PhilHealth</div><div class="stat-v g">{{ withPhil }}</div></div>
      <div class="stat"><div class="stat-n">Without PhilHealth</div><div class="stat-v r">{{ withoutPhil }}</div></div>
    </div>

    <!-- TOOLBAR -->
    <div class="tb">
      <div class="tb-l">
        <div class="sbox">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input v-model="q" type="text" placeholder="Search patient, brgy, item, staff…" />
        </div>
        <select v-model="fPhil">
          <option value="">All PhilHealth</option>
          <option value="1">With PhilHealth</option>
          <option value="0">Without PhilHealth</option>
        </select>
        <select v-model="fMonth">
          <option value="">All months</option>
          <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
        </select>
        <select v-model="fItem">
          <option value="">All items</option>
          <option v-for="i in itemOptions" :key="i.value" :value="i.value">{{ i.label }} ({{ i.count }})</option>
        </select>
        <button v-if="hasFilters" class="btn" style="color:var(--red);border-color:#fca5a5;" @click="clearFilters">&#10005; Clear filters</button>
      </div>
      <div class="tb-r">
        <button class="btn" :class="{ 'btn-on': reportOpen }" @click="toggleReport">
          &#128196; Item report
        </button>
        <button class="btn primary" @click="openNew">+ New dispense</button>
      </div>
    </div>

    <!-- ITEM REPORT (pick item + dates; shown right here) -->
    <div v-if="reportOpen" class="rp">
      <div class="rp-panel">
        <div class="rp-head">
          <div>
            <strong>Item report</strong>
            <span class="rp-sub">Who received an item, within the dates you choose</span>
          </div>
          <button class="btn sm" @click="reportOpen = false">Close</button>
        </div>

        <div class="rp-body">
          <div class="rp-row">
            <label class="fld grow">
              <span>Item</span>
              <select v-model="rItem">
                <option value="">Select an item…</option>
                <option v-for="i in itemOptions" :key="i.value" :value="i.value">{{ i.label }}</option>
              </select>
            </label>
            <label class="fld">
              <span>From</span>
              <input v-model="rFrom" type="date" />
            </label>
            <label class="fld">
              <span>To</span>
              <input v-model="rTo" type="date" />
            </label>
          </div>
          <div class="rp-chips">
            <button v-for="c in rangeChips" :key="c.key" type="button" class="chip" @click="applyRange(c.key)">{{ c.label }}</button>
          </div>
          <div v-if="rangeError" class="rp-err">{{ rangeError }}</div>
        </div>

        <div class="rp-foot">
          <div class="rp-summary">
            <template v-if="rItem">
              <strong>{{ reportRows.length }}</strong> record{{ reportRows.length === 1 ? '' : 's' }}
              · total qty <strong>{{ reportTotalQty }}</strong> {{ reportItem?.unit }}
            </template>
            <template v-else>Select an item to build the report.</template>
          </div>
          <div class="rp-actions">
            <button class="btn" :disabled="!reportRows.length" @click="downloadReportCsv">Download CSV</button>
            <button class="btn primary" :disabled="!reportRows.length" @click="printReport">Print all ({{ reportRows.length }})</button>
          </div>
        </div>
      </div>

      <Teleport to="body" :disabled="!reportPrinting">
        <div class="rp-sheet" :class="{ 'pharm-print-root': reportPrinting }">
          <div class="rp-sheet-head">
            <div class="rp-title">Item Dispensing Report</div>
            <div class="rp-item" v-if="reportItem">{{ reportItem.name }}</div>
            <div class="rp-meta" v-if="reportItem">
              {{ [reportItem.brand, reportItem.vol, reportItem.lot ? `Lot ${reportItem.lot}` : ''].filter(Boolean).join(' · ') }}
            </div>
            <div class="rp-meta">{{ reportPeriod }}</div>
            <div class="rp-entity">{{ labSettings?.name || 'Pharmacy Supplies Inventory' }}</div>
          </div>

          <div v-if="!reportRows.length" class="rp-empty">
            {{ !rItem ? 'Select an item to see who received it.' : (rangeError || 'No one received this item in the selected dates.') }}
          </div>

          <div v-else class="rp-scroll">
            <table>
              <colgroup>
                <col style="width:4%"><col style="width:8%"><col style="width:10%"><col style="width:16%">
                <col style="width:8%"><col style="width:4%"><col style="width:4%"><col style="width:10%">
                <col style="width:6%"><col style="width:12%"><col style="width:8%"><col style="width:10%">
              </colgroup>
              <thead>
                <tr>
                  <th>No.</th><th>Date</th><th>Ref No.</th><th>Patient</th><th>Brgy</th>
                  <th>Age</th><th>Sex</th><th>PhilHealth</th><th class="r">Qty</th>
                  <th>Received By</th><th>Relationship</th><th>Dispensed By</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, i) in visibleReportRows" :key="r.d.id">
                  <td>{{ (reportPrinting ? 0 : (rPage - 1) * rPerPage) + i + 1 }}</td>
                  <td class="nw">{{ fmtMDY(r.date) }}</td>
                  <td class="nw">{{ r.d.reference_no || '—' }}</td>
                  <td>{{ r.d.full_name }}</td>
                  <td>{{ r.d.brgy || '—' }}</td>
                  <td>{{ ageOf(r.d.date_of_birth) }}</td>
                  <td>{{ r.d.sex || '—' }}</td>
                  <td>{{ r.d.has_philhealth ? (r.d.philhealth_number || 'Yes') : 'No' }}</td>
                  <td class="r">{{ r.qty }}</td>
                  <td>{{ r.d.received_by || '—' }}</td>
                  <td>{{ r.d.receiver_relationship || '—' }}</td>
                  <td>{{ r.d.dispense_by || '—' }}</td>
                </tr>
                <tr v-if="reportPrinting || rPage * rPerPage >= reportRows.length" class="tot">
                  <td colspan="8">Total ({{ reportRows.length }} record{{ reportRows.length === 1 ? '' : 's' }})</td>
                  <td class="r">{{ reportTotalQty }}</td>
                  <td colspan="3">{{ reportItem?.unit }}</td>
                </tr>
              </tbody>
            </table>

            <div class="rp-sign">
              <div><span class="line-sig"></span>Prepared by</div>
              <div><span class="line-sig"></span>Noted by</div>
            </div>
          </div>
        </div>
      </Teleport>

      <Pagination
        v-model:page="rPage"
        v-model:perPage="rPerPage"
        :total="reportRows.length"
        label="records"
      />
    </div>

    <!-- TABLE -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Ref No.</th>
            <th>Date</th>
            <th>Patient</th>
            <th>Brgy</th>
            <th>Age</th>
            <th>Sex</th>
            <th>PhilHealth</th>
            <th>Items</th>
            <th>Dispensed By</th>
            <th>Received By</th>
            <th>Relationship</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!pagedRows.length">
            <td colspan="12" class="empty">No dispense records found.</td>
          </tr>
          <tr v-for="d in pagedRows" :key="d.id">
            <td class="nowrap">
              <span class="ref-no">{{ d.reference_no || '—' }}</span>
              <div v-if="d.print_count" class="sub">Printed ×{{ d.print_count }}</div>
            </td>
            <td class="nowrap small">{{ fmtDate(d.created_at) }}</td>
            <td class="strong">{{ d.full_name }}</td>
            <td>{{ d.brgy || '—' }}</td>
            <td>{{ ageOf(d.date_of_birth) }}</td>
            <td>{{ d.sex || '—' }}</td>
            <td>
              <span v-if="d.has_philhealth" class="badge b-ok">Yes</span>
              <span v-else class="badge b-no">No</span>
              <div v-if="d.has_philhealth && d.philhealth_number" class="sub" style="font-family:var(--fm);">{{ d.philhealth_number }}</div>
              <div v-if="d.has_philhealth && d.philhealth_facility" class="sub">{{ d.philhealth_facility }}</div>
            </td>
            <td>
              <div v-for="l in d.dispense_items || []" :key="l.id" class="line" :class="{ hit: fItem && String(l.item_id) === fItem }">
                <span>{{ l.item?.name || 'Deleted item' }}</span>
                <span class="qty">×{{ l.qty }}</span>
              </div>
              <span v-if="!(d.dispense_items || []).length" class="sub">—</span>
            </td>
            <td>{{ d.dispense_by }}</td>
            <td>{{ d.received_by }}</td>
            <td>{{ d.receiver_relationship || '—' }}</td>
            <td class="nowrap">
              <button class="btn sm" @click="printSlip(d)">Print</button>
              <button class="btn sm" style="margin-left:4px;" @click="openEdit(d)">Edit</button>
              <button class="btn sm danger" style="margin-left:4px;" @click="remove(d)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <Pagination
      v-model:page="page"
      v-model:perPage="perPage"
      :total="rows.length"
      label="records"
    />

    <DispenseModal v-model:show="showModal" :dispense="editing" :items="items" :transactions="transactions" />
  </div>
</template>

<style scoped>
/* Uses CSS variables defined on .pharm-app in the parent page */
.dsp { margin-top: 16px; }

/* STAT CARDS */
.stats { display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px; }
.stat { background:var(--bg);border:1px solid var(--c5);border-radius:var(--r2);padding:16px 18px; }
.stat-n { font-size:11px;color:var(--c3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px; }
.stat-v { font-size:26px;font-weight:700; }
.stat-v.b { color:var(--accent); }
.stat-v.a { color:var(--amber); }
.stat-v.g { color:var(--green); }
.stat-v.r { color:var(--red); }

/* TOOLBAR */
.tb { display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap; }
.tb-l { display:flex;gap:8px;flex:1;flex-wrap:wrap;align-items:center; }
.tb-r { display:flex;gap:8px;align-items:center; }
.sbox { position:relative;flex:1;min-width:220px;max-width:340px; }
.sbox svg { position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--c3);pointer-events:none; }
/* higher specificity than the parent's `:deep(input[type=text])` rule, so the icon no longer overlaps the text */
.dsp .sbox input[type=text] { padding-left:32px;width:100%;box-sizing:border-box; }

.btn { height:34px;padding:0 14px;border:1px solid var(--c5);border-radius:var(--r);background:var(--bg);color:var(--c);cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500; }
.btn:hover { background:var(--c6); }
.btn-off { opacity:.55; }
.btn.primary { background:var(--accent);color:#fff;border-color:var(--accent); }
.btn.primary:hover { background:#1d3a98; }
.btn.sm { height:26px;padding:0 8px;font-size:11px; }
.btn.danger { color:var(--red);border-color:#fca5a5; }
.btn.danger:hover { background:var(--red2); }

/* TABLE */
.tbl-wrap { background:var(--bg);border:1px solid var(--c5);border-radius:var(--r2);overflow-x:auto; }
.tbl-wrap table { width:100%;border-collapse:collapse; }
.tbl-wrap th { font-size:10px;font-weight:600;color:var(--c2);text-align:left;padding:10px 12px;background:var(--c7);border-bottom:1px solid var(--c5);text-transform:uppercase;letter-spacing:.06em;white-space:nowrap; }
.tbl-wrap td { padding:10px 12px;border-bottom:1px solid var(--c6);vertical-align:middle; }
.tbl-wrap tr:last-child td { border-bottom:none; }
.tbl-wrap tbody tr:hover td { background:var(--c7); }
.num { text-align:right; }
.nowrap { white-space:nowrap; }
.small { font-size:11px; }
.strong { font-weight:600; }
.sub { font-size:10px;color:var(--c2);margin-top:2px; }
.ref-no { font-family:var(--fm);font-size:11px;font-weight:600;color:var(--accent); }
.empty { text-align:center;padding:2.5rem;color:var(--c3);font-size:13px; }

/* DISPENSED ITEM LINES */
.line { display:flex;justify-content:space-between;gap:12px;font-size:12px;padding:1px 0;min-width:170px; }
.line .qty { font-weight:700;color:var(--accent); }
.line.hit { background:var(--accent2);border-radius:4px;padding:1px 6px; }

/* BADGES */
.badge { display:inline-block;font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px; }
.b-ok { background:var(--green2);color:var(--green); }
.b-no { background:var(--c6);color:var(--c2); }

/* ITEM REPORT PANEL */
.btn.btn-on { background:var(--accent2);border-color:var(--accent2);color:var(--accent); }
.rp { margin-bottom:18px; }
.rp-panel { background:var(--bg);border:1px solid var(--c5);border-radius:var(--r2);margin-bottom:12px; }
.rp-head { display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 18px;border-bottom:1px solid var(--c6); }
.rp-sub { margin-left:10px;font-size:12px;color:var(--c3); }
.rp-body { padding:14px 18px; }
.rp-row { display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end; }
.fld { display:flex;flex-direction:column;gap:4px;min-width:170px; }
.fld.grow { flex:1;min-width:260px; }
.fld > span { font-size:11px;color:var(--c2); }
.fld select,.fld input { width:100%; }
.rp-chips { display:flex;gap:6px;flex-wrap:wrap;margin-top:10px; }
.chip { height:30px;padding:0 12px;border:1px solid var(--c5);border-radius:20px;background:var(--c7);color:var(--c2);font-size:12px;font-weight:500;cursor:pointer; }
.chip:hover { background:var(--accent2);border-color:var(--accent2);color:var(--accent); }
.rp-err { margin-top:8px;font-size:12px;color:var(--red); }
.rp-foot { display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 18px;border-top:1px solid var(--c6); }
.rp-summary { font-size:13px; }
.rp-actions { display:flex;gap:8px; }
.btn:disabled { opacity:.5;cursor:not-allowed; }

/* report sheet — hard-coded colours because it is teleported to <body> when printing */
.rp-sheet { background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:18px 18px 12px;color:#111;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.35; }
.rp-sheet-head { text-align:center;padding-bottom:10px;border-bottom:1px solid #111;margin-bottom:12px; }
.rp-title { font-size:13px;font-weight:700; }
.rp-item { margin-top:6px;font-size:13px;font-weight:700; }
.rp-meta { margin-top:2px; }
.rp-entity { margin-top:8px;font-size:12px;font-weight:700;text-transform:uppercase; }
.rp-empty { text-align:center;padding:36px 0;color:#64748b;font-size:13px; }
.rp-scroll { overflow-x:auto; }
.rp-sheet table { width:100%;min-width:1000px;border-collapse:collapse;table-layout:fixed;border:1px solid #111; }
.rp-sheet thead { display:table-header-group; }
.rp-sheet tr { break-inside:avoid;page-break-inside:avoid; }
.rp-sheet th,.rp-sheet td { border:1px solid #111;padding:3px 5px;vertical-align:top;word-wrap:break-word;font-size:10px;text-align:left;background:transparent;color:#111;text-transform:none;letter-spacing:0; }
.rp-sheet th { font-weight:700;background:#f1f5f9; }
.rp-sheet .r { text-align:right; }
.rp-sheet .nw { white-space:nowrap; }
.rp-sheet tr.tot td { font-weight:700;background:#f1f5f9; }
.rp-sign { display:flex;justify-content:space-around;gap:40px;margin-top:34px;font-size:10px;text-align:center; }
.rp-sign > div { flex:1;max-width:260px; }
.line-sig { display:block;border-top:1px solid #111;margin-bottom:3px; }

@media (max-width: 800px) {
  .stats { grid-template-columns:repeat(2,1fr); }
}
</style>

<!-- GLOBAL print rules: when a report is printing, show only its sheet -->
<style>
@media print {
  @page { size: A4 landscape; margin: 10mm; }
  body > *:not(.pharm-print-root) { display: none !important; }
  .pharm-print-root { display: block !important; border: 0 !important; border-radius: 0 !important; padding: 0 !important; margin: 0 !important; }
  .pharm-print-root .rp-scroll { overflow: visible !important; }
  .pharm-print-root table { min-width: 0 !important; }
  .pharm-print-root th, .pharm-print-root tr.tot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>