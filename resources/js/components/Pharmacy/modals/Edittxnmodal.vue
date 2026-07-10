<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  show: { type: Boolean, required: true },
  txn: { type: Object, default: null },
  itemName: { type: String, default: '' },
})

const emit = defineEmits(['update:show'])

const TYPE_LABEL = { in: 'Stock in', out: 'Stock out', adj: 'Adjustment' }
const TYPE_BADGE_CLASS = { in: 'b-in', out: 'b-out', adj: 'b-adj' }

const form = useForm({
  qty: 0,
  date: '',
  type: 'in',
  note: '',
})

watch(() => props.show, (visible) => {
  if (!visible || !props.txn) return
  form.reset()
  form.clearErrors()
  form.qty = props.txn.qty
  form.date = props.txn.date
  form.type = props.txn.type
  form.note = props.txn.note || ''
}, { immediate: true })

function close() {
  emit('update:show', false)
  form.clearErrors()
}

function submit() {
  if (!props.txn) return

  const qty = Number(form.qty)
  if (isNaN(qty) || qty < 0) {
    alert('Please enter a valid quantity (0 or more).')
    return
  }
  if (!form.date) {
    alert('Please select a date.')
    return
  }

  form.transform((data) => ({
    qty: Number(data.qty),
    date: data.date,
    type: data.type,
    note: data.note?.trim() || null,
  })).put(route('pharmacy.transactions.update', props.txn.id), {
    preserveScroll: true,
    onSuccess: close,
  })
}
</script>

<template>
  <div v-if="show" class="modal-overlay" @click.self="close">
    <div class="modal-container">
      <div class="modal-header">
        <h2 class="modal-title">Edit transaction</h2>
        <button class="modal-close" @click="close">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M13 1L1 13M1 1L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="context-card">
          <strong class="context-name">{{ itemName || '(unknown item)' }}</strong>
          <span class="badge" :class="TYPE_BADGE_CLASS[txn?.type]">{{ TYPE_LABEL[txn?.type] }}</span>
          <span class="context-date">{{ txn?.date }}</span>
        </div>

        <div class="form-row dynamic-cols">
          <div class="form-group">
            <label class="form-label">Quantity *</label>
            <input v-model.number="form.qty" type="number" min="0" class="form-input" placeholder="0" />
            <span class="field-hint">Replaces the current value.</span>
          </div>
          <div class="form-group">
            <label class="form-label">Date</label>
            <input v-model="form.date" type="date" class="form-input icon-right" />
          </div>
        </div>

        <div class="form-row dynamic-cols">
          <div class="form-group">
            <label class="form-label">Type</label>
            <div class="select-wrapper">
              <select v-model="form.type" class="form-select">
                <option value="in">Stock in</option>
                <option value="out">Stock out</option>
                <option value="adj">Adjustment</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Note</label>
            <input v-model="form.note" type="text" class="form-input" placeholder="Optional…" />
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-secondary" @click="close">Cancel</button>
        <button class="btn-primary" :disabled="form.processing" @click="submit">
          Save changes
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background-color: rgba(15, 23, 42, 0.45); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 24px; }
.modal-container { background: #ffffff; border-radius: 12px; width: 520px; max-width: 100%; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
.modal-header { padding: 12px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-family: system-ui, -apple-system, sans-serif; font-size: 16px; font-weight: 600; color: #1e293b; }
.modal-close { background: none; border: none; color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 4px; border-radius: 4px; }
.modal-close:hover { color: #475569; }
.modal-body { padding: 16px 20px; }

.context-card { background-color: #dbeafe; border-radius: 6px; padding: 10px 12px; margin-bottom: 14px; font-size: 12px; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; font-family: system-ui, -apple-system, sans-serif; }
.context-name { color: #1e40af; }
.context-date { font-size: 11px; color: #64748b; }

.badge { display: inline-block; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; text-transform: capitalize; }
.b-in { background: #dcfce7; color: #166534; }
.b-out { background: #fee2e2; color: #991b1b; }
.b-adj { background: #f3e8ff; color: #6b21a8; }

.form-row { margin-bottom: 10px; }
.form-row.dynamic-cols { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-family: system-ui, -apple-system, sans-serif; font-size: 13px; font-weight: 500; color: #475569; }
.form-input, .form-select { height: 34px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; background-color: #ffffff; color: #334155; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; width: 100%; box-sizing: border-box; outline: none; }
.form-input::placeholder { color: #94a3b8; }
.form-input:focus, .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
.field-hint { font-family: system-ui, -apple-system, sans-serif; font-size: 11px; color: #94a3b8; }

.modal-footer { padding: 12px 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; background-color: #ffffff; }
.btn-secondary { height: 34px; padding: 0 14px; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #ffffff; color: #334155; cursor: pointer; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; font-weight: 500; }
.btn-secondary:hover { background-color: #f8fafc; }
.btn-primary { height: 34px; padding: 0 14px; background-color: #1e3a8a; color: #ffffff; border: 1px solid #1e3a8a; border-radius: 6px; cursor: pointer; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; font-weight: 500; }
.btn-primary:hover { background-color: #172554; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
</style>