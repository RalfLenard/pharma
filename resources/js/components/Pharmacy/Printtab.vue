<script setup>
import { computed } from 'vue'
import { stockForMonth, monthLabel, fmtDate } from '@/composables/Usepharmacycalc'

const props = defineProps({
  items: { type: Array, required: true },
  transactions: { type: Array, required: true },
  curKey: { type: Number, required: true },
  labSettings: { type: Object, required: true },
})

const rows = computed(() => props.items
  .filter((i) => !i.archived)
  .map((i) => ({ item: i, ms: stockForMonth(props.transactions, i.id, props.curKey) }))
  .sort((a, b) => a.item.name.localeCompare(b.item.name)))

const y = Math.floor(props.curKey / 100)
const m = props.curKey % 100
</script>

<template>
  <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
    <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 flex justify-between items-center">
      <div>
        <div class="text-sm font-semibold">Inventory Snapshot</div>
        <div class="text-[11px] text-slate-400 mt-0.5">{{ monthLabel(y, m) }}</div>
      </div>
      <button class="btn primary" onclick="window.print()">Print</button>
    </div>
    <div class="p-4">
      <div class="mb-3">
        <div class="font-semibold">{{ labSettings.name || 'Pharmacy Supplies Inventory' }}</div>
        <div class="text-[11px] text-slate-500">{{ labSettings.address }}</div>
      </div>
      <table class="w-full border-collapse">
        <thead>
          <tr class="border-b-2 border-slate-800">
            <th class="th">Supply Item</th><th class="th">Lot</th><th class="th">Expiry</th>
            <th class="th text-right">Stock In</th><th class="th text-right">Stock Out</th><th class="th text-right">Current</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.item.id" class="border-b border-slate-200">
            <td class="td">{{ r.item.name }}</td>
            <td class="td">{{ r.item.lot || '—' }}</td>
            <td class="td">{{ fmtDate(r.item.exp) }}</td>
            <td class="td text-right">{{ r.ms.in }}</td>
            <td class="td text-right">{{ r.ms.out }}</td>
            <td class="td text-right font-semibold">{{ r.ms.curr }}</td>
          </tr>
        </tbody>
      </table>
      <div class="flex gap-5 mt-4 pt-3 border-t border-slate-200 text-[11px] text-slate-500">
        <span>Total items: {{ rows.length }}</span>
        <span>Total current stock: {{ rows.reduce((s, r) => s + r.ms.curr, 0) }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.th { font-size: 10px; font-weight: 600; color: #64748b; text-align: left; padding: 8px 10px; text-transform: uppercase; }
.td { padding: 8px 10px; font-size: 12px; }
.btn.primary { height: 34px; padding: 0 14px; border-radius: 6px; font-size: 12px; font-weight: 500; background: #1e40af; color: #fff; border: none; cursor: pointer; }
</style>