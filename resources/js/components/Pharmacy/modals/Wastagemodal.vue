<script setup>
import { watch, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { localDateStr, stockForMonth, fmtDate } from '@/composables/Usepharmacycalc'

const props = defineProps({
  show: { type: Boolean, required: true },
  items: { type: Array, required: true },
  transactions: { type: Array, required: true },
  curKey: { type: Number, required: true },
})
const emit = defineEmits(['update:show'])

const form = useForm({
  item_id: null, type: 'expired', qty: 1, date: localDateStr(), by: '', reason: '',
})

watch(() => props.show, (visible) => {
  if (!visible) return
  form.reset()
  form.item_id = props.items[0]?.id ?? null
  form.date = localDateStr()
  form.type = 'expired'
  form.qty = 1
}, { immediate: true })

const selectedItem = computed(() => props.items.find((i) => i.id === form.item_id))
const selectedStock = computed(() => selectedItem.value
  ? stockForMonth(props.transactions, selectedItem.value.id, props.curKey).curr
  : null)

function close() {
  emit('update:show', false)
  form.clearErrors()
}

function submit() {
  if (!form.item_id) { alert('Please select an item.'); return }
  if (!form.qty || form.qty < 1) { alert('Quantity must be at least 1.'); return }
  if (!form.reason.trim()) { alert('Please provide a reason for wastage.'); return }
  form.post(route('pharmacy.wastage.store'), {
    preserveScroll: true,
    onSuccess: close,
  })
}
</script>

<template>
  <div v-if="show" class="overlay" @click.self="close">
    <div class="modal">
      <div class="mhead">
        <h2>Record wastage</h2>
        <button class="mclose" @click="close">&times;</button>
      </div>
      <div class="mbody">
        <div class="field" style="margin-bottom:12px;">
          <label>Item *</label>
          <select v-model.number="form.item_id">
            <option v-for="i in items" :key="i.id" :value="i.id">{{ i.name }}</option>
          </select>
        </div>

        <div v-if="selectedItem" class="txn-info">
          <div class="ti-item"><div class="ti-label">Current stock</div><div class="ti-val">{{ selectedStock }}</div></div>
          <div class="ti-item"><div class="ti-label">Unit</div><div class="ti-val">{{ selectedItem.unit || '—' }}</div></div>
          <div class="ti-item"><div class="ti-label">Lot</div><div class="ti-val">{{ selectedItem.lot || '—' }}</div></div>
          <div class="ti-item"><div class="ti-label">Expiry</div><div class="ti-val">{{ fmtDate(selectedItem.exp) }}</div></div>
        </div>

        <div class="frow">
          <div class="field">
            <label>Wastage type</label>
            <select v-model="form.type">
              <option value="expired">Expired</option>
              <option value="spoiled">Spoiled / Contaminated</option>
              <option value="broken">Broken / Damaged</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="field"><label>Quantity discarded *</label><input v-model.number="form.qty" type="number" min="1" /></div>
        </div>
        <div class="frow">
          <div class="field"><label>Date</label><input v-model="form.date" type="date" /></div>
          <div class="field"><label>Recorded by</label><input v-model="form.by" type="text" placeholder="System" /></div>
        </div>
        <div class="field"><label>Reason / remarks *</label><textarea v-model="form.reason" rows="3"></textarea></div>
      </div>
      <div class="mfoot">
        <button class="btn" @click="close">Cancel</button>
        <button class="btn primary" :disabled="form.processing" @click="submit">Save</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 100; display: flex; align-items: flex-start; justify-content: center; padding: 40px 16px; overflow-y: auto; }
.modal { background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; width: 500px; max-width: 100%; margin-bottom: 40px; overflow: hidden; }
.mhead { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
.mhead h2 { font-size: 14px; font-weight: 600; }
.mclose { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 20px; line-height: 1; }
.mbody { padding: 18px 20px; }
.mfoot { padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; }
.frow { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.field label { font-size: 11px; font-weight: 500; color: #64748b; }
.field input, .field select, .field textarea { padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 6px; outline: none; }
.field input, .field select { height: 34px; }
.field textarea { padding: 8px 10px; resize: vertical; }
.txn-info { display: flex; gap: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 12px; }
.ti-item { text-align: center; }
.ti-label { font-size: 10px; color: #64748b; margin-bottom: 2px; }
.ti-val { font-size: 15px; font-weight: 700; }
.btn { height: 34px; padding: 0 14px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; cursor: pointer; font-size: 12px; font-weight: 500; }
.btn.primary { background: #1e40af; color: #fff; border-color: #1e40af; }
</style>