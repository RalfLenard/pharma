<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  show: { type: Boolean, default: false },
  item: { type: Object, default: null },
  currentStock: { type: Number, default: 0 }
})

const emit = defineEmits(['update:show'])

const form = ref({
  item_id: null,
  qty: 1,
  destination: '',
  remarks: '',
  date: '',
})

const customRemarks = ref('')

const remarkOptions = [
  'MHO',
  'Dental',
  'RHU II',
  'RHU III',
  'RHU IV',
  'RHU V',
  'Others'
]


const todayISO = () => {
  const d = new Date()
  const offset = d.getTimezoneOffset()
  const local = new Date(d.getTime() - offset * 60 * 1000)
  return local.toISOString().split('T')[0]
}

const close = () => {
  emit('update:show', false)
  setTimeout(() => {
    form.value = { item_id: null, qty: 1, destination: '', remarks: '', date: '' }
  }, 300)
}

const submitTransfer = () => {
  if (!props.item) return

  const finalDestination = form.value.destination?.trim()

  if (!finalDestination) {
    alert("Please enter a destination")
    return
  }

  if (!form.value.date) {
    alert("Please select a date")
    return
  }

  if (form.value.qty <= 0 || form.value.qty > props.currentStock) {
    alert("Invalid quantity")
    return
  }

  router.post('/pharmacy/transfers', {
    item_id: form.value.item_id,
    qty: form.value.qty,
    destination: finalDestination,
    remarks: form.value.remarks?.trim() || '',
    date: form.value.date,
  }, {
    preserveScroll: true,
    onSuccess: () => close(),
    onError: () => alert("Failed to process transfer.")
  })
}

watch(() => props.show, (isOpen) => {
  if (isOpen && props.item) {
    form.value.item_id = props.item.id
    form.value.qty = 1
    form.value.destination = ''
    form.value.remarks = ''
    form.value.date = todayISO()
  }
})
</script>

<template>
  <div v-if="show && item" class="modal-overlay" @click.self="close">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Transfer Item</h3>
        <button class="close-btn" @click="close">✕</button>
      </div>

      <div class="modal-body">
        <div class="item-info">
          <strong>{{ item.name }}</strong>
          <span class="stock">Current Stock: <b>{{ currentStock }}</b> {{ item.unit || 'pcs' }}</span>
        </div>

        <div class="form-grid">
          <div>
            <label>Date <span class="required">*</span></label>
            <input v-model="form.date" type="date" required />
          </div>

          <div>
            <label>Quantity <span class="required">*</span></label>
            <input v-model="form.qty" type="number" min="1" :max="currentStock" required />
          </div>
        </div>

        <div>
          <label>Destination <span class="required">*</span></label>
          <input
            v-model="form.destination"
            type="text"
            placeholder="Enter destination..."
          />
        </div>

         <div>
          <label>Remarks  <span class="required">*</span></label>
          <select v-model="form.remarks" class="select-input">
            <option value="" disabled>Select remarks...</option>
            <option v-for="option in remarkOptions" :key="option" :value="option">
              {{ option }}
            </option>
          </select>

          <!-- Custom input when "Others" is selected -->
          <input
            v-if="form.remarks === 'Others'"
            v-model="customRemarks"
            type="text"
            placeholder="Please specify remarks..."
            class="mt-2"
          />
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="close">Cancel</button>
        <button 
          class="btn btn-primary" 
          @click="submitTransfer"
          :disabled="form.qty > currentStock || form.qty <= 0 || !form.destination?.trim() || !form.date"
        >
          Confirm Transfer
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay { 
  position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
  background: rgba(0,0,0,0.6); z-index: 1000; 
  display: flex; align-items: center; justify-content: center; 
}
.modal-content { 
  background: white; border-radius: 10px; width: 100%; max-width: 520px; 
  box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
}
.modal-header { 
  padding: 16px 20px; border-bottom: 1px solid #e2e8f0; 
  display: flex; justify-content: space-between; align-items: center; 
}
.modal-header h3 { margin: 0; font-size: 18px; }
.close-btn { 
  font-size: 20px; background: none; border: none; cursor: pointer; color: #64748b; 
}

.modal-body { padding: 20px; }
.item-info { margin-bottom: 18px; padding: 12px; background: #f8fafc; border-radius: 8px; }
.stock { font-size: 13px; color: #64748b; display: block; margin-top: 4px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px; }
.required { color: #ef4444; }
.optional { color: #94a3b8; font-weight: 400; }

input, select { 
  width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; 
}
.mt-2 { margin-top: 8px; }

.modal-footer { 
  padding: 16px 20px; border-top: 1px solid #e2e8f0; 
  display: flex; gap: 10px; justify-content: flex-end; 
}
.btn { padding: 8px 16px; border-radius: 6px; font-weight: 500; cursor: pointer; }
.btn-secondary { background: #f1f5f9; border: 1px solid #cbd5e1; }
.btn-primary { background: #1e40af; color: white; border: none; }
.btn-primary:disabled { background: #94a3b8; cursor: not-allowed; }
</style>