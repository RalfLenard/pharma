<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { stockForMonth, monthLabel, fmtDate } from '@/composables/Usepharmacycalc'
import Pagination from '@/components/Pharmacy/Pagination.vue'

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

const y = computed(() => Math.floor(props.curKey / 100))
const m = computed(() => props.curKey % 100)

/* ── Pagination (screen only — printing always outputs every row) ── */
const page = ref(1)
const perPage = ref(20)
const printingAll = ref(false)

const visibleRows = computed(() => {
  if (printingAll.value) return rows.value
  const start = (page.value - 1) * perPage.value
  return rows.value.slice(start, start + perPage.value)
})

watch(perPage, () => { page.value = 1 })

// month switched / items changed and the current page no longer exists
watch(() => rows.value.length, (len) => {
  const max = Math.max(1, Math.ceil(len / perPage.value))
  if (page.value > max) page.value = max
})

/* Show all rows while the browser print dialog is open (button AND Ctrl+P) */
function beforePrint() { printingAll.value = true }
function afterPrint() { printingAll.value = false }

onMounted(() => {
  window.addEventListener('beforeprint', beforePrint)
  window.addEventListener('afterprint', afterPrint)
})
onUnmounted(() => {
  window.removeEventListener('beforeprint', beforePrint)
  window.removeEventListener('afterprint', afterPrint)
})

async function doPrint() {
  printingAll.value = true
  await nextTick() // make sure every row is in the DOM before the dialog opens
  window.print()
  printingAll.value = false
}

const totalCurrent = computed(() => rows.value.reduce((s, r) => s + r.ms.curr, 0))
</script>

<template>
  <div>
    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
      <div class="bg-slate-50 px-5 py-3.5 border-b border-slate-200 flex justify-between items-center">
        <div>
          <div class="text-sm font-semibold">Inventory Snapshot</div>
          <div class="text-[11px] text-slate-400 mt-0.5">{{ monthLabel(y, m) }}</div>
        </div>
        <button class="btn primary" @click="doPrint">Print all ({{ rows.length }})</button>
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
                <th class="th">Supply Item</th>
                <th class="th">Fund Source</th>
                <th class="th">Lot</th><th class="th">Expiry</th>
                <th class="th text-right">Stock In</th><th class="th text-right">Stock Out</th><th class="th text-right">Current</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!visibleRows.length">
                <td colspan="7" class="empty">No items to show.</td>
              </tr>
              <tr v-for="r in visibleRows" :key="r.item.id" class="border-b border-slate-200">
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
        </div>
      </div>
    </div>

    <!-- PAGINATION (hidden when printing) -->
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
.btn.primary { height: 34px; padding: 0 14px; border-radius: 6px; font-size: 12px; font-weight: 500; background: #1e40af; color: #fff; border: none; cursor: pointer; }
.btn.primary:hover { background: #1d3a98; }
</style>