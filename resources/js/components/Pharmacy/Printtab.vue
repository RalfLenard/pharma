<script setup>
import { ref, computed, watch } from 'vue'
import { stockForMonth, monthLabel, fmtDate } from '@/composables/Usepharmacycalc'
import Pagination from '@/components/Pharmacy/Pagination.vue'

const props = defineProps({
  items: { type: Array, required: true },
  transactions: { type: Array, required: true },
  curKey: { type: Number, required: true },
  labSettings: { type: Object, required: true },
})

/* ── Month picker (defaults to the parent's current month) ── */
const pad = (n) => String(n).padStart(2, '0')
const toMonthStr = (k) => `${Math.floor(k / 100)}-${pad(k % 100)}`
const monthStr = ref(toMonthStr(props.curKey))
watch(() => props.curKey, (k) => { monthStr.value = toMonthStr(k) })

const key = computed(() => {
  if (!monthStr.value) return props.curKey
  const [yy, mm] = monthStr.value.split('-').map(Number)
  return yy * 100 + mm
})
const y = computed(() => Math.floor(key.value / 100))
const m = computed(() => key.value % 100)

/* ── Rows ── */
const search = ref('')

const allRows = computed(() => props.items
  .filter((i) => !i.archived)
  .map((i) => ({ item: i, ms: stockForMonth(props.transactions, i.id, key.value) }))
  .sort((a, b) => a.item.name.localeCompare(b.item.name)))

// rows shown on screen (search only affects the screen list)
const rows = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return allRows.value
  return allRows.value.filter((r) =>
    r.item.name.toLowerCase().includes(q) ||
    String(r.item.fund || '').toLowerCase().includes(q) ||
    String(r.item.lot || '').toLowerCase().includes(q))
})

/* ── Selection ── */
const selected = ref(new Set())

function toggle(id) {
  const s = new Set(selected.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selected.value = s
}

const allFilteredSelected = computed(() =>
  rows.value.length > 0 && rows.value.every((r) => selected.value.has(r.item.id)))

function toggleAll() {
  const s = new Set(selected.value)
  if (allFilteredSelected.value) rows.value.forEach((r) => s.delete(r.item.id))
  else rows.value.forEach((r) => s.add(r.item.id))
  selected.value = s
}

function clearSelection() { selected.value = new Set() }

// what gets printed: ticked items, or everything if nothing is ticked
const printRows = computed(() =>
  selected.value.size
    ? allRows.value.filter((r) => selected.value.has(r.item.id))
    : allRows.value)

const printLabel = computed(() =>
  selected.value.size
    ? `Print selected (${printRows.value.length})`
    : `Print all (${printRows.value.length})`)

/* ── Pagination (screen only) ── */
const page = ref(1)
const perPage = ref(20)

const visibleRows = computed(() => {
  const start = (page.value - 1) * perPage.value
  return rows.value.slice(start, start + perPage.value)
})

watch([perPage, search], () => { page.value = 1 })
watch(() => rows.value.length, (len) => {
  const max = Math.max(1, Math.ceil(len / perPage.value))
  if (page.value > max) page.value = max
})

const totalCurrent = computed(() => rows.value.reduce((s, r) => s + r.ms.curr, 0))
const printTotal = computed(() => printRows.value.reduce((s, r) => s + r.ms.curr, 0))

/* ── Print (own iframe document, so app CSS can't hide it) ── */
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

function buildPrintHtml() {
  const body = printRows.value.map((r) => `
    <tr>
      <td>${esc(r.item.name)}</td>
      <td>${esc(r.item.fund)}</td>
      <td>${esc(r.item.lot || '—')}</td>
      <td>${esc(fmtDate(r.item.exp))}</td>
      <td class="r">${esc(r.ms.in)}</td>
      <td class="r">${esc(r.ms.out)}</td>
      <td class="r b">${esc(r.ms.curr)}</td>
    </tr>`).join('')

  return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Inventory Snapshot - ${esc(monthLabel(y.value, m.value))}</title>
<style>
  @page { margin: 12mm; }
  * { box-sizing: border-box; }
  body { margin: 0; color: #000; background: #fff; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; }
  .title { font-size: 16px; font-weight: 700; }
  .sub { font-size: 11px; color: #333; margin-top: 2px; }
  .head { margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  th { font-size: 10px; text-transform: uppercase; text-align: left; padding: 6px 8px; border-bottom: 2px solid #000; }
  td { font-size: 11px; padding: 5px 8px; border-bottom: 1px solid #ccc; }
  .r { text-align: right; }
  .b { font-weight: 700; }
  .foot { display: flex; gap: 24px; margin-top: 12px; padding-top: 8px; border-top: 1px solid #000; font-size: 11px; }
</style>
</head>
<body>
  <div class="head">
    <div class="title">${esc(props.labSettings.name || 'Pharmacy Supplies Inventory')}</div>
    <div class="sub">${esc(props.labSettings.address)}</div>
    <div class="sub"><strong>Inventory Snapshot — ${esc(monthLabel(y.value, m.value))}</strong></div>
  </div>
  <table>
    <thead>
      <tr>
        <th>Supply Item</th><th>Fund Source</th><th>Lot</th><th>Expiry</th>
        <th class="r">Stock In</th><th class="r">Stock Out</th><th class="r">Current</th>
      </tr>
    </thead>
    <tbody>${body}</tbody>
  </table>
  <div class="foot">
    <span>Total items: ${printRows.value.length}</span>
    <span>Total current stock: ${printTotal.value}</span>
  </div>
</body>
</html>`
}

function doPrint() {
  const iframe = document.createElement('iframe')
  iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;'
  document.body.appendChild(iframe)

  const cleanup = () => { if (iframe.parentNode) iframe.parentNode.removeChild(iframe) }
  const win = iframe.contentWindow
  win.document.open()
  win.document.write(buildPrintHtml())
  win.document.close()

  win.onafterprint = cleanup
  setTimeout(() => {
    win.focus()
    win.print()
    setTimeout(cleanup, 60000) // fallback cleanup
  }, 300)
}
</script>

<template>
  <div>
    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
      <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 flex flex-wrap gap-3 justify-between items-center">
        <div>
          <div class="text-sm font-semibold">Inventory Snapshot</div>
          <div class="text-[11px] text-slate-400 mt-0.5">{{ monthLabel(y, m) }}</div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <input type="month" v-model="monthStr" class="field" title="Pick month" />
          <input type="text" v-model="search" class="field" placeholder="Search item…" />
          <button v-if="selected.size" class="btn ghost" @click="clearSelection">
            Clear ({{ selected.size }})
          </button>
          <button class="btn primary" :disabled="!printRows.length" @click="doPrint">
            {{ printLabel }}
          </button>
        </div>
      </div>

      <div class="p-4">
        <div class="mb-3">
          <div class="font-semibold">{{ labSettings.name || 'Pharmacy Supplies Inventory' }}</div>
          <div class="text-[11px] text-slate-500">{{ labSettings.address }}</div>
        </div>
        <div class="tbl-scroll">
          <table class="w-full border-collapse">
            <thead>
              <tr class="border-b-2 border-slate-800">
                <th class="th" style="width: 32px">
                  <input type="checkbox" :checked="allFilteredSelected" @change="toggleAll" title="Select all" />
                </th>
                <th class="th">Supply Item</th>
                <th class="th">Fund Source</th>
                <th class="th">Lot</th><th class="th">Expiry</th>
                <th class="th text-right">Stock In</th><th class="th text-right">Stock Out</th><th class="th text-right">Current</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!visibleRows.length">
                <td colspan="8" class="empty">No items to show.</td>
              </tr>
              <tr
                v-for="r in visibleRows"
                :key="r.item.id"
                class="border-b border-slate-200 row"
                :class="{ picked: selected.has(r.item.id) }"
                @click="toggle(r.item.id)"
              >
                <td class="td" @click.stop>
                  <input type="checkbox" :checked="selected.has(r.item.id)" @change="toggle(r.item.id)" />
                </td>
                <td class="td">{{ r.item.name }}</td>
                <td class="td">{{ r.item.fund }}</td>
                <td class="td">{{ r.item.lot || '—' }}</td>
                <td class="td">{{ fmtDate(r.item.exp) }}</td>
                <td class="td text-right">{{ r.ms.in }}</td>
                <td class="td text-right">{{ r.ms.out }}</td>
                <td class="td text-right font-semibold">{{ r.ms.curr }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex gap-5 mt-4 pt-3 border-t border-slate-200 text-[11px] text-slate-500">
          <span>Total items: {{ rows.length }}</span>
          <span>Total current stock: {{ totalCurrent }}</span>
          <span v-if="selected.size">Selected: {{ selected.size }}</span>
        </div>
      </div>
    </div>

    <Pagination
      v-model:page="page"
      v-model:perPage="perPage"
      :total="rows.length"
      label="items"
    />
  </div>
</template>

<style scoped>
.th { font-size: 10px; font-weight: 600; color: #64748b; text-align: left; padding: 8px 10px; text-transform: uppercase; white-space: nowrap; }
.td { padding: 8px 10px; font-size: 12px; }
.empty { text-align: center; padding: 2rem; color: #94a3b8; font-size: 13px; }
.tbl-scroll { overflow-x: auto; }
.row { cursor: pointer; }
.row:hover { background: #f8fafc; }
.row.picked { background: #eff6ff; }
.field { height: 34px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; background: #fff; }
.btn { height: 34px; padding: 0 14px; border-radius: 6px; font-size: 12px; font-weight: 500; border: none; cursor: pointer; }
.btn.primary { background: #1e40af; color: #fff; }
.btn.primary:hover { background: #1d3a98; }
.btn.primary:disabled { opacity: .5; cursor: not-allowed; }
.btn.ghost { background: #fff; color: #475569; border: 1px solid #cbd5e1; }
</style>