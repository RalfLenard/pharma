<script setup>
import { ref, computed } from 'vue'
import {
  stockForItem, stockMonthOnly, prevMonthKey, monthLabel, parseLocalDate, monthKey, FUND_SOURCES,
} from '@/composables/Usepharmacycalc'

const props = defineProps({
  items: { type: Array, required: true },
  transactions: { type: Array, required: true },
  wastageRecords: { type: Array, required: true },
  curKey: { type: Number, required: true },
  curYear: { type: Number, required: true },
  curMonth: { type: Number, required: true },
})

const fSec = ref('')
const fFund = ref('')
const sections = computed(() => [...new Set(props.items.map((i) => i.sec).filter(Boolean))].sort())

const rows = computed(() => {
  const k = props.curKey
  const prevKey = prevMonthKey(k)
  const list = props.items
    .filter((i) => !fSec.value || i.sec === fSec.value)
    .filter((i) => !fFund.value || i.fund === fFund.value)
    .sort((a, b) => (a.sec || '').localeCompare(b.sec || '') || a.name.localeCompare(b.name))

  return list.map((i) => {
    const prevStock = stockForItem(props.transactions, i.id, prevKey).curr
    const thisMonth = stockMonthOnly(props.transactions, i.id, k)
    const itemWastage = props.wastageRecords
      .filter((w) => { const d = parseLocalDate(w.date); return w.item_id === i.id && monthKey(d.getFullYear(), d.getMonth()) === k })
      .reduce((s, w) => s + w.qty, 0)
    const consumed = props.transactions
      .filter((t) => { const d = parseLocalDate(t.date); return t.item_id === i.id && t.type === 'out' && monthKey(d.getFullYear(), d.getMonth()) === k && !(t.note && t.note.startsWith('[WASTAGE/')) })
      .reduce((s, t) => s + t.qty, 0)
    const endBal = Math.max(0, prevStock + thisMonth.in - consumed - itemWastage)
    const remarks = []
    if (endBal === 0) remarks.push('Out of stock')
    else if (endBal < i.min) remarks.push('Low stock')
    if (itemWastage > 0) remarks.push(`${itemWastage} wasted`)
    return {
      item: i, prevStock, received: thisMonth.in, consumed, wastage: itemWastage, endBal,
      remarks: remarks.join(', '),
    }
  })
})

const kpis = computed(() => ({
  totalReceived: rows.value.reduce((s, r) => s + r.received, 0),
  totalConsumed: rows.value.reduce((s, r) => s + r.consumed, 0),
  totalWastage: rows.value.reduce((s, r) => s + r.wastage, 0),
  totalEndBal: rows.value.reduce((s, r) => s + r.endBal, 0),
}))

function exportExcel() {
  const mLabel = monthLabel(props.curYear, props.curMonth)
  const lines = []
  lines.push('Monthly Inventory Consumption Report')
  lines.push(`Period: ${mLabel}`)
  lines.push('')
  lines.push('Supply Item\tUnit\tCategory\tFund Source\tBeg. Balance\tReceived\tConsumed\tWastage\tEnd Balance\tRemarks')
  rows.value.forEach((r) => {
    lines.push(`${r.item.name}\t${r.item.unit || ''}\t${r.item.sec || ''}\t${r.item.fund || ''}\t${r.prevStock}\t${r.received}\t${r.consumed}\t${r.wastage}\t${r.endBal}\t${r.remarks}`)
  })
  const blob = new Blob([lines.join('\n')], { type: 'text/tab-separated-values;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `Monthly_Inventory_${mLabel.replace(' ', '_')}.xls`
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div>
    <div class="bg-blue-800 rounded-lg p-5 mb-5 flex items-start justify-between flex-wrap gap-3">
      <div>
        <div class="text-white font-semibold text-sm">Monthly Inventory Consumption Report</div>
        <div class="text-blue-200 text-[11px] mt-1">Beginning balance · Received · Consumed · Wastage · Ending balance</div>
      </div>
      <div class="flex gap-2 items-center flex-wrap">
        <select v-model="fSec" class="tbl-input text-xs">
          <option value="">All sections</option>
          <option v-for="s in sections" :key="s" :value="s">{{ s }}</option>
        </select>
        <select v-model="fFund" class="tbl-input text-xs">
          <option value="">All funds</option>
          <option v-for="f in FUND_SOURCES" :key="f" :value="f">{{ f }}</option>
        </select>
        <button class="btn" style="background:rgba(255,255,255,.15);color:#fff;" onclick="window.print()">Print / PDF</button>
        <button class="btn" style="background:#16a34a;color:#fff;" @click="exportExcel">Export Excel</button>
      </div>
    </div>

    <div class="grid grid-cols-4 gap-3 mb-5">
      <div class="bg-white border border-slate-200 rounded-lg p-4"><div class="stat-label">Received</div><div class="stat-val">{{ kpis.totalReceived }}</div></div>
      <div class="bg-white border border-slate-200 rounded-lg p-4"><div class="stat-label">Consumed</div><div class="stat-val">{{ kpis.totalConsumed }}</div></div>
      <div class="bg-white border border-slate-200 rounded-lg p-4"><div class="stat-label">Wastage</div><div class="stat-val">{{ kpis.totalWastage }}</div></div>
      <div class="bg-white border border-slate-200 rounded-lg p-4"><div class="stat-label">End balance</div><div class="stat-val">{{ kpis.totalEndBal }}</div></div>
    </div>

    <div class="text-[11px] text-slate-600 mb-2.5 bg-blue-50 border border-blue-200 rounded-md p-2.5">
      <strong class="text-blue-800">Formula:</strong> Beginning Balance + Received − Consumed − Wastage = Ending Balance
    </div>

    <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200">
            <th class="th">Supply Item</th><th class="th">Unit</th><th class="th">Category</th><th class="th">Fund Source</th>
            <th class="th text-right">Beg. Balance</th><th class="th text-right">Received</th>
            <th class="th text-right">Consumed</th><th class="th text-right">Wastage</th>
            <th class="th text-right">End Balance</th><th class="th">Remarks</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.item.id" class="border-b border-slate-100">
            <td class="td font-medium">{{ r.item.name }}</td>
            <td class="td">{{ r.item.unit || '—' }}</td>
            <td class="td">{{ r.item.sec || '—' }}</td>
            <td class="td">{{ r.item.fund || '—' }}</td>
            <td class="td text-right">{{ r.prevStock }}</td>
            <td class="td text-right">{{ r.received }}</td>
            <td class="td text-right">{{ r.consumed }}</td>
            <td class="td text-right">{{ r.wastage }}</td>
            <td class="td text-right font-semibold">{{ r.endBal }}</td>
            <td class="td text-slate-500">{{ r.remarks }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="grid grid-cols-3 gap-6 mt-8">
      <div class="text-center"><div class="border-t border-slate-900 pt-1.5 text-[11px] font-semibold">Prepared by</div><div class="text-[10px] text-slate-500 mt-0.5">Pharmacy Technician</div></div>
      <div class="text-center"><div class="border-t border-slate-900 pt-1.5 text-[11px] font-semibold">Reviewed by</div><div class="text-[10px] text-slate-500 mt-0.5">Pharmacy Supervisor</div></div>
      <div class="text-center"><div class="border-t border-slate-900 pt-1.5 text-[11px] font-semibold">Noted by</div><div class="text-[10px] text-slate-500 mt-0.5">Chief Pharmacist</div></div>
    </div>
  </div>
</template>

<style scoped>
.th { font-size: 10px; font-weight: 600; color: #64748b; text-align: left; padding: 10px 12px; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }
.td { padding: 10px 12px; }
.tbl-input { height: 34px; padding: 0 10px; border-radius: 6px; border: none; }
.btn { height: 34px; padding: 0 14px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; border: 1px solid transparent; }
.stat-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
.stat-val { font-size: 22px; font-weight: 700; color: #1e40af; }
</style>