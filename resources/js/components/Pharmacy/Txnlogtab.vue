<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { fmtDate, monthLabel, monthKey, parseLocalDate } from '@/composables/Usepharmacycalc'
import EditTxnModal from '@/components/Pharmacy/modals/Edittxnmodal.vue'

const props = defineProps({
  items: { type: Array, required: true },
  transactions: { type: Array, required: true },
})
const emit = defineEmits(['stock'])

const search = ref('')
const typeFilter = ref('')
const monthFilter = ref('')

const itemsById = computed(() => Object.fromEntries(props.items.map((i) => [i.id, i])))

const monthOptions = computed(() => {
  const keys = new Set(props.transactions.map((t) => {
    const d = parseLocalDate(t.date)
    return monthKey(d.getFullYear(), d.getMonth())
  }))
  return [...keys].sort((a, b) => b - a).map((k) => ({ key: k, label: monthLabel(Math.floor(k / 100), k % 100) }))
})

const rows = computed(() => [...props.transactions].reverse().filter((t) => {
  const item = itemsById.value[t.item_id]
  const q = search.value.toLowerCase()
  const matchesQ = !q || (item && item.name.toLowerCase().includes(q)) || (t.note || '').toLowerCase().includes(q)
  const matchesType = !typeFilter.value
    || (typeFilter.value === 'wastage' ? (t.note || '').startsWith('[WASTAGE/') : t.type === typeFilter.value)
  const d = parseLocalDate(t.date)
  const matchesMonth = !monthFilter.value || monthKey(d.getFullYear(), d.getMonth()) === parseInt(monthFilter.value)
  return matchesQ && matchesType && matchesMonth
}))

function typeBadge(t) {
  return { in: 'bg-green-100 text-green-900', out: 'bg-red-100 text-red-900', adj: 'bg-purple-100 text-purple-900' }[t]
}

function deleteTxn(t) {
  if (!confirm('Delete this transaction?')) return
  router.delete(route('pharmacy.transactions.destroy', t.id), { preserveScroll: true })
}

/* Edit transaction modal */
const editTxnModal = ref({ show: false, txn: null })

function openEditTxn(t) {
  editTxnModal.value = { show: true, txn: t }
}

const editTxnItemName = computed(() => {
  const t = editTxnModal.value.txn
  return t ? (itemsById.value[t.item_id]?.name || '') : ''
})
</script>

<template>
  <div>
    <div class="flex items-center gap-2 mb-3.5 flex-wrap">
      <input v-model="search" type="text" placeholder="Search item, note…" class="tbl-input flex-1 min-w-[160px]" />
      <select v-model="typeFilter" class="tbl-input">
        <option value="">All types</option>
        <option value="in">Stock in</option><option value="out">Stock out</option>
        <option value="adj">Adjustment</option><option value="wastage">Wastage only</option>
      </select>
      <select v-model="monthFilter" class="tbl-input">
        <option value="">All months</option>
        <option v-for="m in monthOptions" :key="m.key" :value="m.key">{{ m.label }}</option>
      </select>
      <div class="flex-1"></div>
      <button class="btn green" @click="emit('stock', null, 'in')">+ Stock in</button>
      <button class="btn red" @click="emit('stock', null, 'out')">&minus; Stock out</button>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200">
            <th class="th">Date</th><th class="th">Lot No.</th><th class="th">Item</th><th class="th">Type</th>
            <th class="th text-right">Qty</th><th class="th">Unit</th><th class="th">Note</th><th class="th">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!rows.length"><td colspan="7" class="text-center py-10 text-slate-400">No transactions found.</td></tr>
          <tr v-for="t in rows" :key="t.id" class="border-b border-slate-100 hover:bg-slate-50">
            <td class="td">{{ fmtDate(t.date) }}</td>
            <td class="td font-medium">{{ itemsById[t.item_id]?.lot || '—' }}</td>
            <td class="td font-medium">{{ itemsById[t.item_id]?.name || '—' }}</td>
            <td class="td"><span class="badge" :class="typeBadge(t.type)">{{ t.type }}</span></td>
            <td class="td text-right">{{ t.qty }}</td>
            <td class="td">{{ itemsById[t.item_id]?.unit || '—' }}</td>
            <td class="td text-slate-500">{{ t.note || '—' }}</td>
            <td class="td" style="white-space:nowrap;">
              <button class="abt in" @click="openEditTxn(t)">Edit</button>
              <button class="abt del" @click="deleteTxn(t)">Del</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EditTxnModal v-model:show="editTxnModal.show" :txn="editTxnModal.txn" :item-name="editTxnItemName" />
  </div>
</template>

<style scoped>
.th { font-size: 10px; font-weight: 600; color: #64748b; text-align: left; padding: 10px 12px; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }
.td { padding: 10px 12px; }
.tbl-input { height: 34px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; outline: none; font-size: 12px; }
.badge { display: inline-block; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; text-transform: capitalize; }
.btn { height: 34px; padding: 0 14px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; cursor: pointer; font-size: 12px; font-weight: 500; }
.btn.green { background: #dcfce7; color: #166534; border-color: #86efac; }
.btn.red { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
.abt.in { font-size: 10px; padding: 3px 8px; border: 1px solid #93c5fd; border-radius: 4px; background: none; color: #1e40af; cursor: pointer; margin-right: 4px; }
.abt.del { font-size: 10px; padding: 3px 8px; border: 1px solid #fca5a5; border-radius: 4px; background: none; color: #991b1b; cursor: pointer; }
</style>