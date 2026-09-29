<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  show: Boolean,
  items: { type: Array, default: () => [] },
  transactions: { type: Array, default: () => [] },
  dispense: { type: Object, default: null }, // pass a record to edit it
})
const emit = defineEmits(['update:show'])

const page = usePage()

/* Use props if given, otherwise fall back to the Inertia page props (items / transactions) */
const inventory = computed(() => (props.items?.length ? props.items : (page.props.items || [])))
const txns = computed(() => (props.transactions?.length ? props.transactions : (page.props.transactions || [])))

const isEdit = computed(() => !!props.dispense)
const nameInput = ref(null)
const search = ref('')
const open = ref(false)
const clientError = ref('')

const form = useForm({
  // Patient details
  full_name: '',
  brgy: '',
  date_of_birth: '',
  sex: '',
  has_philhealth: false,
  philhealth_number: '',
  philhealth_facility: '',
  // Items (dispense_items rows)
  items: [],
  // Dispensing details
  dispense_by: '',
  received_by: '',
  receiver_relationship: '',
})

/* ── stock per item (same formula as the inventory page) ── */
const stockMap = computed(() => {
  const m = new Map()
  inventory.value.forEach((i) => m.set(Number(i.id), (Number(i.init_in) || 0) - (Number(i.init_out) || 0)))
  txns.value.forEach((t) => {
    const id = Number(t.item_id)
    if (!m.has(id)) return
    const q = Number(t.qty) || 0
    m.set(id, m.get(id) + (t.type === 'in' ? q : t.type === 'out' ? -q : 0))
  })
  return m
})

function isExpired(item) {
  return !!item.exp && new Date(item.exp) < new Date()
}

/* ── searchable item list ── */
const options = computed(() => {
  const s = search.value.trim().toLowerCase()
  const picked = new Set(form.items.map((l) => l.item_id))
  return inventory.value
    .filter((i) => !i.archived && !picked.has(i.id))
    .filter((i) => !s
      || (i.name || '').toLowerCase().includes(s)
      || (i.brand || '').toLowerCase().includes(s)
      || (i.lot || '').toLowerCase().includes(s)
      || (i.vol || '').toLowerCase().includes(s)
      || (i.sec || '').toLowerCase().includes(s))
    .map((i) => {
      const stock = stockMap.value.get(Number(i.id)) ?? 0
      const expired = isExpired(i)
      return { item: i, stock, expired, disabled: expired || stock <= 0 }
    })
    .sort((a, b) => Number(a.disabled) - Number(b.disabled) || (a.item.name || '').localeCompare(b.item.name || ''))
    .slice(0, 50)
})

function addItem(o) {
  if (o.disabled) return
  form.items.push({
    item_id: o.item.id,
    name: o.item.name,
    brand: o.item.brand || '',
    vol: o.item.vol || '',
    lot: o.item.lot || '',
    unit: o.item.unit || 'pcs',
    stock: o.stock,
    qty: 1,
  })
  search.value = ''
  clientError.value = ''
}

function addFirst() {
  const first = options.value.find((o) => !o.disabled)
  if (first) addItem(first)
}

function removeLine(idx) {
  form.items.splice(idx, 1)
}

const totalQty = computed(() => form.items.reduce((s, l) => s + (Number(l.qty) || 0), 0))

/* ── load an existing record into the form (edit mode) ── */
function fillFromRecord(d) {
  form.full_name = d.full_name || ''
  form.brgy = d.brgy || ''
  form.date_of_birth = d.date_of_birth ? String(d.date_of_birth).slice(0, 10) : ''
  form.sex = d.sex || ''
  form.has_philhealth = !!d.has_philhealth
  form.philhealth_number = d.philhealth_number || ''
  form.philhealth_facility = d.philhealth_facility || ''
  form.dispense_by = d.dispense_by || ''
  form.received_by = d.received_by || ''
  form.receiver_relationship = d.receiver_relationship || ''

  form.items = (d.dispense_items || []).map((l) => {
    const inv = inventory.value.find((i) => Number(i.id) === Number(l.item_id)) || l.item || {}
    return {
      item_id: l.item_id,
      name: inv.name || 'Unknown item',
      brand: inv.brand || '',
      vol: inv.vol || '',
      lot: inv.lot || '',
      unit: inv.unit || 'pcs',
      // stock already had this record's qty deducted, so add it back for editing
      stock: (stockMap.value.get(Number(l.item_id)) ?? 0) + Number(l.qty),
      qty: Number(l.qty),
    }
  })
}

/* ── lifecycle ── */
watch(() => props.show, async (v) => {
  if (v) {
    form.reset()
    form.clearErrors()
    search.value = ''
    open.value = false
    clientError.value = ''
    if (props.dispense) fillFromRecord(props.dispense)
    await nextTick()
    nameInput.value?.focus()
  }
})

function close() { emit('update:show', false) }

/* PhilHealth ID mask: 00-000000000-0 (12 digits) */
function onPhilhealthInput(e) {
  const d = e.target.value.replace(/\D/g, '').slice(0, 12)
  let out = d
  if (d.length > 2) out = d.slice(0, 2) + '-' + d.slice(2)
  if (d.length > 11) out = out.slice(0, 12) + '-' + d.slice(11)
  form.philhealth_number = out
  e.target.value = out
}

function submit() {
  clientError.value = ''
  if (!form.items.length) { clientError.value = 'Add at least one item to dispense.'; return }
  const bad = form.items.find((l) => !(Number(l.qty) >= 1) || Number(l.qty) > l.stock)
  if (bad) { clientError.value = `Invalid quantity for "${bad.name}" (available: ${bad.stock}).`; return }

  if (!form.has_philhealth) {
    form.philhealth_number = ''
    form.philhealth_facility = ''
  }

  const opts = { preserveScroll: true, onSuccess: close }
  const f = form.transform((d) => ({
    ...d,
    items: d.items.map((l) => ({ item_id: l.item_id, qty: Number(l.qty) })),
  }))

  if (isEdit.value) f.put(route('pharmacy.dispenses.update', props.dispense.id), opts)
  else f.post(route('pharmacy.dispenses.store'), opts)
}
</script>

<template>
  <div v-if="show" class="dm-overlay" @click.self="close">
    <div class="dm-box">
      <div class="dm-head">
        <span>{{ isEdit ? 'Edit dispense record' : 'New dispense record' }}</span>
        <button type="button" class="dm-x" @click="close">&#10005;</button>
      </div>

      <form class="dm-body" @submit.prevent="submit">
        <!-- 1. PATIENT DETAILS -->
        <div class="dm-sec"><span class="dm-num">1</span> Patient details</div>
        <div class="dm-grid">
          <div class="dm-f full">
            <label>Full name *</label>
            <input ref="nameInput" v-model="form.full_name" type="text" required />
            <small v-if="form.errors.full_name">{{ form.errors.full_name }}</small>
          </div>
          <div class="dm-f">
            <label>Barangay</label>
            <input v-model="form.brgy" type="text" />
          </div>
          <div class="dm-f">
            <label>Date of birth</label>
            <input v-model="form.date_of_birth" type="date" />
            <small v-if="form.errors.date_of_birth">{{ form.errors.date_of_birth }}</small>
          </div>
          <div class="dm-f">
            <label>Sex</label>
            <select v-model="form.sex">
              <option value="">—</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
          <div class="dm-f">
            <label class="chk"><input v-model="form.has_philhealth" type="checkbox" /> Has PhilHealth</label>
          </div>
          <template v-if="form.has_philhealth">
            <div class="dm-f">
              <label>PhilHealth number</label>
              <input
                :value="form.philhealth_number"
                type="text"
                inputmode="numeric"
                maxlength="14"
                placeholder="00-000000000-0"
                @input="onPhilhealthInput"
              />
              <small v-if="form.errors.philhealth_number">{{ form.errors.philhealth_number }}</small>
            </div>
            <div class="dm-f">
              <label>PhilHealth facility</label>
              <input v-model="form.philhealth_facility" type="text" />
              <small v-if="form.errors.philhealth_facility">{{ form.errors.philhealth_facility }}</small>
            </div>
          </template>
        </div>

        <!-- 2. ITEMS -->
        <div class="dm-sec"><span class="dm-num">2</span> Items to dispense</div>

        <div class="dm-combo">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            v-model="search"
            type="text"
            placeholder="Search item, brand, or lot number…"
            autocomplete="off"
            @focus="open = true"
            @blur="open = false"
            @keydown.enter.prevent="addFirst"
            @keydown.esc="open = false"
          />
          <div v-if="open" class="dm-list">
            <div v-if="!options.length" class="dm-none">No matching items.</div>
            <div
              v-for="o in options" :key="o.item.id"
              class="dm-opt" :class="{ dis: o.disabled }"
              @mousedown.prevent="addItem(o)"
            >
              <div class="dm-opt-main">
                <strong>{{ o.item.name }}</strong>
                <span class="dm-opt-sub">
                  {{ [o.item.brand, o.item.vol, o.item.lot ? 'Lot ' + o.item.lot : ''].filter(Boolean).join(' · ') || '—' }}
                </span>
              </div>
              <span v-if="o.expired" class="dm-tag bad">Expired</span>
              <span v-else-if="o.stock <= 0" class="dm-tag bad">Out of stock</span>
              <span v-else class="dm-tag ok">{{ o.stock }} {{ o.item.unit || 'pcs' }}</span>
            </div>
          </div>
        </div>

        <small v-if="!inventory.length" class="dm-err">
          No inventory items were received by this modal. Make sure the page passes <code>items</code> to the Inertia component.
        </small>

        <div v-if="!form.items.length" class="dm-empty">No items added yet. Search above and click an item.</div>

        <div v-else class="dm-lines">
          <div v-for="(l, idx) in form.items" :key="l.item_id" class="dm-line">
            <div class="dm-line-name">
              <strong>{{ l.name }}</strong>
              <span class="dm-opt-sub">
                {{ [l.brand, l.vol, l.lot ? 'Lot ' + l.lot : ''].filter(Boolean).join(' · ') }}
                <template v-if="[l.brand, l.vol, l.lot].some(Boolean)"> · </template>Available: {{ l.stock }} {{ l.unit }}
              </span>
              <small v-if="form.errors['items.' + idx + '.qty']" class="dm-err">{{ form.errors['items.' + idx + '.qty'] }}</small>
            </div>
            <input v-model.number="l.qty" type="number" min="1" :max="l.stock" class="dm-qty" />
            <button type="button" class="dm-rm" title="Remove" @click="removeLine(idx)">&#10005;</button>
          </div>
        </div>

        <small v-if="form.errors.items" class="dm-err">{{ form.errors.items }}</small>
        <small v-if="clientError" class="dm-err">{{ clientError }}</small>

        <!-- 3. DISPENSING DETAILS -->
        <div class="dm-sec"><span class="dm-num">3</span> Dispensing details</div>
        <div class="dm-grid">
          <div class="dm-f">
            <label>Dispensed by *</label>
            <input v-model="form.dispense_by" type="text" required />
            <small v-if="form.errors.dispense_by">{{ form.errors.dispense_by }}</small>
          </div>
          <div class="dm-f">
            <label>Received by *</label>
            <input v-model="form.received_by" type="text" required />
            <small v-if="form.errors.received_by">{{ form.errors.received_by }}</small>
          </div>
          <div class="dm-f full">
            <label>Receiver relationship</label>
            <input v-model="form.receiver_relationship" type="text" placeholder="e.g. Self, Mother, Spouse" />
          </div>
        </div>

        <div class="dm-foot">
          <span class="dm-total">Total qty: <strong>{{ totalQty }}</strong></span>
          <span class="dm-spacer"></span>
          <button type="button" class="btn" @click="close">Cancel</button>
          <button type="submit" class="btn primary" :disabled="form.processing">
            {{ form.processing ? 'Saving…' : (isEdit ? 'Update' : 'Save') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.dm-overlay { position:fixed;inset:0;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;z-index:200;padding:16px; }
.dm-box { background:#fff;border-radius:10px;width:100%;max-width:620px;max-height:90vh;overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,.2); }
.dm-head { display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:14px;position:sticky;top:0;background:#fff;z-index:2; }
.dm-x { background:none;border:none;cursor:pointer;color:#64748b;font-size:14px; }
.dm-body { padding:6px 18px 16px; }

.dm-sec { display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.05em;margin:16px 0 10px;padding-bottom:6px;border-bottom:1px solid #e2e8f0; }
.dm-num { width:18px;height:18px;border-radius:50%;background:#1e40af;color:#fff;font-size:10px;display:inline-flex;align-items:center;justify-content:center; }

.dm-grid { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
.dm-f { display:flex;flex-direction:column;gap:4px; }
.dm-f.full { grid-column:1 / -1; }
.dm-f label { font-size:11px;font-weight:600;color:#64748b; }
.dm-f label.chk { display:flex;align-items:center;gap:6px;margin-top:20px;color:#0f172a;font-size:12px;cursor:pointer; }
.dm-f input[type=text],.dm-f input[type=number],.dm-f input[type=date],.dm-f select,
.dm-combo input,.dm-qty { height:34px;padding:0 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;font-family:inherit;background:#fff;color:#0f172a; }
.dm-f input:focus,.dm-f select:focus,.dm-combo input:focus,.dm-qty:focus { outline:none;border-color:#1e40af;box-shadow:0 0 0 3px rgba(30,64,175,.1); }
.dm-f small,.dm-err { color:#991b1b;font-size:11px;display:block;margin-top:4px; }

/* searchable item picker */
.dm-combo { position:relative; }
.dm-combo > svg { position:absolute;left:10px;top:10px;color:#94a3b8;pointer-events:none; }
.dm-combo input { width:100%;box-sizing:border-box;padding-left:32px; }
.dm-list { position:relative;margin-top:4px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.14);max-height:220px;overflow-y:auto; }
.dm-opt { display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9; }
.dm-opt:last-child { border-bottom:none; }
.dm-opt:hover { background:#f8fafc; }
.dm-opt.dis { opacity:.55;cursor:not-allowed; }
.dm-opt-main { display:flex;flex-direction:column;min-width:0; }
.dm-opt-main strong { font-size:12px; }
.dm-opt-sub { font-size:11px;color:#64748b; }
.dm-none { padding:14px;text-align:center;color:#94a3b8;font-size:12px; }
.dm-tag { font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;white-space:nowrap; }
.dm-tag.ok { background:#dcfce7;color:#166534; }
.dm-tag.bad { background:#fee2e2;color:#991b1b; }

/* selected lines */
.dm-empty { margin-top:10px;padding:14px;border:1px dashed #cbd5e1;border-radius:8px;text-align:center;color:#94a3b8;font-size:12px; }
.dm-lines { margin-top:10px;border:1px solid #e2e8f0;border-radius:8px; }
.dm-line { display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid #f1f5f9; }
.dm-line:last-child { border-bottom:none; }
.dm-line-name { flex:1;display:flex;flex-direction:column;min-width:0; }
.dm-line-name strong { font-size:12px; }
.dm-qty { width:80px;text-align:right; }
.dm-rm { width:26px;height:26px;border:1px solid #fca5a5;border-radius:6px;background:#fff;color:#991b1b;cursor:pointer;font-size:11px; }
.dm-rm:hover { background:#fee2e2; }

.dm-foot { display:flex;align-items:center;gap:8px;margin-top:18px;padding-top:12px;border-top:1px solid #e2e8f0; }
.dm-spacer { flex:1; }
.dm-total { font-size:12px;color:#64748b; }
.dm-total strong { color:#0f172a;font-size:14px; }
.btn { height:34px;padding:0 14px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;cursor:pointer;font-size:12px;font-weight:500; }
.btn:hover { background:#f1f5f9; }
.btn.primary { background:#1e40af;color:#fff;border-color:#1e40af; }
.btn.primary:hover { background:#1d3a98; }
.btn:disabled { opacity:.6;cursor:not-allowed; }

@media (max-width: 520px) { .dm-grid { grid-template-columns:1fr; } }
</style>