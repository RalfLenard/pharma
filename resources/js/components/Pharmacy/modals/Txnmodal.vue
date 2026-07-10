<script setup>
import { ref, watch, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { localDateStr } from '@/composables/Usepharmacycalc'

const props = defineProps({
  show: { type: Boolean, required: true },
  items: { type: Array, required: true },
  transactions: { type: Array, default: () => [] },
  itemId: { type: [Number, String, null], default: null },
  type: { type: String, default: 'in' },
})

const emit = defineEmits(['update:show'])

const activeTab = ref('existing')

const form = useForm({
  item_id: null,
  type: 'in',
  qty: 0,
  date: localDateStr(),
  by: '',
  note: '',

  new_lot_number: '',
  new_expiration_date: '',

  supply_item_name: '',
  volume_packaging: '',
  brand: '',
  category: '',
  quarter_delivered: '',
  lot_number: 'LOT2026-001',
  expiration_date: '',
  min_stock_level: 5,
})

const selectedItem = computed(() => {
  return props.items.find(i => Number(i.id) === Number(form.item_id)) || null
})

/* ── Stock Calculations (MATCHING MAIN PAGE) ── */
function itemStockIn(item) {
  if (!item) return 0
  const initialIn = Number(item.init_in) || 0
  const ledgerIn = props.transactions
    .filter(t => Number(t.item_id) === Number(item.id) && t.type === 'in')
    .reduce((sum, t) => sum + (Number(t.qty) || 0), 0)
  return initialIn + ledgerIn
}

function itemStockOut(item) {
  if (!item) return 0
  const initialOut = Number(item.init_out) || 0
  const ledgerOut = props.transactions
    .filter(t => Number(t.item_id) === Number(item.id) && t.type === 'out')
    .reduce((sum, t) => sum + (Number(t.qty) || 0), 0)
  return initialOut + ledgerOut
}

function itemStock(item) {
  return itemStockIn(item) - itemStockOut(item)
}

watch(() => props.show, (visible) => {
  if (!visible) return
  form.reset()
  activeTab.value = 'existing'
  form.item_id = props.itemId || (props.items[0]?.id ?? null)
  form.type = props.type
  form.date = localDateStr()
  form.qty = 0
}, { immediate: true })

function close() {
  emit('update:show', false)
  form.clearErrors()
}

function submit() {
  if (activeTab.value === 'existing' && !form.item_id) {
    alert('Please select an item.')
    return
  }
  if (activeTab.value === 'new' && !form.supply_item_name) {
    alert('Supply Item name is required.')
    return
  }

  const endpoint = route('pharmacy.transactions.store')
  form.transform((data) => ({
    ...data,
    is_new_item: activeTab.value === 'new'
  })).post(endpoint, {
    preserveScroll: true,
    onSuccess: close,
  })
}
</script>

<template>
  <div v-if="show" class="overlay" @click.self="close">
    <div class="modal">
      <div class="mhead">
        <h2>Record stock transaction</h2>
        <button class="mclose" @click="close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>

      <div class="mbody">
        <div class="tab-group">
          <button
            type="button"
            :class="['tab-btn', { active: activeTab === 'existing' }]"
            @click="activeTab = 'existing'"
          >
            Existing item
          </button>
          <button
            type="button"
            :class="['tab-btn', { active: activeTab === 'new' }]"
            @click="activeTab = 'new'"
          >
            + New item
          </button>
        </div>

        <!-- Existing Item -->
        <div v-if="activeTab === 'existing'">
          <div class="field mandatory">
            <label>Item *</label>
            <div class="select-wrapper">
              <select v-model.number="form.item_id">
                <option v-for="i in items" :key="i.id" :value="i.id">
                  {{ i.name || 'Unnamed Item' }}
                </option>
              </select>
            </div>
          </div>

          <div class="info-card-blue">
            <div class="card-title">UPDATE LOT / EXPIRY ON DELIVERY (OPTIONAL)</div>
            <div class="frow">
              <div class="field">
                <label>New lot number</label>
                <input v-model="form.new_lot_number" type="text" placeholder="Leave blank to keep existing" />
              </div>
              <div class="field">
                <label>New expiration date</label>
                <input v-model="form.new_expiration_date" type="date" />
              </div>
            </div>
            <p class="card-caption">Fill in if the delivered stock has a different lot or expiry.</p>
          </div>
        </div>

        <!-- New Item -->
        <div v-else-if="activeTab === 'new'">
          <div class="info-card-blue-simple">
            This will create a new item and record the transaction at the same time.
          </div>

          <div class="field mandatory">
            <label>Supply Item name *</label>
            <input v-model="form.supply_item_name" type="text" placeholder="e.g. Disposable Syringe 5mL" />
          </div>

          <div class="frow">
            <div class="field">
              <label>Volume / Packaging</label>
              <input v-model="form.volume_packaging" type="text" placeholder="500 mL..." />
            </div>
            <div class="field">
              <label>Brand</label>
              <input v-model="form.brand" type="text" placeholder="3M..." />
            </div>
          </div>

          <div class="frow">
            <div class="field mandatory">
              <label>Category *</label>
              <input v-model="form.category" type="text" placeholder="e.g. Wound Care" />
            </div>
            <div class="field">
              <label>Quarter delivered</label>
              <div class="select-wrapper">
                <select v-model="form.quarter_delivered">
                  <option value="">— Not specified —</option>
                  <option value="Q1">Q1</option>
                  <option value="Q2">Q2</option>
                  <option value="Q3">Q3</option>
                  <option value="Q4">Q4</option>
                </select>
              </div>
            </div>
          </div>

          <div class="frow">
            <div class="field">
              <label>Lot number</label>
              <input v-model="form.lot_number" type="text" />
            </div>
            <div class="field">
              <label>Expiration date</label>
              <input v-model="form.expiration_date" type="date" />
            </div>
          </div>

          <div class="frow dimensions-half">
            <div class="field">
              <label>Min. stock level</label>
              <input v-model.number="form.min_stock_level" type="number" />
            </div>
          </div>
        </div>

        <!-- Common Fields -->
        <div class="frow">
          <div class="field">
            <label>Type</label>
            <div class="select-wrapper">
              <select v-model="form.type">
                <option value="in">Stock in (received)</option>
                <option value="out">Stock out (used)</option>
                <option value="adj">Adjustment</option>
              </select>
            </div>
          </div>
          <div class="field mandatory">
            <label>Quantity *</label>
            <input v-model.number="form.qty" type="number" min="0" />
          </div>
        </div>

        <div class="frow">
          <div class="field">
            <label>Date</label>
            <input v-model="form.date" type="date" />
          </div>
          <div class="field">
            <label>Performed by</label>
            <input v-model="form.by" type="text" placeholder="Name / initials" />
          </div>
        </div>

        <div class="field m-bottom-sm">
          <label>Note</label>
          <input v-model="form.note" type="text" placeholder="Lot received, batch used..." />
        </div>

        <!-- Stock Summary (Fixed) -->
        <div v-if="activeTab === 'existing' && selectedItem" class="stock-summary-box">
          <div class="summary-title">TOTAL STOCK OVERVIEW</div>
          <div class="summary-row">
            <div class="summary-col">
              <span class="summary-label">Total In</span>
              <span class="summary-val text-green">
                {{ itemStockIn(selectedItem) }} {{ selectedItem.unit || 'pcs' }}
              </span>
            </div>
            <div class="summary-col">
              <span class="summary-label">Total Out</span>
              <span class="summary-val text-red">
                {{ itemStockOut(selectedItem) }} {{ selectedItem.unit || 'pcs' }}
              </span>
            </div>
            <div class="summary-col">
              <span class="summary-label">Current Balance</span>
              <span class="summary-val text-brown">
                {{ itemStock(selectedItem) }} {{ selectedItem.unit || 'pcs' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="mfoot">
        <button type="button" class="btn-cancel" @click="close">Cancel</button>
        <button type="button" class="btn-submit" :disabled="form.processing" @click="submit">
          Record transaction
        </button>
      </div>
    </div>
  </div>
</template>


<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  z-index: 1000;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 40px 16px;
  overflow-y: auto;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.modal {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  width: 580px;
  max-width: 100%;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}
.mhead {
  padding: 16px 24px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.mhead h2 {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}
.mclose {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  display: flex;
  align-items: center;
  padding: 0;
}
.mclose:hover {
  color: #64748b;
}
.mbody {
  padding: 24px 24px 16px 24px;
}
.tab-group {
  display: flex;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 8px;
  gap: 4px;
  margin-bottom: 18px;
  width: max-content;
}
.tab-btn {
  border: none;
  background: transparent;
  padding: 6px 14px;
  font-size: 12.5px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.15s ease;
}
.tab-btn.active {
  background: #ffffff;
  color: #1e293b;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.frow {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.dimensions-half {
  grid-template-columns: 1fr;
  width: calc(50% - 8px);
}
.field {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 14px;
}
.field.m-bottom-sm {
  margin-bottom: 8px;
}
.field label {
  font-size: 12px;
  font-weight: 500;
  color: #475569;
}
.field input, .field select {
  height: 36px;
  padding: 0 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13.5px;
  color: #334155;
  outline: none;
  box-sizing: border-box;
  width: 100%;
}
.field input::placeholder {
  color: #94a3b8;
}
.field input:focus, .field select:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 1px #3b82f6;
}
.select-wrapper {
  position: relative;
}
.select-wrapper select {
  appearance: none;
  padding-right: 32px;
  background: #ffffff;
}
.select-wrapper::after {
  content: "";
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 0;
  height: 0;
  border-left: 4.5px solid transparent;
  border-right: 4.5px solid transparent;
  border-top: 5px solid #64748b;
  pointer-events: none;
}
.info-card-blue {
  background: #edf4fe;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  padding: 14px 16px;
  margin-bottom: 14px;
}
.info-card-blue .card-title {
  font-size: 11px;
  font-weight: 700;
  color: #1d4ed8;
  letter-spacing: 0.03em;
  margin-bottom: 10px;
}
.info-card-blue .field {
  margin-bottom: 10px;
}
.info-card-blue .card-caption {
  font-size: 11px;
  color: #64748b;
  margin: 2px 0 0 0;
  line-height: 1.4;
}
.info-card-blue-simple {
  background: #edf4fe;
  border: 1px solid #bfdbfe;
  border-radius: 6px;
  padding: 10px 14px;
  font-size: 12.5px;
  color: #1d4ed8;
  margin-bottom: 14px;
}
.stock-summary-box {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 16px;
  background-color: #f8fafc;
  margin-top: 4px;
}
.summary-title {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  letter-spacing: 0.03em;
  margin-bottom: 10px;
}
.summary-row {
  display: flex;
  gap: 32px;
}
.summary-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.summary-label {
  font-size: 11px;
  color: #64748b;
  font-weight: 500;
}
.summary-val {
  font-size: 14px;
  font-weight: 700;
}
.text-green { color: #16a34a; }
.text-red { color: #dc2626; }
.text-brown { color: #b45309; }
.mfoot {
  padding: 16px 24px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
.btn-cancel {
  height: 36px;
  padding: 0 16px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #334155;
  font-size: 12.5px;
  font-weight: 500;
  border-radius: 6px;
  cursor: pointer;
}
.btn-cancel:hover {
  background: #f8fafc;
}
.btn-submit {
  height: 36px;
  padding: 0 16px;
  background: #1d4ed8;
  border: 1px solid #1d4ed8;
  color: #ffffff;
  font-size: 12.5px;
  font-weight: 500;
  border-radius: 6px;
  cursor: pointer;
}
.btn-submit:hover {
  background: #1e40af;
}
.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>