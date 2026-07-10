<script setup>
import { ref, computed } from 'vue'
import { stockForItem, monthKey } from '@/composables/Usepharmacycalc'

const props = defineProps({
  items: { type: Array, required: true },
  transactions: { type: Array, required: true },
})

const period = ref(String(new Date().getMonth() + 1))
const fSec = ref('')

const sections = computed(() => [...new Set(props.items.map((i) => i.sec).filter(Boolean))].sort())

const QUARTERS = { Q1: [0, 1, 2], Q2: [3, 4, 5], Q3: [6, 7, 8], Q4: [9, 10, 11] }

const validKeys = computed(() => {
  const v = period.value
  if (v === 'all') return null
  if (v.startsWith('Q')) {
    const months = QUARTERS[v]
    const keys = new Set()
    props.transactions.forEach((t) => {
      const d = new Date(t.date)
      if (months.includes(d.getMonth())) keys.add(monthKey(d.getFullYear(), d.getMonth()))
    })
    return keys
  }
  const mNum = parseInt(v) - 1
  const keys = new Set()
  props.transactions.forEach((t) => {
    const d = new Date(t.date)
    if (d.getMonth() === mNum) keys.add(monthKey(d.getFullYear(), d.getMonth()))
  })
  return keys
})

const periodMonthCount = computed(() => {
  const v = period.value
  if (v === 'all') return 12
  if (v.startsWith('Q')) return 3
  return 1
})

const ranking = computed(() => {
  const active = props.items.filter((i) => !i.archived && (!fSec.value || i.sec === fSec.value))
  return active.map((i) => {
    const consumed = props.transactions
      .filter((t) => t.item_id === i.id && t.type === 'out'
        && (!validKeys.value || validKeys.value.has(monthKey(new Date(t.date).getFullYear(), new Date(t.date).getMonth()))))
      .reduce((s, t) => s + t.qty, 0)
    const avgPerMonth = consumed / periodMonthCount.value
    const currentStock = stockForItem(props.transactions, i.id, 999999).curr
    const daysOfStock = avgPerMonth > 0 ? Math.round((currentStock / avgPerMonth) * 30) : null
    const velocity = avgPerMonth === 0 ? 'none' : avgPerMonth >= 50 ? 'high' : avgPerMonth >= 10 ? 'med' : 'low'
    const suggestedOrder = avgPerMonth > 0 ? Math.max(0, Math.round(avgPerMonth * 2 - currentStock)) : 0
    return { item: i, consumed, avgPerMonth, currentStock, daysOfStock, velocity, suggestedOrder }
  }).sort((a, b) => b.consumed - a.consumed)
})

const top10 = computed(() => ranking.value.slice(0, 10))
const maxConsumed = computed(() => Math.max(1, ...top10.value.map((r) => r.consumed)))

function velocityClass(v) {
  return { high: 'bg-red-100 text-red-800', med: 'bg-amber-100 text-amber-800', low: 'bg-green-100 text-green-800', none: 'bg-slate-100 text-slate-400' }[v]
}
</script>

<template>
  <div>
    <div class="bg-blue-800 rounded-lg p-5 mb-5 flex items-start justify-between flex-wrap gap-3">
      <div>
        <div class="text-white font-semibold text-sm">Fast-Moving Supplies &amp; Order Planner</div>
        <div class="text-blue-200 text-[11px] mt-1">Ranked by consumption · helps you plan what to order</div>
      </div>
      <div class="flex gap-2 items-center flex-wrap">
        <select v-model="period" class="tbl-input text-xs">
          <option value="1">January</option><option value="2">February</option><option value="3">March</option>
          <option value="4">April</option><option value="5">May</option><option value="6">June</option>
          <option value="7">July</option><option value="8">August</option><option value="9">September</option>
          <option value="10">October</option><option value="11">November</option><option value="12">December</option>
          <option value="Q1">Q1 (Jan–Mar)</option><option value="Q2">Q2 (Apr–Jun)</option>
          <option value="Q3">Q3 (Jul–Sep)</option><option value="Q4">Q4 (Oct–Dec)</option>
          <option value="all">All time</option>
        </select>
        <select v-model="fSec" class="tbl-input text-xs">
          <option value="">All sections</option>
          <option v-for="s in sections" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
    </div>

    <div class="text-xs font-bold uppercase text-slate-400 tracking-wide mb-2">Top 10 chart</div>
    <div class="bg-white border border-slate-200 rounded-lg p-4 mb-5">
      <div class="text-[11px] text-slate-400 mb-2">Units consumed (stock out)</div>
      <div class="flex items-end gap-1.5 h-24">
        <div v-for="r in top10" :key="r.item.id" class="flex-1 flex flex-col items-center gap-1 min-w-0">
          <span class="text-[9px] font-semibold text-slate-500">{{ r.consumed }}</span>
          <div class="w-full bg-blue-700 rounded-t" :style="{ height: (r.consumed / maxConsumed * 90) + 'px' }"></div>
          <span class="text-[9px] text-slate-400 truncate w-full text-center">{{ r.item.name }}</span>
        </div>
      </div>
    </div>

    <div class="text-xs font-bold uppercase text-slate-400 tracking-wide mb-2">Full analytics &amp; order guide</div>
    <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200">
            <th class="th">#</th><th class="th">Supply Item</th><th class="th">Section</th>
            <th class="th text-right">Consumed</th><th class="th text-right">Avg/mo</th>
            <th class="th text-right">Current stock</th><th class="th">Days of stock</th>
            <th class="th">Velocity</th><th class="th text-right">Suggested order</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, idx) in ranking" :key="r.item.id" class="border-b border-slate-100 hover:bg-slate-50">
            <td class="td">{{ idx + 1 }}</td>
            <td class="td font-medium">{{ r.item.name }}</td>
            <td class="td">{{ r.item.sec || '—' }}</td>
            <td class="td text-right">{{ r.consumed }}</td>
            <td class="td text-right">{{ r.avgPerMonth.toFixed(1) }}</td>
            <td class="td text-right">{{ r.currentStock }}</td>
            <td class="td">{{ r.daysOfStock !== null ? r.daysOfStock + ' days' : '—' }}</td>
            <td class="td"><span class="badge" :class="velocityClass(r.velocity)">{{ r.velocity }}</span></td>
            <td class="td text-right font-semibold">{{ r.suggestedOrder }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.th { font-size: 10px; font-weight: 600; color: #64748b; text-align: left; padding: 10px 12px; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }
.td { padding: 10px 12px; }
.tbl-input { height: 34px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; outline: none; }
.badge { display: inline-block; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
</style>