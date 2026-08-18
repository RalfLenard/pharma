<script setup>
const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Delete this item?' },
  message: { type: String, default: 'This action cannot be undone.' },
  confirmText: { type: String, default: 'Delete' },
  cancelText: { type: String, default: 'Cancel' },
  loading: { type: Boolean, default: false }
})

const emit = defineEmits(['update:show', 'confirm', 'cancel'])

function close() {
  if (props.loading) return
  emit('update:show', false)
  emit('cancel')
}

function confirm() {
  if (props.loading) return
  emit('confirm')
}
</script>

<template>
  <teleport to="body">
    <div v-if="show" class="modal-overlay" @click.self="close">
      <div class="modal-box">
        <div class="modal-icon">⚠️</div>
        <h3 class="modal-title">{{ title }}</h3>
        <p class="modal-message">{{ message }}</p>

        <div class="modal-footer">
          <button class="btn btn-cancel" @click="close" :disabled="loading">
            {{ cancelText }}
          </button>
          <button class="btn btn-danger" @click="confirm" :disabled="loading">
            {{ loading ? 'Deleting…' : confirmText }}
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
  z-index: 1100;
  padding: 16px;
}

.modal-box {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 380px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  padding: 24px;
  text-align: center;
}

.modal-icon {
  font-size: 32px;
  margin-bottom: 8px;
}

.modal-title {
  margin: 0 0 8px 0;
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
}

.modal-message {
  margin: 0 0 20px 0;
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
}

.modal-footer {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.btn {
  padding: 9px 18px;
  border-radius: 8px;
  font-weight: 500;
  font-size: 14px;
  cursor: pointer;
  border: none;
  flex: 1;
}

.btn-cancel {
  background: white;
  border: 1px solid #cbd5e1;
  color: #334155;
}
.btn-cancel:hover:not(:disabled) { background: #f1f5f9; }

.btn-danger {
  background: #dc2626;
  color: white;
}
.btn-danger:hover:not(:disabled) { background: #b91c1c; }

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>