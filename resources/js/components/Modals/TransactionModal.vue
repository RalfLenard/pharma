<!-- resources/js/Components/Modals/TransactionModal.vue -->
<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
      <div class="p-6">
        <h3 class="text-lg font-semibold mb-4">{{ title }}</h3>
        
        <select v-model="form.type" class="w-full border rounded-lg p-3 mb-4">
          <option value="in">Stock In</option>
          <option value="out">Stock Out</option>
          <option value="adj">Adjustment</option>
        </select>

        <input v-model="form.qty" type="number" placeholder="Quantity" 
               class="w-full border rounded-lg p-3 mb-4">

        <textarea v-model="form.note" placeholder="Note / Remarks" rows="3"
                  class="w-full border rounded-lg p-3"></textarea>

        <div class="flex gap-3 mt-6">
          <button @click="close" class="flex-1 py-3 border rounded-xl">Cancel</button>
          <button @click="save" class="flex-1 py-3 bg-blue-600 text-white rounded-xl">
            Record
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const show = ref(false)
const form = ref({ type: 'in', qty: 1, note: '' })
const currentItemId = ref(null)

const emit = defineEmits(['saved'])

const open = (itemId, type = 'in') => {
  currentItemId.value = itemId
  form.value.type = type
  show.value = true
}

const save = () => {
  emit('saved', { item_id: currentItemId.value, ...form.value })
  close()
}

const close = () => show.value = false

defineExpose({ open })
</script>