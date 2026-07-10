<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-2xl w-full max-w-2xl mx-4 shadow-2xl my-8">
      <!-- Header -->
      <div class="px-6 py-5 border-b flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-900">Add New Item</h2>
        <button @click="close" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
      </div>

      <form @submit.prevent="saveItem" class="p-6 space-y-6">
        <!-- Basic Info -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Supply Item Name <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" required
                 class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="grid grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Pack Size / Volume</label>
            <input v-model="form.vol" type="text" placeholder="e.g. 500 mL, 100 strips"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Unit <span class="text-red-500">*</span></label>
            <input v-model="form.unit" type="text" required placeholder="pcs, box, vial"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
            <input v-model="form.brand" type="text" placeholder="3M, Medline..."
                   class="w-full border border-gray-300 rounded-xl px-4 py-3">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category / Section</label>
            <input v-model="form.section" type="text" placeholder="e.g. Wound Care"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Lot Number</label>
            <input v-model="form.lot_number" type="text" placeholder="LOT2026-0812"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Expiration Date</label>
            <input v-model="form.expiry" type="date"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3">
          </div>
        </div>

        <!-- Advanced Fields -->
        <div class="grid grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Min. Stock Level</label>
            <input v-model="form.min_stock" type="number" min="0" value="5"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Fund Source</label>
            <select v-model="form.fund_source"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white">
              <option value="">— Select —</option>
              <option value="General Fund (MOOE)">General Fund (MOOE)</option>
              <option value="Trust Fund">Trust Fund</option>
              <option value="DOH Subsidy">DOH Subsidy</option>
              <option value="Local Fund">Local Fund (LGU)</option>
              <option value="Donation/Grant">Donation / Grant</option>
              <option value="Others">Others</option>
            </select>
          </div>
        </div>

        <!-- Initial Stock Entry -->
        <div class="border border-gray-200 rounded-2xl p-5 bg-gray-50">
          <p class="font-medium text-gray-700 mb-4">Initial Stock Entry</p>
          
          <div class="grid grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Stock In (Received)</label>
              <input v-model="form.initial_in" type="number" min="0" @input="updateStockPreview"
                     class="w-full border border-gray-300 rounded-xl px-4 py-3">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Stock Out (Used)</label>
              <input v-model="form.initial_out" type="number" min="0" @input="updateStockPreview"
                     class="w-full border border-gray-300 rounded-xl px-4 py-3">
            </div>
          </div>

          <div class="grid grid-cols-2 gap-5 mt-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date of Entry</label>
              <input v-model="form.entry_date" type="date"
                     class="w-full border border-gray-300 rounded-xl px-4 py-3">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Performed By</label>
              <input v-model="form.performed_by" type="text" placeholder="Name / Initials"
                     class="w-full border border-gray-300 rounded-xl px-4 py-3">
            </div>
          </div>

          <!-- Stock Preview -->
          <div v-if="previewVisible" class="mt-6 p-4 bg-white border border-blue-200 rounded-xl">
            <div class="text-sm font-semibold text-blue-700 mb-3">Current Stock Preview</div>
            <div class="flex items-center gap-6">
              <div class="text-center">
                <div class="text-xs text-gray-500">Stock In</div>
                <div class="text-2xl font-bold text-green-600">{{ preview.in }}</div>
              </div>
              <div class="text-3xl text-gray-300">-</div>
              <div class="text-center">
                <div class="text-xs text-gray-500">Stock Out</div>
                <div class="text-2xl font-bold text-red-600">{{ preview.out }}</div>
              </div>
              <div class="text-3xl text-gray-300">=</div>
              <div class="text-center">
                <div class="text-xs text-gray-500">Current Stock</div>
                <div :class="['text-3xl font-bold', preview.current === 0 ? 'text-red-600' : preview.current < 10 ? 'text-amber-600' : 'text-blue-600']">
                  {{ preview.current }}
                </div>
                <div class="text-sm text-gray-500">{{ form.unit || 'units' }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3 pt-6 border-t">
          <button type="button" @click="close"
                  class="px-6 py-3 text-gray-700 hover:bg-gray-100 rounded-xl font-medium">
            Cancel
          </button>
          <button type="submit"
                  class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">
            Save Item
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const show = ref(false)
const emit = defineEmits(['saved'])

const form = ref({
  name: '',
  vol: '',
  unit: '',
  brand: '',
  section: '',
  lot_number: '',
  expiry: '',
  min_stock: 5,
  fund_source: '',
  initial_in: 0,
  initial_out: 0,
  entry_date: new Date().toISOString().split('T')[0],
  performed_by: ''
})

const preview = ref({
  in: 0,
  out: 0,
  current: 0
})

const previewVisible = computed(() => preview.value.in > 0 || preview.value.out > 0)

const updateStockPreview = () => {
  const inQty = parseInt(form.value.initial_in) || 0
  const outQty = parseInt(form.value.initial_out) || 0
  preview.value.in = inQty
  preview.value.out = outQty
  preview.value.current = Math.max(0, inQty - outQty)
}

const open = () => {
  // Reset form
  form.value = {
    name: '',
    vol: '',
    unit: '',
    brand: '',
    section: '',
    lot_number: '',
    expiry: '',
    min_stock: 5,
    fund_source: '',
    initial_in: 0,
    initial_out: 0,
    entry_date: new Date().toISOString().split('T')[0],
    performed_by: ''
  }
  preview.value = { in: 0, out: 0, current: 0 }
  show.value = true
}

const close = () => {
  show.value = false
}

const saveItem = () => {
  if (!form.value.name) {
    alert('Supply Item Name is required')
    return
  }
  emit('saved', { ...form.value })
  close()
}

defineExpose({ open, close })

// Watch for live preview
watch([() => form.value.initial_in, () => form.value.initial_out], updateStockPreview)
</script>