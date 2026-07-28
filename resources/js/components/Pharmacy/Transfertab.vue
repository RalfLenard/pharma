<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import PrintTransferModal from '@/components/Pharmacy/modals/Printtransfermodal.vue'
import EditTransferModal from '@/components/Pharmacy/modals/EditTransferModal.vue'

const props = defineProps({
  transfers: { type: Array, default: () => [] },
  items: { type: Array, required: true }
})

const emit = defineEmits(['edit', 'delete', 'refresh'])

const activeTab = ref('transfers') // 'transfers' | 'history'

const searchQuery = ref('')
const showPrintModal = ref(false)
const showEditModal = ref(false)
const editingTransfer = ref(null)

// Local copy of transfers to allow immediate UI updates
const localTransfers = ref([...props.transfers])

watch(() => props.transfers, (newVal) => {
  localTransfers.value = [...(newVal || [])]
}, { deep: true, immediate: true })

// --- Print History state ---
const printHistory = ref([])
const historyLoading = ref(false)
const historyError = ref('')
const historyPage = ref(1)
const historyLastPage = ref(1)
const reprintingId = ref(null)

async function loadPrintHistory(page = 1) {
  historyLoading.value = true
  historyError.value = ''
  try {
    const { data } = await axios.get('/print-history', { params: { page } })
    printHistory.value = data.data || []
    historyPage.value = data.current_page || 1
    historyLastPage.value = data.last_page || 1
  } catch (err) {
    historyError.value = err.response?.data?.message || 'Failed to load print history.'
  } finally {
    historyLoading.value = false
  }
}

function switchTab(tab) {
  activeTab.value = tab
  if (tab === 'history' && printHistory.value.length === 0) {
    loadPrintHistory(1)
  }
}

async function reprint(record) {
  reprintingId.value = record.id
  try {
    const response = await axios.get(`/print-history/${record.reference_id}/reprint`, {
      responseType: 'blob',
    })
    const blobUrl = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    window.open(blobUrl, '_blank')
    setTimeout(() => window.URL.revokeObjectURL(blobUrl), 60_000)
  } catch (err) {
    alert('Failed to reprint. The original data for this reference may no longer be available.')
  } finally {
    reprintingId.value = null
  }
}

function formatHistoryDate(date) {
  if (!date) return '—'
  const d = new Date(date)
  if (isNaN(d.getTime())) return String(date)
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) +
    ' • ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

function formatHistoryDateShort(date) {
  if (!date) return '—'
  const d = new Date(date)
  if (isNaN(d.getTime())) return String(date)
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

// --- Transfer table logic ---

function pick(obj, keys, fallback = '') {
  for (const k of keys) {
    if (obj && obj[k] !== undefined && obj[k] !== null && obj[k] !== '') return obj[k]
  }
  return fallback
}

const enrichedTransfers = computed(() => {
  return (localTransfers.value || []).map((t) => {
    const itemId = pick(t, ['item_id', 'itemId', 'item'], null)
    const item = props.items.find((i) => Number(i.id) === Number(itemId))
    return {
      raw: t,
      id: pick(t, ['id'], Math.random()),
      item_id: itemId,
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

function formatDate(date) {
  if (!date) return '—'
  const d = new Date(date)
  if (isNaN(d.getTime())) return String(date)
  return d.toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric'
  }) + ' • ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

function handlePrinted() {
  if (activeTab.value === 'history') {
    loadPrintHistory(historyPage.value)
  }
}

function onEdit(transfer) {
  editingTransfer.value = transfer.raw
  showEditModal.value = true
  emit('edit', transfer.raw)
}

function onUpdated(updatedRecord) {
  editingTransfer.value = null
  if (updatedRecord && updatedRecord.id) {
    const idx = localTransfers.value.findIndex(t => t.id === updatedRecord.id)
    if (idx !== -1) {
      localTransfers.value[idx] = { ...localTransfers.value[idx], ...updatedRecord }
    }
  }
  emit('refresh')
}

async function onDelete(transfer) {
  if (!confirm(`Delete transfer of ${transfer.qty} ${transfer.item_unit} — ${transfer.item_name}?`)) return

  try {
    await axios.delete(`/transfers/${transfer.id}`)
    localTransfers.value = localTransfers.value.filter(t => t.id !== transfer.id)
    emit('delete', transfer.raw)
    emit('refresh')
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete transfer.')
  }
}
</script>

<template>
  <div class="transfer-page">
    <div class="page-header">
      <div>
        <h2>Item Transfers</h2>
        <p class="subtitle">{{ enrichedTransfers.length }} total transfers</p>
      </div>
      <div class="header-actions">
        <div class="search-box" v-if="activeTab === 'transfers'">
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

    <!-- Tabs -->
    <div class="tabs">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'transfers' }"
        @click="switchTab('transfers')"
      >
        Transfers
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'history' }"
        @click="switchTab('history')"
      >
        Print History
      </button>
    </div>

    <!-- Transfers Tab -->
    <template v-if="activeTab === 'transfers'">
      <div v-if="!enrichedTransfers.length" class="empty-state">
        <p>No transfers recorded yet.</p>
        <small>Transfers made from the Transfer modal will appear here.</small>
      </div>

      <div v-else class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Item</th>
              <th class="num">Qty</th>
              <th>Remarks</th>
              <th class="actions-col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in filteredTransfers" :key="t.id" class="transfer-row">
              <td class="date">{{ formatDate(t.created_at) }}</td>
              <td class="item-name">{{ t.item_name }}</td>
              <td class="qty num">{{ t.qty }} <span class="unit">{{ t.item_unit }}</span></td>
              <td class="remarks">{{ t.remarks || '—' }}</td>
              <td class="actions-col">
                <button class="btn-action btn-edit" @click="onEdit(t)" title="Edit transfer">
                  ✏️ Edit
                </button>
                <button class="btn-action btn-delete" @click="onDelete(t)" title="Delete transfer">
                  🗑️
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Print History Tab -->
    <template v-else>
      <div v-if="historyLoading" class="empty-state">
        <p>Loading print history…</p>
      </div>

      <div v-else-if="historyError" class="empty-state">
        <p>{{ historyError }}</p>
      </div>

      <div v-else-if="!printHistory.length" class="empty-state">
        <p>No prints recorded yet.</p>
        <small>Reports generated from "Print Transfers" will appear here for reprinting.</small>
      </div>

      <div v-else class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Reference #</th>
              <th>Period</th>
              <th>Prepared By</th>
              <th>Printed At</th>
              <th class="actions-col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="record in printHistory" :key="record.id">
              <td class="ref"><strong>{{ record.reference_id }}</strong></td>
              <td class="date">
                {{ formatHistoryDateShort(record.date_from) }} — {{ formatHistoryDateShort(record.date_to) }}
              </td>
              <td>
                {{ record.prepared_by }}
                <span v-if="record.prepared_by_position" class="unit">({{ record.prepared_by_position }})</span>
              </td>
              <td class="date">{{ formatHistoryDate(record.printed_at) }}</td>
              <td class="actions-col">
                <button
                  class="btn-action btn-edit"
                  @click="reprint(record)"
                  :disabled="reprintingId === record.id"
                  title="Reprint this report"
                >
                  {{ reprintingId === record.id ? 'Preparing…' : '🖨️ Reprint' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="pagination" v-if="historyLastPage > 1">
          <button
            class="btn btn-cancel"
            :disabled="historyPage <= 1"
            @click="loadPrintHistory(historyPage - 1)"
          >
            Previous
          </button>
          <span class="page-info">Page {{ historyPage }} of {{ historyLastPage }}</span>
          <button
            class="btn btn-cancel"
            :disabled="historyPage >= historyLastPage"
            @click="loadPrintHistory(historyPage + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </template>

    <PrintTransferModal
      v-model:show="showPrintModal"
      @printed="handlePrinted"
    />

    <EditTransferModal
      v-model:show="showEditModal"
      :transfer="editingTransfer"
      :items="items"
      @updated="onUpdated"
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
  margin-bottom: 16px;
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

/* Tabs */
.tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 20px;
}

.tab-btn {
  padding: 10px 18px;
  border: none;
  background: transparent;
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all 0.15s;
}

.tab-btn:hover {
  color: #1e40af;
}

.tab-btn.active {
  color: #1e40af;
  border-bottom-color: #1e40af;
}

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

/* Actions column */
.actions-col {
  white-space: nowrap;
  text-align: right;
}

.btn-action {
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 13px;
  padding: 6px 10px;
  border-radius: 6px;
  margin-left: 4px;
  transition: background 0.15s;
}

.btn-action:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-edit {
  color: #1e40af;
}
.btn-edit:hover { background: #eff6ff; }

.btn-delete {
  color: #dc2626;
}
.btn-delete:hover { background: #fef2f2; }

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 16px;
  border-top: 1px solid #f1f5f9;
}

.page-info {
  font-size: 13px;
  color: #64748b;
}

.btn {
  padding: 8px 14px;
  border-radius: 8px;
  font-weight: 500;
  font-size: 13px;
  cursor: pointer;
  border: 1px solid #cbd5e1;
  background: white;
  color: #334155;
}
.btn:hover:not(:disabled) { background: #f1f5f9; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>