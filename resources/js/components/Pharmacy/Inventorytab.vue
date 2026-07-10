<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  stockForMonth, itemStatus, statusLabel, daysUntilExpiry, expClass, expLabel, fmtDate, FUND_SOURCES,
} from '@/composables/Usepharmacycalc'

const props = defineProps({
  items: { type: Array, required: true },
  transactions: { type: Array, required: true },
  curKey: { type: Number, required: true },
})
const emit = defineEmits(['add', 'edit', 'stock'])

const search = ref('')
const showFilters = ref(false)
const showArchived = ref(false)
const fSec = ref('')
const fStatus = ref('')
const fFund = ref('')

const sections = computed(() => [...new Set(props.items.map((i) => i.sec).filter(Boolean))].sort())

const rows = computed(() => props.items
  .filter((i) => !!i.archived === showArchived.value)
  .map((i) => {
    const ms = stockForMonth(props.transactions, i.id, props.curKey)
    const days = daysUntilExpiry(i.exp)
    return { item: i, ms, status: itemStatus(props.transactions, i, props.curKey, ms.curr), days }
  })
  .filter((r) => {
    const q = search.value.toLowerCase()
    const matchesQ = !q || r.item.name.toLowerCase().includes(q)
      || (r.item.brand || '').toLowerCase().includes(q) || (r.item.lot || '').toLowerCase().includes(q)
    const matchesSec = !fSec.value || r.item.sec === fSec.value
    const matchesStatus = !fStatus.value || r.status === fStatus.value
    const matchesFund = !fFund.value || r.item.fund === fFund.value
    return matchesQ && matchesSec && matchesStatus && matchesFund
  })
  .sort((a, b) => a.item.name.localeCompare(b.item.name)))

const stats = computed(() => {
  const active = props.items.filter((i) => !i.archived)
  const withStock = active.map((i) => stockForMonth(props.transactions, i.id, props.curKey).curr)
  const totalStock = withStock.reduce((s, v) => s + v, 0)
  const low = active.filter((i, idx) => {
    const st = itemStatus(props.transactions, i, props.curKey, withStock[idx])
    return st === 'low' || st === 'critical'
  }).length
  const expired = active.filter((i) => i.exp && new Date(i.exp) < new Date()).length
  return { count: active.length, totalStock, low, expired }
})

function statusBadgeClass(s) {
  return {
    ok: 'bg-green-100 text-green-800', low: 'bg-amber-100 text-amber-800',
    critical: 'bg-red-100 text-red-800', consume: 'bg-orange-50 text-orange-700 border border-orange-200',
    expired: 'bg-neutral-900 text-neutral-100',
  }[s]
}

function clearFilters() {
  search.value = ''; fSec.value = ''; fStatus.value = ''; fFund.value = ''
}

function deleteItem(item) {
  if (!confirm(`Delete "${item.name}"? This also removes its transaction history.`)) return
  router.delete(route('pharmacy.items.destroy', item.id), { preserveScroll: true })
}

function toggleArchive(item, archived) {
  const reason = archived ? (prompt('Archive reason (optional):') || '') : null
  router.patch(route('pharmacy.items.archive', item.id), { archived, reason }, { preserveScroll: true })
}
</script>

<template>
  <div>
    <!-- STAT CARDS -->
    <div class="grid grid-cols-4 gap-3 mb-5">
      <div class="bg-white border border-slate-200 rounded-lg p-4">
        <div class="text-[11px] text-slate-400 uppercase tracking-wide mb-1.5">Items</div>
        <div class="text-2xl font-bold">{{ stats.count }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-lg p-4">
        <div class="text-[11px] text-slate-400 uppercase tracking-wide mb-1.5">Total stock</div>
        <div class="text-2xl font-bold text-blue-800">{{ stats.totalStock }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-lg p-4">
        <div class="text-[11px] text-slate-400 uppercase tracking-wide mb-1.5">Low / critical</div>
        <div class="text-2xl font-bold text-amber-700">{{ stats.low }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-lg p-4">
        <div class="text-[11px] text-slate-400 uppercase tracking-wide mb-1.5">Expired</div>
        <div class="text-2xl font-bold text-red-800">{{ stats.expired }}</div>
      </div>
    </div>

    <!-- TOOLBAR -->
    <div class="flex items-center gap-2 mb-2 flex-wrap">
      <div class="flex-1 min-w-[220px] max-w-[340px]">
        <input v-model="search" type="text" placeholder="Search item, brand, lot…" class="tbl-input w-full" />
      </div>
      <button class="btn" @click="showFilters = !showFilters">Filters</button>
      <button class="btn" @click="showArchived = !showArchived" :class="{ 'bg-blue-50': showArchived }">
        {{ showArchived ? 'Show active' : 'Archived' }}
      </button>
      <div class="flex-1"></div>
      <button class="btn primary" @click="emit('add')">+ Add item</button>
    </div>

    <!-- FILTER PANEL -->
    <div v-if="showFilters" class="bg-white border border-slate-200 rounded-lg mb-3.5 p-3 flex flex-wrap gap-2">
      <select v-model="fSec" class="tbl-input">
        <option value="">All sections</option>
        <option v-for="s in sections" :key="s" :value="s">{{ s }}</option>
      </select>
      <select v-model="fStatus" class="tbl-input">
        <option value="">All statuses</option>
        <option value="ok">Adequate</option><option value="low">Low stock</option>
        <option value="critical">Critical</option><option value="expired">Expired</option>
        <option value="consume">Discard</option>
      </select>
      <select v-model="fFund" class="tbl-input">
        <option value="">All fund sources</option>
        <option v-for="f in FUND_SOURCES" :key="f" :value="f">{{ f }}</option>
      </select>
      <button class="btn" style="color:#991b1b" @click="clearFilters">&#10005; Clear filters</button>
    </div>

    <!-- TABLE -->
    <div class="bg-white border border-slate-200 rounded-lg overflow-x-auto">
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200">
            <th class="th">Supply Item</th>
            <th class="th">Category</th>
            <th class="th">Fund Source</th>
            <th class="th">Lot</th>
            <th class="th">Expiration</th>
            <th class="th text-right">Current Stock</th>
            <th class="th">Status</th>
            <th class="th">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!rows.length"><td colspan="8" class="text-center py-10 text-slate-400">No items found.</td></tr>
          <tr v-for="r in rows" :key="r.item.id" class="border-b border-slate-100 hover:bg-slate-50">
            <td class="td font-medium">{{ r.item.name }}<div class="text-[11px] text-slate-400">{{ r.item.brand }}</div></td>
            <td class="td">{{ r.item.sec || '—' }}</td>
            <td class="td">{{ r.item.fund || '—' }}</td>
            <td class="td">{{ r.item.lot || '—' }}</td>
            <td class="td">
              <div>{{ fmtDate(r.item.exp) }}</div>
              <span v-if="expLabel(r.days)" class="text-[11px] font-semibold" :class="{
                'text-red-800': expClass(r.days) === 'bad', 'text-orange-700': expClass(r.days) === 'w30',
                'text-amber-700': expClass(r.days) === 'w60',
              }">{{ expLabel(r.days) }}</span>
            </td>
            <td class="td text-right font-semibold">{{ r.ms.curr }} <span class="text-slate-400 font-normal">{{ r.item.unit }}</span></td>
            <td class="td"><span class="badge" :class="statusBadgeClass(r.status)">{{ statusLabel(r.status) }}</span></td>
            <td class="td whitespace-nowrap">
              <button class="abt in" @click="emit('stock', r.item.id, 'in')">+ In</button>
              <button class="abt out" @click="emit('stock', r.item.id, 'out')">&minus; Out</button>
              <button class="abt" @click="emit('edit', r.item)">Edit</button>
              <button class="abt" @click="toggleArchive(r.item, !showArchived)">{{ showArchived ? 'Restore' : 'Archive' }}</button>
              <button class="abt del" @click="deleteItem(r.item)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.th { font-size: 10px; font-weight: 600; color: #64748b; text-align: left; padding: 10px 12px; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }
.td { padding: 10px 12px; vertical-align: middle; }
.tbl-input { height: 34px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; outline: none; font-size: 12px; }
.badge { display: inline-block; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
.btn { height: 34px; padding: 0 14px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; cursor: pointer; font-size: 12px; font-weight: 500; }
.btn:hover { background: #f1f5f9; }
.btn.primary { background: #1e40af; color: #fff; border-color: #1e40af; }
.abt { font-size: 10px; padding: 3px 8px; border: 1px solid #e2e8f0; border-radius: 4px; background: none; color: #64748b; cursor: pointer; margin-right: 2px; }
.abt.in { color: #166534; border-color: #86efac; background: #dcfce7; }
.abt.out { color: #991b1b; border-color: #fca5a5; background: #fee2e2; }
.abt.del { color: #991b1b; border-color: #fca5a5; }
</style>