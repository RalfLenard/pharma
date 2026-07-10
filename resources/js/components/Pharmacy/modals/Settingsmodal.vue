<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  show: { type: Boolean, required: true },
  labSettings: { type: Object, required: true },
})
const emit = defineEmits(['update:show'])

const form = useForm({
  name: '', address: '', email: '', contact: '', logo_data_url: '',
})

watch(() => props.show, (visible) => {
  if (!visible) return
  form.reset()
  form.name = props.labSettings.name || ''
  form.address = props.labSettings.address || ''
  form.email = props.labSettings.email || ''
  form.contact = props.labSettings.contact || ''
  form.logo_data_url = props.labSettings.logo_data_url || ''
}, { immediate: true })

function close() {
  emit('update:show', false)
  form.clearErrors()
}

function onLogoChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => { form.logo_data_url = reader.result }
  reader.readAsDataURL(file)
}

function submit() {
  form.put(route('pharmacy.settings.update'), {
    preserveScroll: true,
    onSuccess: close,
  })
}
</script>

<template>
  <div v-if="show" class="overlay" @click.self="close">
    <div class="modal">
      <div class="mhead">
        <h2>Pharmacy settings</h2>
        <button class="mclose" @click="close">&times;</button>
      </div>
      <div class="mbody">
        <div class="field" style="margin-bottom:12px;">
          <label>Pharmacy / facility name</label>
          <input v-model="form.name" type="text" />
        </div>
        <div class="field" style="margin-bottom:12px;">
          <label>Address</label>
          <input v-model="form.address" type="text" />
        </div>
        <div class="frow">
          <div class="field"><label>Email</label><input v-model="form.email" type="email" /></div>
          <div class="field"><label>Contact number</label><input v-model="form.contact" type="text" /></div>
        </div>
        <div class="field">
          <label>Logo</label>
          <div class="logo-drop" @click="$refs.logoInput.click()">
            <img v-if="form.logo_data_url" :src="form.logo_data_url" class="logo-preview" />
            <div class="logo-hint">{{ form.logo_data_url ? 'Click to replace logo' : 'Click to upload logo' }}</div>
          </div>
          <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="onLogoChange" />
        </div>
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
.field input { height: 34px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 6px; outline: none; }
.logo-drop { border: 2px dashed #e2e8f0; border-radius: 6px; padding: 18px; text-align: center; cursor: pointer; background: #f8fafc; }
.logo-preview { max-height: 64px; max-width: 160px; object-fit: contain; margin: 0 auto 6px; display: block; }
.logo-hint { font-size: 11px; color: #94a3b8; }
.hidden { display: none; }
.btn { height: 34px; padding: 0 14px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; cursor: pointer; font-size: 12px; font-weight: 500; }
.btn.primary { background: #1e40af; color: #fff; border-color: #1e40af; }
</style>