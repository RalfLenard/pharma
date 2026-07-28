<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:show', 'printed'])

const dateFrom = ref('')
const dateTo = ref('')
const preparedBy = ref('')
const preparedByPosition = ref('')
const remarks = ref('')
const customRemarks = ref('')
const submitting = ref(false)

const remarkOptions = [
  'All',
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
  if (submitting.value) return
  emit('update:show', false)

  setTimeout(() => {
    dateFrom.value = ''
    dateTo.value = ''
    preparedBy.value = ''
    preparedByPosition.value = ''
    remarks.value = ''
    customRemarks.value = ''
  }, 300)
}

const submitPrint = async () => {
  if (
    !dateFrom.value ||
    !dateTo.value ||
    !preparedBy.value.trim() ||
    !preparedByPosition.value.trim() ||
    !remarks.value
  ) {
    alert('Please complete all required fields.')
    return
  }

  if (dateFrom.value > dateTo.value) {
    alert('Start date cannot be after end date.')
    return
  }

  if (remarks.value === 'Others' && !customRemarks.value.trim()) {
    alert('Please specify the destination.')
    return
  }

  const finalRemarks = remarks.value === 'Others'
    ? customRemarks.value.trim()
    : remarks.value

  const payload = {
    date_from: dateFrom.value,
    date_to: dateTo.value,
    prepared_by: preparedBy.value,
    prepared_by_position: preparedByPosition.value,
  }

  if (finalRemarks !== 'All') {
    payload.remarks = finalRemarks
  }

  submitting.value = true
  try {
    const { data } = await axios.post('/transfers/print', payload)

    window.open(`/print-history/${data.reference_id}/reprint`, '_blank')

    emit('printed', {
      reference_id: data.reference_id,
      date_from: dateFrom.value,
      date_to: dateTo.value,
      prepared_by: preparedBy.value,
      prepared_by_position: preparedByPosition.value,
      remarks: finalRemarks,
    })

    close()
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to generate the report. Please try again.')
  } finally {
    submitting.value = false
  }
}

watch(() => props.show, (isOpen) => {
  if (isOpen) {
    dateFrom.value = todayISO()
    dateTo.value = todayISO()
    preparedBy.value = ''
    preparedByPosition.value = ''
    remarks.value = ''
    customRemarks.value = ''
  }
})
</script>

<template>
  <div v-if="show" class="modal-overlay" @click.self="close">
    <div class="modal-content" @click.stop>

      <div class="modal-header">
        <h3>Print Transfers</h3>
        <button class="close-btn" @click="close" :disabled="submitting">✕</button>
      </div>

      <div class="modal-body">

        <p class="hint">
          Select the date range, destination, and enter the preparer's information.
        </p>

        <div class="form-grid">
          <div>
            <label>
              From
              <span class="required">*</span>
            </label>

            <input
              v-model="dateFrom"
              type="date"
              :max="dateTo || undefined"
            >
          </div>

          <div>
            <label>
              To
              <span class="required">*</span>
            </label>

            <input
              v-model="dateTo"
              type="date"
              :min="dateFrom || undefined"
            >
          </div>
        </div>

        <div class="prepared-by">
          <label>
            Remarks / Destination
            <span class="required">*</span>
          </label>

          <select v-model="remarks" class="select-input">
            <option value="" disabled>Select destination...</option>
            <option v-for="option in remarkOptions" :key="option" :value="option">
              {{ option }}
            </option>
          </select>

          <input
            v-if="remarks === 'Others'"
            v-model="customRemarks"
            type="text"
            placeholder="Please specify destination..."
            class="mt-2"
          >
        </div>

        <div class="prepared-by">
          <label>
            Prepared By
            <span class="required">*</span>
          </label>

          <input
            v-model="preparedBy"
            type="text"
            placeholder="Enter preparer's name"
          >
        </div>

        <div class="prepared-by">
          <label>
            Position
            <span class="required">*</span>
          </label>

          <input
            v-model="preparedByPosition"
            type="text"
            placeholder="Enter preparer's position"
          >
        </div>

      </div>

      <div class="modal-footer">

        <button
          class="btn btn-secondary"
          @click="close"
          :disabled="submitting"
        >
          Cancel
        </button>

        <button
          class="btn btn-primary"
          @click="submitPrint"
          :disabled="
            submitting ||
            !dateFrom ||
            !dateTo ||
            !preparedBy ||
            !preparedByPosition ||
            !remarks ||
            (remarks === 'Others' && !customRemarks.trim())
          "
        >
          {{ submitting ? 'Generating…' : 'Generate & Print' }}
        </button>

      </div>

    </div>
  </div>
</template>

<style scoped>
.modal-overlay{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.6);
  display:flex;
  justify-content:center;
  align-items:center;
  z-index:1000;
}

.modal-content{
  width:100%;
  max-width:450px;
  background:#fff;
  border-radius:10px;
  overflow:hidden;
  box-shadow:0 12px 30px rgba(0,0,0,.2);
}

.modal-header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:16px 20px;
  border-bottom:1px solid #e2e8f0;
}

.modal-header h3{
  margin:0;
  font-size:18px;
}

.close-btn{
  border:none;
  background:none;
  font-size:20px;
  cursor:pointer;
  color:#64748b;
}
.close-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.modal-body{
  padding:20px;
}

.hint{
  margin:0 0 18px;
  color:#64748b;
  font-size:13px;
}

.form-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}

.prepared-by{
  margin-top:18px;
}

label{
  display:block;
  margin-bottom:6px;
  font-size:12px;
  font-weight:600;
  color:#475569;
}

.required{
  color:#ef4444;
}

input, .select-input{
  width:100%;
  padding:10px;
  border:1px solid #cbd5e1;
  border-radius:6px;
  font-size:13px;
  outline:none;
  transition:.2s;
}

.select-input{
  height:42px;
  background:#fff;
}

.mt-2{
  margin-top:8px;
}

input:focus, .select-input:focus{
  border-color:#1e40af;
  box-shadow:0 0 0 3px rgba(30,64,175,.1);
}

.modal-footer{
  padding:16px 20px;
  display:flex;
  justify-content:flex-end;
  gap:10px;
  border-top:1px solid #e2e8f0;
}

.btn{
  padding:10px 18px;
  border-radius:6px;
  cursor:pointer;
  font-weight:600;
  border: none;
}

.btn-secondary{
  background:#f1f5f9;
  border:1px solid #cbd5e1;
}
.btn-secondary:disabled{ opacity: 0.6; cursor: not-allowed; }

.btn-primary{
  background:#1e40af;
  color:#fff;
  border:none;
}

.btn-primary:disabled{
  background:#94a3b8;
  cursor:not-allowed;
}
</style>