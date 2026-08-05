<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  show: { type: Boolean, default: false },
  transfer: { type: Object, default: null },
  items: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:show', 'updated'])

const form = ref({
  id: null,
  item_id: '',
  qty: 1,
  destination: '',
  remarks: '',
  date: '',
})

const remarkOptions = [
  'MHO',
  'Dental',
  'RHU II',
  'RHU III',
  'RHU IV',
  'RHU V',
  'Others'
]
const customRemarks = ref('')
const submitting = ref(false)
const errors = ref({})

// Populate the form whenever a new transfer is passed in to edit
watch(() => props.transfer, (t) => {
  if (!t) return

  form.value.id = t.id
  form.value.item_id = t.item_id ?? t.item?.id ?? ''
  form.value.qty = t.qty ?? t.quantity ?? 1
  form.value.destination = t.destination ?? ''
  form.value.date = normalizeDate(t.date ?? t.created_at ?? '')

  // If the stored remark isn't one of the presets, treat it as a custom
  // "Others" value — select "Others" and preload the free-text field,
  // otherwise the <select> would show blank and the custom text would
  // be lost on save.
  const storedRemark = t.remarks ?? ''
  if (storedRemark && !remarkOptions.includes(storedRemark)) {
    form.value.remarks = 'Others'
    customRemarks.value = storedRemark
  } else {
    form.value.remarks = storedRemark
    customRemarks.value = ''
  }

  errors.value = {}
}, { immediate: true })

// Avoid UTC conversion shifting the date by a day — parse the date-ish
// string manually instead of going through Date/toISOString.
function normalizeDate(value) {
  if (!value) return ''
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (match) return `${match[1]}-${match[2]}-${match[3]}`

  const d = new Date(value)
  if (isNaN(d.getTime())) return ''
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const selectedItem = computed(() =>
  props.items.find((i) => Number(i.id) === Number(form.value.item_id))
)

// The actual remarks value to submit — resolves "Others" to the
// free-text the user typed in customRemarks.
const resolvedRemarks = computed(() => {
  if (form.value.remarks === 'Others') {
    return customRemarks.value?.trim() || ''
  }
  return form.value.remarks?.trim() || ''
})

// Save button now also requires remarks (and, if "Others" is chosen,
// requires the custom text to be filled in).
const canSubmit = computed(() => {
  if (submitting.value) return false
  if (!form.value.destination?.trim()) return false
  if (!form.value.date) return false
  if (!form.value.qty || form.value.qty <= 0) return false
  if (!form.value.remarks) return false
  if (form.value.remarks === 'Others' && !customRemarks.value?.trim()) return false
  return true
})

function close() {
  if (submitting.value) return
  errors.value = {}
  emit('update:show', false)
}

async function submit() {
  if (!form.value.id) return
  submitting.value = true
  errors.value = {}

  const finalDestination = form.value.destination?.trim()
  const finalRemarks = resolvedRemarks.value

  try {
    const { data } = await axios.post(`/transfers/${form.value.id}`, {
      item_id: form.value.item_id,
      qty: form.value.qty,
      destination: finalDestination,
      remarks: finalRemarks,
      date: form.value.date,
    })

    const updatedRecord = {
      ...props.transfer,
      ...(data.transfer || data),
      item_id: form.value.item_id,
      qty: form.value.qty,
      destination: finalDestination,
      remarks: finalRemarks,
      date: form.value.date,
      item: selectedItem.value || props.transfer?.item
    }

    emit('updated', updatedRecord)
    emit('update:show', false)
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
    } else {
      errors.value = { qty: [err.response?.data?.message || 'Something went wrong. Please try again.'] }
    }
  } finally {
    submitting.value = false
  }
}

function firstError(field) {
  return errors.value[field]?.[0] || null
}
</script>

<template>
  <teleport to="body">
    <div v-if="show" class="modal-overlay" @click.self="close">
      <div class="modal-box">
        <div class="modal-header">
          <h3>Edit Transfer</h3>
          <button class="modal-close" @click="close" :disabled="submitting">✕</button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>Item</label>
            <select v-model="form.item_id" class="form-input">
              <option value="" disabled>Select item</option>
              <option v-for="i in items" :key="i.id" :value="i.id">
                {{ i.name }}
              </option>
            </select>
            <small v-if="firstError('item_id')" class="field-error">{{ firstError('item_id') }}</small>
          </div>

          <div class="form-group">
            <label>Quantity <span v-if="selectedItem">({{ selectedItem.unit || 'pcs' }})</span></label>
            <input v-model.number="form.qty" type="number" min="1" class="form-input" />
            <small v-if="firstError('qty')" class="field-error">{{ firstError('qty') }}</small>
          </div>

          <div class="form-group">
            <label>Date</label>
            <input v-model="form.date" type="date" class="form-input" />
            <small v-if="firstError('date')" class="field-error">{{ firstError('date') }}</small>
          </div>

          <div class="form-group">
            <label>Destination <span class="required">*</span></label>
            <input
              v-model="form.destination"
              type="text"
              placeholder="Enter destination..."
              class="form-input"
            />
            <small v-if="firstError('destination')" class="field-error">{{ firstError('destination') }}</small>
          </div>

          <div class="form-group">
            <label>Remarks / Destination <span class="required">*</span></label>
            <select v-model="form.remarks" class="form-input">
              <option value="" disabled>Select destination...</option>
              <option v-for="option in remarkOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>

            <!-- Custom input when "Others" is selected -->
            <input
              v-if="form.remarks === 'Others'"
              v-model="customRemarks"
              type="text"
              placeholder="Please specify destination..."
              class="form-input mt-2"
            />

            <small v-if="firstError('remarks')" class="field-error">{{ firstError('remarks') }}</small>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-cancel" @click="close" :disabled="submitting">Cancel</button>
          <button
            class="btn btn-save"
            @click="submit"
            :disabled="!canSubmit"
          >
            {{ submitting ? 'Saving…' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-box {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 20px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
}

.modal-close {
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 14px;
  color: #64748b;
  padding: 4px 8px;
  border-radius: 6px;
}
.modal-close:hover { background: #f1f5f9; }

.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  font-weight: 500;
  color: #334155;
}

.required {
  color: #dc2626;
}

.optional {
  color: #94a3b8;
  font-weight: 400;
}

.form-input {
  padding: 9px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  width: 100%;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: #1e40af;
  box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
  outline: none;
}

.mt-2 {
  margin-top: 8px;
}

.field-error {
  color: #dc2626;
  font-size: 12px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.btn {
  padding: 9px 16px;
  border-radius: 8px;
  font-weight: 500;
  font-size: 14px;
  cursor: pointer;
  border: none;
}

.btn-cancel {
  background: white;
  border: 1px solid #cbd5e1;
  color: #334155;
}
.btn-cancel:hover { background: #f1f5f9; }

.btn-save {
  background: #1e40af;
  color: white;
}
.btn-save:hover { background: #1e3a8a; }
.btn-save:disabled,
.btn-cancel:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>