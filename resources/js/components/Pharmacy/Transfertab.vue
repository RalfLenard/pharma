<script setup>
import { ref, computed, nextTick } from 'vue'
import PrintTransferModal from '@/components/Pharmacy/modals/Printtransfermodal.vue'

const props = defineProps({
  transfers: { type: Array, default: () => [] },
  items: { type: Array, required: true }
})

const searchQuery = ref('')
const showPrintModal = ref(false)

const printInfo = ref({
  reference_id: '',
  date_from: '',
  date_to: '',
})

function pick(obj, keys, fallback = '') {
  for (const k of keys) {
    if (obj[k] !== undefined && obj[k] !== null && obj[k] !== '') return obj[k]
  }
  return fallback
}

const enrichedTransfers = computed(() => {
  return (props.transfers || []).map((t) => {
    const itemId = pick(t, ['item_id', 'itemId', 'item'], null)
    const item = props.items.find((i) => Number(i.id) === Number(itemId))
    return {
      raw: t,
      id: pick(t, ['id'], Math.random()),
      created_at: pick(t, ['created_at', 'createdAt', 'date', 'transfer_date'], null),
      reference_id: pick(t, ['reference_id', 'ref_id', 'reference', 'ref'], '—'),
      qty: Number(pick(t, ['qty', 'quantity', 'amount'], 0)),
      from_location: pick(t, ['from_location', 'from', 'source'], 'Main Pharmacy'),
      to_location: pick(t, ['to_location', 'to', 'destination'], ''),
      remarks: pick(t, ['remarks', 'note', 'notes', 'reason'], ''),
      item_name: item ? item.name : pick(t, ['item_name'], 'Unknown Item'),
      item_unit: item ? (item.unit || 'pcs') : 'pcs',
    }
  }).sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))
})

const filteredTransfers = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  if (!q) return enrichedTransfers.value
  return enrichedTransfers.value.filter((t) =>
    t.item_name.toLowerCase().includes(q) ||
    t.remarks.toLowerCase().includes(q) ||
    t.reference_id.toLowerCase().includes(q) ||
    t.to_location.toLowerCase().includes(q)
  )
})

// Transfers within the selected print date range
const printTransfers = computed(() => {
  if (!printInfo.value.date_from || !printInfo.value.date_to) return []
  const from = new Date(printInfo.value.date_from)
  const to = new Date(printInfo.value.date_to)
  to.setHours(23, 59, 59, 999)

  return enrichedTransfers.value.filter((t) => {
    if (!t.created_at) return false
    const d = new Date(t.created_at)
    return d >= from && d <= to
  })
})

const printTotalQty = computed(() =>
  printTransfers.value.reduce((sum, t) => sum + (t.qty || 0), 0)
)

function formatDate(date) {
  if (!date) return '—'
  const d = new Date(date)
  if (isNaN(d.getTime())) return String(date)
  return d.toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric'
  }) + ' • ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

function formatDateShort(date) {
  if (!date) return '—'
  const d = new Date(date)
  if (isNaN(d.getTime())) return String(date)
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

async function handlePrinted(payload) {
  printInfo.value = payload

  // Wait for the printable view to render with the new data, then open print dialog
  await nextTick()
  window.print()
}
</script>

<template>
  <div class="transfer-page">
    <div class="page-header no-print">
      <div>
        <h2>Item Transfers</h2>
        <p class="subtitle">{{ enrichedTransfers.length }} total transfers</p>
      </div>
      <div class="header-actions">
        <div class="search-box">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Search by item, reference, or remarks..." 
            class="search-input"
          />
        </div>
        <button class="btn btn-print" @click="showPrintModal = true">
          🖨️ Print Transfers
        </button>
      </div>
    </div>

    <div v-if="!props.transfers.length" class="empty-state no-print">
      <p>No transfers recorded yet.</p>
      <small>Transfers made from the Transfer modal will appear here.</small>
    </div>

    <div v-else class="tbl-wrap no-print">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Reference</th>
            <th>Item</th>
            <th class="num">Qty</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in filteredTransfers" :key="t.id" class="transfer-row">
            <td class="date">{{ formatDate(t.created_at) }}</td>
            <td class="ref"><strong>{{ t.reference_id }}</strong></td>
            <td class="item-name">{{ t.item_name }}</td>
            <td class="qty num">{{ t.qty }} <span class="unit">{{ t.item_unit }}</span></td>
            <td class="remarks">{{ t.remarks || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Printable summary, hidden on screen, shown only when printing -->
    <div class="print-only">
      <div class="print-header">
        <h1>Item Transfer Report</h1>
        <p v-if="printInfo.reference_id">Reference: <strong>{{ printInfo.reference_id }}</strong></p>
        <p>Period: {{ formatDateShort(printInfo.date_from) }} — {{ formatDateShort(printInfo.date_to) }}</p>
      </div>

      <table class="print-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Reference</th>
            <th>Item</th>
            <th>Qty</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in printTransfers" :key="'print-' + t.id">
            <td>{{ formatDate(t.created_at) }}</td>
            <td>{{ t.reference_id }}</td>
            <td>{{ t.item_name }}</td>
            <td>{{ t.qty }} {{ t.item_unit }}</td>
            <td>{{ t.remarks || '—' }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3"><strong>Total</strong></td>
            <td><strong>{{ printTotalQty }}</strong></td>
            <td></td>
          </tr>
        </tfoot>
      </table>

      <p v-if="!printTransfers.length" class="print-empty">No transfers found in this date range.</p>
    </div>

    <PrintTransferModal
      v-model:show="showPrintModal"
      @printed="handlePrinted"
    />
  </div>
</template>

<style scoped>
.transfer-page {
  padding: 20px 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}

.page-header h2 {
  margin: 0 0 4px 0;
  font-size: 20px;
  font-weight: 600;
  color: #0f172a;
}

.subtitle {
  margin: 0;
  color: #64748b;
  font-size: 13px;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.search-box input {
  padding: 10px 14px;
  width: 340px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.2s;
}

.search-box input:focus {
  border-color: #1e40af;
  box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
  outline: none;
}

.btn-print {
  padding: 10px 16px;
  border-radius: 8px;
  font-weight: 500;
  font-size: 14px;
  cursor: pointer;
  background: #1e40af;
  color: white;
  border: none;
  white-space: nowrap;
}
.btn-print:hover { background: #1e3a8a; }

/* Empty State */
.empty-state {
  text-align: center;
  padding: 80px 20px;
  color: #64748b;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px dashed #cbd5e1;
}

/* Table */
.tbl-wrap {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.tbl-wrap table {
  width: 100%;
  border-collapse: collapse;
}

.tbl-wrap th {
  background: #f8fafc;
  padding: 12px 16px;
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid #e2e8f0;
}

.tbl-wrap td {
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.tbl-wrap tr:last-child td {
  border-bottom: none;
}

.tbl-wrap tr:hover td {
  background: #f8fafc;
}

/* Column Styles */
.date { white-space: nowrap; color: #64748b; font-size: 13px; }
.ref strong { font-family: ui-monospace, monospace; font-size: 13px; }
.item-name { font-weight: 500; }
.qty { font-weight: 600; color: #1e40af; }
.unit { font-size: 12px; color: #94a3b8; font-weight: normal; }

.remarks {
  color: #64748b;
  font-size: 13px;
  max-width: 280px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Print view is hidden on screen, only shown when printing */
.print-only { display: none; }
</style>

<style>
/* Global print rules (unscoped so they reliably apply to the whole page) */
@media print {
  .no-print { display: none !important; }
  .print-only { display: block !important; }

  .print-header {
    text-align: center;
    margin-bottom: 20px;
  }
  .print-header h1 {
    margin: 0 0 6px 0;
    font-size: 18px;
  }
  .print-header p {
    margin: 2px 0;
    font-size: 12px;
  }

  .print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }
  .print-table th,
  .print-table td {
    border: 1px solid #333;
    padding: 6px 8px;
    text-align: left;
  }
  .print-table thead {
    background: #eee;
  }
  .print-empty {
    text-align: center;
    color: #666;
    margin-top: 20px;
  }
}
</style>