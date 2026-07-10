<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { localDateStr } from '@/composables/Usepharmacycalc'

const props = defineProps({
  show: { type: Boolean, required: true },
  item: { type: Object, default: null },
})

const emit = defineEmits(['update:show'])

const form = useForm({
  archived_date: '',
  archive_reason: '',
})

watch(() => props.show, (visible) => {
  if (!visible) return
  form.reset()
  form.clearErrors()
  form.archived_date = localDateStr()
  form.archive_reason = ''
}, { immediate: true })

function close() {
  emit('update:show', false)
  form.clearErrors()
}

function submit() {
  if (!props.item) return

  if (!form.archived_date) {
    alert('Please select an archive date.')
    return
  }

  form.transform((data) => ({
    archived: true,
    reason: data.archive_reason || null,
  })).patch(route('pharmacy.items.archive', props.item.id), {
    preserveScroll: true,
    onSuccess: close,
  })
}
</script>

<template>
  <div v-if="show" class="modal-overlay" @click.self="close">
    <div class="modal-container">
      <div class="modal-header">
        <h2 class="modal-title">Archive item</h2>
        <button class="modal-close" @click="close">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M13 1L1 13M1 1L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="warning-card">
          <div class="warning-name">{{ item?.name }}</div>
          <div class="warning-text">
            This item will be hidden from the inventory from the archive date onward. Historical records stay intact.
          </div>
        </div>

        <div class="form-row dynamic-cols">
          <div class="form-group">
            <label class="form-label">Archive date *</label>
            <input v-model="form.archived_date" type="date" class="form-input icon-right" />
            <span class="field-hint">Item disappears starting this month.</span>
          </div>
          <div class="form-group">
            <label class="form-label">Reason (optional)</label>
            <input v-model="form.archive_reason" type="text" class="form-input" placeholder="Expired, discontinued…" />
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-secondary" @click="close">Cancel</button>
        <button class="btn-danger" :disabled="form.processing" @click="submit">
          Confirm archive
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background-color: rgba(15, 23, 42, 0.45); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 24px; }
.modal-container { background: #ffffff; border-radius: 12px; width: 480px; max-width: 100%; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
.modal-header { padding: 12px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-family: system-ui, -apple-system, sans-serif; font-size: 16px; font-weight: 600; color: #1e293b; }
.modal-close { background: none; border: none; color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 4px; border-radius: 4px; }
.modal-close:hover { color: #475569; }
.modal-body { padding: 16px 20px; }

.warning-card { background-color: #fee2e2; border-radius: 6px; padding: 12px 14px; margin-bottom: 14px; }
.warning-name { font-family: system-ui, -apple-system, sans-serif; font-weight: 600; font-size: 13px; color: #1e293b; margin-bottom: 4px; }
.warning-text { font-family: system-ui, -apple-system, sans-serif; font-size: 12px; color: #991b1b; line-height: 1.4; }

.form-row { margin-bottom: 10px; }
.form-row.dynamic-cols { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-family: system-ui, -apple-system, sans-serif; font-size: 13px; font-weight: 500; color: #475569; }
.form-input { height: 34px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; background-color: #ffffff; color: #334155; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; width: 100%; box-sizing: border-box; outline: none; }
.form-input::placeholder { color: #94a3b8; }
.form-input:focus { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
.field-hint { font-family: system-ui, -apple-system, sans-serif; font-size: 11px; color: #94a3b8; }

.modal-footer { padding: 12px 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; background-color: #ffffff; }
.btn-secondary { height: 34px; padding: 0 14px; border: 1px solid #e2e8f0; border-radius: 6px; background-color: #ffffff; color: #334155; cursor: pointer; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; font-weight: 500; }
.btn-secondary:hover { background-color: #f8fafc; }
.btn-danger { height: 34px; padding: 0 14px; background-color: #dc2626; color: #ffffff; border: 1px solid #dc2626; border-radius: 6px; cursor: pointer; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; font-weight: 500; }
.btn-danger:hover { background-color: #b91c1c; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }
</style>