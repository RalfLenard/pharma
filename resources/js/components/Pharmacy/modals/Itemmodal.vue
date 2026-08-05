<script setup>
import { watch, computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { localDateStr, FUND_SOURCES } from '@/composables/Usepharmacycalc'

const props = defineProps({
  show: { type: Boolean, required: true },
  mode: { type: String, default: 'add' }, // 'add' | 'edit'
  item: { type: Object, default: null },
})

const emit = defineEmits(['update:show'])

// Local tracking string for the HTML date picker calendar element
const quarterDeliveredDate = ref(localDateStr())

const form = useForm({
  name: '', vol: '', brand: '', sec: '', lot: '', exp: '', min: 5,
  fund: '', unit: '', added_date: localDateStr(), by: '',
  init_in: 0, init_out: 0, add_in: 0, add_out: 0, order_qty: 0,
  quarter_delivered: '', // This holds the payload string (e.g., "Q3 2026") sent to the database
})

// Completely automated calendar lookup method that extracts both the Quarter and the Calendar Year
function getQuarterFromDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return ''

  const year = date.getFullYear() // Extracts the selected year (e.g., 2026)
  const month = date.getMonth() + 1 // January = 1, July = 7, etc.

  let qString = 'Q4'
  if (month >= 1 && month <= 3) qString = 'Q1'
  else if (month >= 4 && month <= 6) qString = 'Q2'
  else if (month >= 7 && month <= 9) qString = 'Q3'

  return `${qString} ${year}` // Returns formatted string: "Q3 2026", "Q1 2027", etc.
}

// Computed for clean v-model binding
const stockIn = computed({
  get: () => props.mode === 'edit' ? form.add_in : form.init_in,
  set: (val) => {
    if (props.mode === 'edit') form.add_in = val
    else form.init_in = val
  }
})

const stockOut = computed({
  get: () => props.mode === 'edit' ? form.add_out : form.init_out,
  set: (val) => {
    if (props.mode === 'edit') form.add_out = val
    else form.init_out = val
  }
})

// Stock already on hand for this item (0 for a brand new item being added)
const availableStock = computed(() => {
  if (props.mode === 'edit' && props.item) {
    return Number(props.item.stock ?? 0)
  }
  return 0
})

// Dynamic current stock preview calculation — now factors in existing stock
// so the preview reflects the REAL resulting stock after this transaction.
const currentStockPreview = computed(() => {
  const incoming = Number(stockIn.value) || 0
  const outgoing = Number(stockOut.value) || 0
  return availableStock.value + incoming - outgoing
})

// Client-side validation mirroring the backend rules:
// 1. Can't stock out when there is no stock at all.
// 2. Can't stock out more than what's available.
const stockError = computed(() => {
  const outgoing = Number(stockOut.value) || 0
  if (outgoing <= 0) return ''

  if (props.mode === 'edit') {
    if (availableStock.value <= 0) {
      return 'You cannot stock out an item that has no stock.'
    }
    if (outgoing > availableStock.value) {
      return `Only ${availableStock.value} item(s) are available in stock.`
    }
  } else {
    const incoming = Number(stockIn.value) || 0
    if (incoming === 0) {
      return 'You cannot stock out an item that has no stock.'
    }
    if (outgoing > incoming) {
      return 'Stock out cannot be greater than the stock in.'
    }
  }
  return ''
})

// WATCHER: Automatically convert the date picker choice into the code string
watch(quarterDeliveredDate, (newDate) => {
  form.quarter_delivered = getQuarterFromDate(newDate)
}, { immediate: true })

// Link the default Entry Date change to the calendar picker if adding a new item
watch(() => form.added_date, (newDate) => {
  if (props.mode !== 'edit') {
    quarterDeliveredDate.value = newDate
  }
})

watch(() => props.show, (visible) => {
  if (!visible) return

  if (props.mode === 'edit' && props.item) {
    form.reset()
    form.name = props.item.name || ''
    form.vol = props.item.vol || ''
    form.brand = props.item.brand || ''
    form.sec = props.item.sec || ''
    form.lot = props.item.lot || ''
    form.exp = props.item.exp || ''
    form.min = props.item.min || 0
    form.order_qty = props.item.order_qty || 0
    form.fund = props.item.fund || ''
    form.unit = props.item.unit || ''

    // Assign the saved quarter value from DB
    form.quarter_delivered = props.item.quarter_delivered || getQuarterFromDate(localDateStr())

    // Attempt to parse out the year from the database string to synchronize the calendar field display
    if (props.item.quarter_delivered && props.item.quarter_delivered.includes(' ')) {
      const parts = props.item.quarter_delivered.split(' ')
      const savedYear = parts[1]
      quarterDeliveredDate.value = `${savedYear}-07-01` // Sets the picker focus window to the stored year
    } else {
      quarterDeliveredDate.value = localDateStr()
    }

    form.add_in = 0
    form.add_out = 0
  } else {
    form.reset()
    form.added_date = localDateStr()
    quarterDeliveredDate.value = localDateStr()
    form.min = 5
    form.init_in = 0
    form.init_out = 0
  }
}, { immediate: true })

function close() {
  emit('update:show', false)
  form.clearErrors()
}

function submit() {
  if (!form.name?.trim()) {
    alert('Supply Item name is required.')
    return
  }

  if (stockError.value) {
    // Guard against submitting an invalid stock-out value.
    return
  }

  if (props.mode === 'edit' && props.item) {
    form.transform((data) => ({
      name: data.name,
      vol: data.vol,
      brand: data.brand,
      sec: data.sec,
      lot: data.lot,
      exp: data.exp || null,
      min: data.min,
      order_qty: data.order_qty,
      fund: data.fund,
      unit: data.unit,
      add_in: data.add_in,
      add_out: data.add_out,
      quarter_delivered: data.quarter_delivered,
    })).put(route('pharmacy.items.update', props.item.id), {
      preserveScroll: true,
      onSuccess: close,
    })
  } else {
    form.transform((data) => ({
      name: data.name,
      vol: data.vol,
      brand: data.brand,
      sec: data.sec,
      lot: data.lot,
      exp: data.exp || null,
      min: data.min,
      fund: data.fund,
      unit: data.unit,
      added_date: data.added_date,
      by: data.by,
      init_in: data.init_in,
      init_out: data.init_out,
      quarter_delivered: data.quarter_delivered,
    })).post(route('pharmacy.items.store'), {
      preserveScroll: true,
      onSuccess: close,
    })
  }
}
</script>

<template>
  <div v-if="show" class="modal-overlay" @click.self="close">
    <div class="modal-container">
      <div class="modal-header">
        <h2 class="modal-title">{{ mode === 'edit' ? 'Edit item' : 'Add new item' }}</h2>
        <button class="modal-close" @click="close">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M13 1L1 13M1 1L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-row full-width">
          <div class="form-group">
            <label class="form-label">Supply Item name *</label>
            <input v-model="form.name" type="text" class="form-input" placeholder="e.g. Disposable Syringe 5mL" />
          </div>
        </div>

        <div class="form-row dynamic-cols">
          <div class="form-group">
            <label class="form-label">Volume / Packaging</label>
            <input v-model="form.vol" type="text" class="form-input" placeholder="500 mL, 100 strips..." />
          </div>
          <div class="form-group">
            <label class="form-label">Brand</label>
            <input v-model="form.brand" type="text" class="form-input" placeholder="3M, Medline..." />
          </div>
        </div>

        <div class="form-row dynamic-cols">
          <div class="form-group">
            <label class="form-label">Category</label>
            <input v-model="form.sec" type="text" class="form-input" placeholder="e.g. Wound Care" />
          </div>
          <div class="form-group">
            <label class="form-label">Lot number</label>
            <input v-model="form.lot" type="text" class="form-input" placeholder="LOT2024-0812" />
          </div>
        </div>

        <div class="form-row dynamic-cols">
          <div class="form-group relative">
            <label class="form-label">Expiration date</label>
            <input v-model="form.exp" type="date" class="form-input icon-right" />
          </div>
          <div class="form-group">
            <label class="form-label">Unit *</label>
            <input v-model="form.unit" type="text" class="form-input" placeholder="bottles, kits, boxes..." />
          </div>
        </div>

        <div class="form-row dynamic-cols">
          <div class="form-group">
            <label class="form-label">Min. stock level</label>
            <input v-model.number="form.min" type="number" min="0" class="form-input" placeholder="5" />
          </div>
          <div class="form-group">
            <label class="form-label">Fund Source</label>
            <div class="select-wrapper">
              <select v-model="form.fund" class="form-select">
                <option value="">— select —</option>
                <option v-for="f in FUND_SOURCES" :key="f" :value="f">{{ f }}</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-row full-width">
          <div class="form-group">
            <label class="form-label">
              Quarter Delivered Date (Calculates: <strong style="color: #2563eb;">{{ form.quarter_delivered || 'None' }}</strong>)
            </label>
            <input
              v-model="quarterDeliveredDate"
              type="date"
              class="form-input icon-right"
            />
          </div>
        </div>

        <hr class="section-divider" />
        <div class="section-title">
          {{ mode === 'edit' ? 'Add stock transaction (optional)' : 'Initial stock entry' }}
          <span v-if="mode === 'edit'" class="available-stock-tag">
            Currently available: {{ availableStock }}
          </span>
        </div>

        <div class="form-row dynamic-cols">
          <div class="form-group">
            <label class="form-label">{{ mode === 'edit' ? 'Additional stock in' : 'Stock in (received)' }}</label>
            <input v-model.number="stockIn" type="number" min="0" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">{{ mode === 'edit' ? 'Additional stock out' : 'Stock out (used)' }}</label>
            <input
              v-model.number="stockOut"
              type="number"
              min="0"
              class="form-input"
              :class="{ 'input-error': stockError }"
            />
            <span v-if="stockError" class="field-error">{{ stockError }}</span>
          </div>
        </div>

        <div class="form-row dynamic-cols">
          <div v-if="mode !== 'edit'" class="form-group">
            <label class="form-label">Date of entry</label>
            <input v-model="form.added_date" type="date" class="form-input icon-right" />
          </div>
          <div v-else class="form-group">
            <label class="form-label">Quantity on order</label>
            <input v-model.number="form.order_qty" type="number" min="0" class="form-input" />
          </div>
          <div class="form-group" v-if="mode !== 'edit'">
            <label class="form-label">Performed by</label>
            <input v-model="form.by" type="text" class="form-input" placeholder="Name / initials" />
          </div>
        </div>

        <div class="preview-card">
          <div class="preview-title">Current Stock Preview</div>
          <div class="preview-equation">
            <div class="eq-group" v-if="mode === 'edit'">
              <span class="eq-label">On hand</span>
              <span class="eq-value">{{ availableStock }}</span>
            </div>
            <div class="eq-operator" v-if="mode === 'edit'">+</div>
            <div class="eq-group">
              <span class="eq-label">Stock in</span>
              <span class="eq-value">{{ stockIn || 0 }}</span>
            </div>
            <div class="eq-operator">—</div>
            <div class="eq-group">
              <span class="eq-label">Stock out</span>
              <span class="eq-value">{{ stockOut || 0 }}</span>
            </div>
            <div class="eq-operator">=</div>
            <div class="eq-group text-primary">
              <span class="eq-label">Current stock</span>
              <span class="eq-value highlighted">{{ currentStockPreview }}</span>
            </div>
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn-secondary" @click="close">Cancel</button>
        <button class="btn-primary" :disabled="form.processing || !!stockError" @click="submit">
          {{ mode === 'edit' ? 'Save changes' : 'Save item' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background-color: rgba(15, 23, 42, 0.45); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 24px; }
.modal-container { background: #ffffff; border-radius: 12px; width: 580px; max-width: 100%; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
.modal-header { padding: 12px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-family: system-ui, -apple-system, sans-serif; font-size: 16px; font-weight: 600; color: #1e293b; }
.modal-close { background: none; border: none; color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 4px; border-radius: 4px; }
.modal-close:hover { color: #475569; }
.modal-body { padding: 16px 20px; }
.form-row { margin-bottom: 10px; }
.form-row.dynamic-cols { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-family: system-ui, -apple-system, sans-serif; font-size: 13px; font-weight: 500; color: #475569; }
.form-input, .form-select { height: 34px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; background-color: #ffffff; color: #334155; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; width: 100%; box-sizing: border-box; outline: none; }
.form-input::placeholder { color: #94a3b8; }
.form-input:focus, .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
.form-input:disabled { border-color: #e2e8f0; }
.form-input.input-error { border-color: #dc2626; }
.form-input.input-error:focus { border-color: #dc2626; box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1); }
.field-error { font-family: system-ui, -apple-system, sans-serif; font-size: 12px; color: #dc2626; margin-top: 2px; }
.section-divider { border: none; border-top: 1px solid #e2e8f0; margin: 14px 0 10px 0; }
.section-title { font-family: system-ui, -apple-system, sans-serif; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
.available-stock-tag { font-size: 11px; font-weight: 600; color: #1e40af; text-transform: none; letter-spacing: normal; }
.preview-card { background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 16px; margin-top: 12px; }
.preview-title { font-family: system-ui, -apple-system, sans-serif; font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
.preview-equation { display: flex; align-items: center; gap: 12px; font-family: system-ui, -apple-system, sans-serif; flex-wrap: wrap; }
.eq-group { display: flex; flex-direction: column; gap: 2px; }
.eq-label { font-size: 11px; color: #2563eb; font-weight: 500; }
.eq-value { font-size: 18px; font-weight: 700; color: #1e40af; line-height: 1.2; }
.eq-value.highlighted { font-size: 20px; }
.eq-operator { font-size: 18px; font-weight: 500; color: #64748b; padding-top: 10px; }
.modal-footer { padding: 12px 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; background-color: #ffffff; }
.btn-secondary { height: 34px; padding: 0 14px; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #ffffff; color: #334155; cursor: pointer; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; font-weight: 500; }
.btn-secondary:hover { background-color: #f8fafc; }
.btn-primary { height: 34px; padding: 0 14px; background-color: #1e3a8a; color: #ffffff; border: 1px solid #1e3a8a; border-radius: 6px; cursor: pointer; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; font-weight: 500; }
.btn-primary:hover { background-color: #172554; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
</style>