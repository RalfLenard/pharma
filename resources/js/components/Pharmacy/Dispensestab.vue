<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import DispenseModal from '@/components/Pharmacy/modals/Dispensemodal.vue'

const props = defineProps({
  dispenses: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  transactions: { type: Array, default: () => [] },
})

const showModal = ref(false)
const editing = ref(null)

function openNew() {
  editing.value = null
  showModal.value = true
}
function openEdit(d) {
  editing.value = d
  showModal.value = true
}
const q = ref('')
const fPhil = ref('')
const fMonth = ref('')

function ageOf(dob) {
  if (!dob) return '—'
  const d = new Date(dob)
  const n = new Date()
  let a = n.getFullYear() - d.getFullYear()
  const m = n.getMonth() - d.getMonth()
  if (m < 0 || (m === 0 && n.getDate() < d.getDate())) a--
  return a
}

function fmtDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function monthKeyOf(v) {
  const dt = new Date(v)
  return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}`
}

const months = computed(() => {
  const map = new Map()
  props.dispenses.forEach((d) => {
    const key = monthKeyOf(d.created_at)
    if (!map.has(key)) {
      map.set(key, new Date(d.created_at).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' }))
    }
  })
  return [...map.entries()]
    .sort((a, b) => b[0].localeCompare(a[0]))
    .map(([value, label]) => ({ value, label }))
})

const rows = computed(() => {
  const s = q.value.trim().toLowerCase()
  return props.dispenses
    .filter((d) => {
      const mQ = !s
        || d.full_name?.toLowerCase().includes(s)
        || (d.brgy || '').toLowerCase().includes(s)
        || (d.dispense_by || '').toLowerCase().includes(s)
        || (d.received_by || '').toLowerCase().includes(s)
        || (d.philhealth_number || '').includes(s)
        || (d.dispense_items || []).some((l) => (l.item?.name || '').toLowerCase().includes(s))
      const mP = fPhil.value === '' || (fPhil.value === '1') === !!d.has_philhealth
      const mM = !fMonth.value || monthKeyOf(d.created_at) === fMonth.value
      return mQ && mP && mM
    })
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

const totalQty = computed(() => rows.value.reduce((s, d) => s + (Number(d.qty) || 0), 0))
const withPhil = computed(() => rows.value.filter((r) => r.has_philhealth).length)
const withoutPhil = computed(() => rows.value.length - withPhil.value)

function remove(d) {
  if (!confirm(`Delete dispense record for "${d.full_name}"?`)) return
  router.delete(route('pharmacy.dispenses.destroy', d.id), { preserveScroll: true })
}
</script>

<template>
  <div class="dsp">
    <!-- STATS -->
    <div class="stats">
      <div class="stat"><div class="stat-n">Records</div><div class="stat-v b">{{ rows.length }}</div></div>
      <div class="stat"><div class="stat-n">Total qty dispensed</div><div class="stat-v a">{{ totalQty }}</div></div>
      <div class="stat"><div class="stat-n">With PhilHealth</div><div class="stat-v g">{{ withPhil }}</div></div>
      <div class="stat"><div class="stat-n">Without PhilHealth</div><div class="stat-v r">{{ withoutPhil }}</div></div>
    </div>

    <!-- TOOLBAR -->
    <div class="tb">
      <div class="tb-l">
        <div class="sbox">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input v-model="q" type="text" placeholder="Search patient, brgy, item, staff…" />
        </div>
        <select v-model="fPhil">
          <option value="">All PhilHealth</option>
          <option value="1">With PhilHealth</option>
          <option value="0">Without PhilHealth</option>
        </select>
        <select v-model="fMonth">
          <option value="">All months</option>
          <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
        </select>
      </div>
      <button class="btn primary" @click="openNew">+ New dispense</button>
    </div>

    <!-- TABLE -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Patient</th>
            <th>Brgy</th>
            <th>Age</th>
            <th>Sex</th>
            <th>PhilHealth</th>
            <th>Items</th>
            <th class="num">Total Qty</th>
            <th>Dispensed By</th>
            <th>Received By</th>
            <th>Relationship</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!rows.length">
            <td colspan="12" class="empty">No dispense records found.</td>
          </tr>
          <tr v-for="d in rows" :key="d.id">
            <td class="nowrap small">{{ fmtDate(d.created_at) }}</td>
            <td class="strong">{{ d.full_name }}</td>
            <td>{{ d.brgy || '—' }}</td>
            <td>{{ ageOf(d.date_of_birth) }}</td>
            <td>{{ d.sex || '—' }}</td>
            <td>
              <span v-if="d.has_philhealth" class="badge b-ok">Yes</span>
              <span v-else class="badge b-no">No</span>
              <div v-if="d.has_philhealth && d.philhealth_number" class="sub" style="font-family:var(--fm);">{{ d.philhealth_number }}</div>
              <div v-if="d.has_philhealth && d.philhealth_facility" class="sub">{{ d.philhealth_facility }}</div>
            </td>
            <td>
              <div v-for="l in d.dispense_items || []" :key="l.id" class="line">
                <span>{{ l.item?.name || 'Deleted item' }}</span>
                <span class="qty">×{{ l.qty }}</span>
              </div>
              <span v-if="!(d.dispense_items || []).length" class="sub">—</span>
            </td>
            <td class="num strong">{{ d.qty }}</td>
            <td>{{ d.dispense_by }}</td>
            <td>{{ d.received_by }}</td>
            <td>{{ d.receiver_relationship || '—' }}</td>
            <td class="nowrap">
              <button class="btn sm" @click="openEdit(d)">Edit</button>
              <button class="btn sm danger" style="margin-left:4px;" @click="remove(d)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <DispenseModal v-model:show="showModal" :dispense="editing" :items="items" :transactions="transactions" />
  </div>
</template>

<style scoped>
/* Uses CSS variables defined on .pharm-app in the parent page */
.dsp { margin-top: 16px; }

/* STAT CARDS */
.stats { display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px; }
.stat { background:var(--bg);border:1px solid var(--c5);border-radius:var(--r2);padding:16px 18px; }
.stat-n { font-size:11px;color:var(--c3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px; }
.stat-v { font-size:26px;font-weight:700; }
.stat-v.b { color:var(--accent); }
.stat-v.a { color:var(--amber); }
.stat-v.g { color:var(--green); }
.stat-v.r { color:var(--red); }

/* TOOLBAR */
.tb { display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap; }
.tb-l { display:flex;gap:8px;flex:1;flex-wrap:wrap;align-items:center; }
.sbox { position:relative;flex:1;min-width:220px;max-width:340px; }
.sbox svg { position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--c3);pointer-events:none; }
.sbox input { padding-left:32px;width:100%;box-sizing:border-box; }

.btn { height:34px;padding:0 14px;border:1px solid var(--c5);border-radius:var(--r);background:var(--bg);color:var(--c);cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500; }
.btn:hover { background:var(--c6); }
.btn.primary { background:var(--accent);color:#fff;border-color:var(--accent); }
.btn.primary:hover { background:#1d3a98; }
.btn.sm { height:26px;padding:0 8px;font-size:11px; }
.btn.danger { color:var(--red);border-color:#fca5a5; }
.btn.danger:hover { background:var(--red2); }

/* TABLE */
.tbl-wrap { background:var(--bg);border:1px solid var(--c5);border-radius:var(--r2);overflow-x:auto; }
.tbl-wrap table { width:100%;border-collapse:collapse; }
.tbl-wrap th { font-size:10px;font-weight:600;color:var(--c2);text-align:left;padding:10px 12px;background:var(--c7);border-bottom:1px solid var(--c5);text-transform:uppercase;letter-spacing:.06em;white-space:nowrap; }
.tbl-wrap td { padding:10px 12px;border-bottom:1px solid var(--c6);vertical-align:middle; }
.tbl-wrap tr:last-child td { border-bottom:none; }
.tbl-wrap tbody tr:hover td { background:var(--c7); }
.num { text-align:right; }
.nowrap { white-space:nowrap; }
.small { font-size:11px; }
.strong { font-weight:600; }
.sub { font-size:10px;color:var(--c2);margin-top:2px; }
.empty { text-align:center;padding:2.5rem;color:var(--c3);font-size:13px; }

/* DISPENSED ITEM LINES */
.line { display:flex;justify-content:space-between;gap:12px;font-size:12px;padding:1px 0;min-width:170px; }
.line .qty { font-weight:700;color:var(--accent); }

/* BADGES */
.badge { display:inline-block;font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px; }
.b-ok { background:var(--green2);color:var(--green); }
.b-no { background:var(--c6);color:var(--c2); }

@media (max-width: 800px) {
  .stats { grid-template-columns:repeat(2,1fr); }
}
</style>