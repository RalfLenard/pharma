<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { fmtDate, monthLabel, monthKey, parseLocalDate } from '@/composables/Usepharmacycalc'

const props = defineProps({
  items: { type: Array, required: true },
  wastageRecords: { type: Array, required: true },
})
const emit = defineEmits(['add'])

const search = ref('')
const typeFilter = ref('')
const monthFilter = ref('')

const monthOptions = computed(() => {
  const keys = new Set(props.wastageRecords.map((w) => {
    const d = parseLocalDate(w.date)
    return monthKey(d.getFullYear(), d.getMonth())
  }))
  return [...keys].sort((a, b) => b - a).map((k) => ({ key: k, label: monthLabel(Math.floor(k / 100), k % 100) }))
})

const curKey = monthKey(new Date().getFullYear(), new Date().getMonth())

const rows = computed(() => [...props.wastageRecords].reverse().filter((w) => {
  const q = search.value.toLowerCase()
  const matchesQ = !q || w.item_name?.toLowerCase().includes(q) || (w.reason || '').toLowerCase().includes(q)
  const matchesType = !typeFilter.value || w.type === typeFilter.value
  const d = parseLocalDate(w.date)
  const matchesMonth = !monthFilter.value || monthKey(d.getFullYear(), d.getMonth()) === parseInt(monthFilter.value)
  return matchesQ && matchesType && matchesMonth
}))

const kpis = computed(() => {
  const thisMonth = props.wastageRecords.filter((w) => { const d = parseLocalDate(w.date); return monthKey(d.getFullYear(), d.getMonth()) === curKey })
  return {
    total: props.wastageRecords.length,
    thisMonthQty: thisMonth.reduce((s, w) => s + w.qty, 0),
    expired: props.wastageRecords.filter((w) => w.type === 'expired').length,
    spoiled: props.wastageRecords.filter((w) => w.type === 'spoiled').length,
  }
})

const typeLabels = { expired: 'Expired', spoiled: 'Spoiled / Contaminated', broken: 'Broken / Damaged', other: 'Other' }
function typeClass(t) {
  return { expired: 'bg-red-100 text-red-800', spoiled: 'bg-orange-100 text-orange-800', broken: 'bg-purple-100 text-purple-800', other: 'bg-slate-100 text-slate-600' }[t]
}

function deleteWastage(w) {
  if (!confirm(`Delete wastage record for "${w.item_name}" (${w.qty} ${w.item_unit})?`)) return
  router.delete(route('pharmacy.wastage.destroy', w.id), { preserveScroll: true })
}
</script>

<template>
  <div>
    <div class="rounded-lg p-5 mb-5 flex items-start justify-between flex-wrap gap-3" style="background:linear-gradient(135deg,#7f1d1d,#991b1b);">
      <div>
        <div class="text-white font-semibold text-sm">Wastage &amp; Spoilage Record</div>
        <div class="text-red-200 text-[11px] mt-1">Track discarded, expired, spoiled, or broken supplies</div>
      </div>
      <button class="btn primary" @click="emit('add')">+ Record Wastage</button>
    </div>

    <div class="grid grid-cols-4 gap-3 mb-5">
      <div class="bg-white border border-slate-200 rounded-lg p-4"><div class="stat-label">Total records</div><div class="stat-val">{{ kpis.total }}</div></div>
      <div class="bg-white border border-slate-200 rounded-lg p-4"><div class="stat-label">This month</div><div class="stat-val">{{ kpis.thisMonthQty }}</div></div>
      <div class="bg-white border border-slate-200 rounded-lg p-4"><div class="stat-label">Expired</div><div class="stat-val">{{ kpis.expired }}</div></div>
      <div class="bg-white border border-slate-200 rounded-lg p-4"><div class="stat-label">Spoiled</div><div class="stat-val">{{ kpis.spoiled }}</div></div>
    </div>

    <div class="flex items-center gap-2 mb-3.5 flex-wrap">
      <input v-model="search" type="text" placeholder="Search item, reason…" class="tbl-input flex-1 min-w-[160px]" />
      <select v-model="typeFilter" class="tbl-input">
        <option value="">All types</option>
        <option value="expired">Expired</option><option value="spoiled">Spoiled / Contaminated</option>
        <option value="broken">Broken / Damaged</option><option value="other">Other</option>
      </select>
      <select v-model="monthFilter" class="tbl-input">
        <option value="">All months</option>
        <option v-for="m in monthOptions" :key="m.key" :value="m.key">{{ m.label }}</option>
      </select>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200">
            <th class="th">Date</th><th class="th">Supply Item</th><th class="th">Section</th>
            <th class="th">Type</th><th class="th text-right">Qty</th><th class="th">Unit</th>
            <th class="th">Lot</th><th class="th">Reason</th><th class="th">By</th><th class="th">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!rows.length"><td colspan="10" class="text-center py-10 text-slate-400">No wastage records.</td></tr>
          <tr v-for="w in rows" :key="w.id" class="border-b border-slate-100 hover:bg-slate-50">
            <td class="td">{{ fmtDate(w.date) }}</td>
            <td class="td font-medium">{{ w.item_name }}</td>
            <td class="td">{{ w.item_sec || '—' }}</td>
            <td class="td"><span class="badge" :class="typeClass(w.type)">{{ typeLabels[w.type] }}</span></td>
            <td class="td text-right">{{ w.qty }}</td>
            <td class="td">{{ w.item_unit || '—' }}</td>
            <td class="td">{{ w.item_lot || '—' }}</td>
            <td class="td text-slate-500">{{ w.reason }}</td>
            <td class="td">{{ w.by || '—' }}</td>
            <td class="td"><button class="abt del" @click="deleteWastage(w)">Delete</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.th { font-size: 10px; font-weight: 600; color: #64748b; text-align: left; padding: 10px 12px; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }
.td { padding: 10px 12px; }
.tbl-input { height: 34px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; outline: none; font-size: 12px; }
.badge { display: inline-block; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
.btn.primary { height: 34px; padding: 0 14px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #fff; color: #991b1b; cursor: pointer; border: none; }
.stat-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
.stat-val { font-size: 22px; font-weight: 700; color: #991b1b; }
.abt.del { font-size: 10px; padding: 3px 8px; border: 1px solid #fca5a5; border-radius: 4px; background: none; color: #991b1b; cursor: pointer; }
</style>