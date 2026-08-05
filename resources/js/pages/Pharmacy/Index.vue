<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import {
  MONTHS, monthKey, monthLabel, daysUntilExpiry, expLabel, parseLocalDate,
} from '@/composables/Usepharmacycalc'

import FastMovingTab from '@/components/Pharmacy/Fastmovingtab.vue'
import TxnLogTab from '@/components/Pharmacy/Txnlogtab.vue'
import MonthlyReportTab from '@/components/Pharmacy/Monthlyreporttab.vue'
import WastageTab from '@/components/Pharmacy/Wastagetab.vue'
import PrintTab from '@/components/Pharmacy/Printtab.vue'
import TransferTab from '@/components/Pharmacy/Transfertab.vue'

import ItemModal from '@/components/Pharmacy/modals/Itemmodal.vue'
import TxnModal from '@/components/Pharmacy/modals/Txnmodal.vue'
import WastageModal from '@/components/Pharmacy/modals/Wastagemodal.vue'
import SettingsModal from '@/components/Pharmacy/modals/Settingsmodal.vue'
import ArchiveModal from '@/components/Pharmacy/modals/Archivemodal.vue'
import TransferModal from '@/components/Pharmacy/modals/Transfermodal.vue'

const props = defineProps({
  items: { type: Array, required: true },
  transactions: { type: Array, required: true },
  wastageRecords: { type: Array, required: true },
  transfers: { type: Array, default: () => [] },
  labSettings: { type: Object, required: true },
})

/* ── tab + month state ── */
const tabs = [
  { key: 'inventory', label: 'Inventory' },
  { key: 'fastmoving', label: 'Fast-Moving' },
  { key: 'log', label: 'Txn Log' },
  { key: 'monthly', label: 'Monthly Report' },
  { key: 'wastage', label: 'Wastage' },
  { key: 'transfers', label: 'Transfers' },
  { key: 'print', label: 'Print' },
]
const activeTab = ref('inventory')

const now = new Date()
const curYear = ref(now.getFullYear())
const curMonth = ref(now.getMonth())
const curKey = computed(() => monthKey(curYear.value, curMonth.value))

function prevMonth() {
  if (curMonth.value === 0) { curMonth.value = 11; curYear.value-- } else curMonth.value--
}
function nextMonth() {
  if (curMonth.value === 11) { curMonth.value = 0; curYear.value++ } else curMonth.value++
}

/* ── search ── */
const searchQuery = ref('')

/* Filter Panel */
const filterPanelOpen = ref(false)
const fSec = ref('')
const fStatus = ref('')
const fFund = ref('')
const fDelivery = ref('')

const FUND_SOURCES = [
  'General Fund (MOOE)', 'Trust Fund', 'DOH Subsidy',
  'Local Fund', 'Donation/Grant', 'Others',
]

const sections = computed(() => [...new Set(props.items.map((i) => i.sec).filter(Boolean))].sort())

const deliveryMonths = computed(() => {
  const map = new Map()
  props.items.forEach((i) => {
    if (!i.added_date) return
    const d = parseLocalDate(i.added_date)
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    if (!map.has(key)) map.set(key, monthLabel(d.getFullYear(), d.getMonth()))
  })
  return [...map.entries()].sort((a, b) => b[0].localeCompare(a[0])).map(([value, label]) => ({ value, label }))
})

function itemDeliveryKey(item) {
  if (!item.added_date) return ''
  const d = parseLocalDate(item.added_date)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function toggleFilterPanel() {
  filterPanelOpen.value = !filterPanelOpen.value
}

const activeFilterCount = computed(() => [fSec.value, fStatus.value, fFund.value, fDelivery.value]
  .filter(Boolean).length)

function clearAllFilters() {
  searchQuery.value = ''
  fSec.value = ''
  fStatus.value = ''
  fFund.value = ''
  fDelivery.value = ''
}

/* ── FIXED Stock Calculations ── */
function itemStockIn(item) {
  if (!item) return 0
  const initialIn = Number(item.init_in) || 0
  const ledgerIn = props.transactions
    .filter(t => Number(t.item_id) === Number(item.id) && t.type === 'in')
    .reduce((sum, t) => sum + (Number(t.qty) || 0), 0)
  return initialIn + ledgerIn
}

function itemStockOut(item) {
  if (!item) return 0
  const initialOut = Number(item.init_out) || 0
  const ledgerOut = props.transactions
    .filter(t => Number(t.item_id) === Number(item.id) && t.type === 'out')
    .reduce((sum, t) => sum + (Number(t.qty) || 0), 0)
  return initialOut + ledgerOut
}

function itemStock(item) {
  return itemStockIn(item) - itemStockOut(item)
}

function itemStatus(item) {
  const stock = itemStock(item)
  if (item.exp && parseLocalDate(item.exp) < new Date()) return stock > 0 ? 'consume' : 'expired'
  if (stock <= 0) return 'critical'
  if (stock <= (item.min || 0)) return 'low'
  return 'ok'
}

const STATUS_LABEL = { ok: 'Adequate', low: 'Low stock', critical: 'Out Of Stock', expired: 'Expired', consume: 'Discard' }
const STATUS_BADGE_CLASS = { ok: 'b-ok', low: 'b-low', critical: 'b-crit', expired: 'b-exp', consume: 'b-consume' }

/* Stats */
const stats = computed(() => {
  const active = filteredItemsUnpaged.value
  let low = 0, crit = 0, exp = 0
  active.forEach((i) => {
    const st = itemStatus(i)
    if (st === 'low') low++
    else if (st === 'critical') crit++
    else if (st === 'expired') exp++
  })
  return { total: active.length, low, crit, exp }
})

/* Columns */
const showColumns = ref(false)
const columns = ref({
  vol: true, brand: true, sec: true, fund: true,
  added_date: true, quarter_delivered: true, exp: true, lot: true, stockio: true, order_qty: true,
})

const allHeaders = [
  { key: 'name', label: 'Supply Item', always: true },
  { key: 'vol', label: 'Pack Size', col: 'vol' },
  { key: 'brand', label: 'Brand', col: 'brand' },
  { key: 'sec', label: 'Category', col: 'sec' },
  { key: 'fund', label: 'Fund Source', col: 'fund' },
  { key: 'added_date', label: 'Date Added', col: 'added_date' },
  { key: 'quarter', label: 'Qtr Delivered', col: 'quarter_delivered' },
  { key: 'lot', label: 'Lot Number', col: 'lot' },
  { key: 'exp', label: 'Expiration', col: 'exp' },
  { key: 'stock_in', label: 'Stock In', col: 'stockio', num: true },
  { key: 'stock_out', label: 'Stock Out', col: 'stockio', num: true },
  { key: 'current_stock', label: 'Current Stock', always: true, num: true },
  // { key: 'order_qty', label: 'On Order', col: 'order_qty', num: true },
  { key: 'status', label: 'Status', always: true },
  { key: 'actions', label: 'Actions', always: true },
]

const inventoryHeaders = computed(() => allHeaders.filter((h) => h.always || columns.value[h.col]))

function isColVisible(colKey) {
  return columns.value[colKey]
}

/* Outside click */
const filterBarRef = ref(null)
function handleOutsideClick(e) {
  if (filterBarRef.value && !filterBarRef.value.contains(e.target)) {
    showColumns.value = false
  }
}
onMounted(() => document.addEventListener('click', handleOutsideClick))
onUnmounted(() => document.removeEventListener('click', handleOutsideClick))

/* Filtered Items */
const SEC_ORDER = [
  'Dispensing Supplies', 'Wound Care', 'PPE', 'Diagnostic Supplies',
  'Injection & IV Supplies', 'Packaging & Labeling', 'Sanitation & Disinfection',
  'Diabetic Care', 'Others',
]

function parseQuarterKey(str) {
  if (!str) return null
  const m = str.match(/Q([1-4])\s+(\d{4})/)
  if (!m) return null
  return parseInt(m[2]) * 10 + parseInt(m[1])
}

const filteredItemsUnpaged = computed(() => props.items.filter((item) => {
  if (item.archived) return false
  const q = searchQuery.value.trim().toLowerCase()
  const matchesQ = !q || item.name?.toLowerCase().includes(q) ||
                   (item.brand || '').toLowerCase().includes(q) ||
                   (item.lot || '').toLowerCase().includes(q)
  const matchesSec = !fSec.value || item.sec === fSec.value
  const matchesStatus = !fStatus.value || itemStatus(item) === fStatus.value
  const matchesFund = !fFund.value || item.fund === fFund.value
  const matchesDelivery = !fDelivery.value || itemDeliveryKey(item) === fDelivery.value
  return matchesQ && matchesSec && matchesStatus && matchesFund && matchesDelivery
}))

const filteredItems = computed(() => [...filteredItemsUnpaged.value].sort((a, b) => {
  const ai = SEC_ORDER.indexOf(a.sec), bi = SEC_ORDER.indexOf(b.sec)
  const si = (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi)
  return si !== 0 ? si : a.name.localeCompare(b.name)
}))

function isFirstInSection(index) {
  if (index === 0) return true
  return filteredItems.value[index].sec !== filteredItems.value[index - 1].sec
}

/* Expiry Alerts */
const notifOpen = ref(false)
const expiryAlerts = computed(() => props.items
  .filter((i) => !i.archived)
  .map((i) => ({ item: i, days: daysUntilExpiry(i.exp) }))
  .filter((x) => x.days !== null && x.days <= 60)
  .sort((a, b) => a.days - b.days))

/* Modals */
const itemModal = ref({ show: false, mode: 'add', item: null })
const txnModal = ref({ show: false, itemId: null, type: 'in' })
const wastageModal = ref({ show: false })
const settingsModal = ref({ show: false })
const archiveModal = ref({ show: false, item: null })

function openAddItem() {
  itemModal.value = { show: false, mode: 'add', item: null }
  requestAnimationFrame(() => { itemModal.value.show = true })
}
function openEditItem(item) {
  itemModal.value = { show: true, mode: 'edit', item }
}
function openTxn(itemId, type) {
  txnModal.value = { show: true, itemId, type }
}
function openArchiveItem(item) {
  archiveModal.value = { show: true, item }
}
function restoreItem(item) {
  router.patch(route('pharmacy.items.restore', item.id), {}, { preserveScroll: true })
}
function permanentDeleteItem(item) {
  if (!confirm(`Permanently delete "${item.name}"?\n\nThis removes the item AND all transaction history. Cannot be undone.`)) return
  router.delete(route('pharmacy.items.destroy', item.id), { preserveScroll: true })
}

const openMenuId = ref(null)
function toggleDotMenu(id) {
  openMenuId.value = openMenuId.value === id ? null : id
}
function closeDotMenus() {
  openMenuId.value = null
}

const showArchived = ref(false)
const archivedItems = computed(() => props.items.filter((i) => i.archived))

const transferModal = ref({ show: false, item: null })

// Add this function
function openTransferModal(item) {
  transferModal.value = { show: true, item }
}
</script>

<template>
  <Head title="Pharmacy Supplies Inventory" />

  <div class="pharm-app">
    <!-- TOP BAR -->
    <div class="topbar">
      <div class="topbar-left">
        <div class="logo-dot"></div>
        <div>
          <div class="lab-name">{{ labSettings.name || 'Pharmacy Supplies Inventory' }}</div>
          <div class="lab-sub">Medical &amp; pharmacy supplies</div>
        </div>
      </div>

      <div class="topbar-right">
        <div class="tabs">
          <button
            v-for="t in tabs" :key="t.key"
            class="tab" :class="{ on: activeTab === t.key }"
            @click="activeTab = t.key"
          >{{ t.label }}</button>
        </div>

        <div class="month-nav">
          <button class="month-arrow" @click="prevMonth">&#8592;</button>
          <span class="month-label">{{ monthLabel(curYear, curMonth) }}</span>
          <button class="month-arrow" @click="nextMonth">&#8594;</button>
        </div>

        <button class="btn ghost" @click="settingsModal.show = true">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
          Settings
        </button>
      </div>
    </div>

    <div class="wrap">
      <!-- EXPIRY ALERT STRIP -->
      <div v-if="expiryAlerts.length" class="alert-strip" style="margin-top:16px;">
        <div class="alert-head" @click="notifOpen = !notifOpen">
          <div class="alert-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            Expiration alerts
            <span class="alert-badge">{{ expiryAlerts.length }}</span>
          </div>
          <span class="alert-chevron" :class="{ open: notifOpen }">&#9660;</span>
        </div>
        <div class="alert-body" :class="{ open: notifOpen }">
          <div
            v-for="a in expiryAlerts" :key="a.item.id"
            class="alert-row"
          >
            <span class="adot" :class="a.days < 0 ? 'exp' : 'soon'"></span>
            <span class="aname">{{ a.item.name }}</span>
            <span style="font-size:11px;color:var(--c3);">{{ a.item.exp }}</span>
            <span class="atag" :class="a.days < 0 ? 'exp' : a.days <= 30 ? 's30' : 's60'">{{ expLabel(a.days) }}</span>
          </div>
        </div>
      </div>

      <!-- ═══ INVENTORY TAB ═══ -->
      <div v-if="activeTab === 'inventory'" class="view on content">
        <div class="stats">
          <div class="stat"><div class="stat-n">Total items</div><div class="stat-v b">{{ stats.total }}</div></div>
          <div class="stat"><div class="stat-n">Low stock</div><div class="stat-v a">{{ stats.low }}</div></div>
          <div class="stat"><div class="stat-n">Critical / out</div><div class="stat-v r">{{ stats.crit }}</div></div>
          <div class="stat"><div class="stat-n">Expired</div><div class="stat-v r">{{ stats.exp }}</div></div>
        </div>

        <!-- PRIMARY TOOLBAR -->
        <div class="tb" style="margin-bottom:8px;" ref="filterBarRef">
          <div class="tb-l">
            <div class="sbox" style="min-width:220px;max-width:340px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input v-model="searchQuery" type="text" placeholder="Search item, brand, lot…" />
            </div>

            <button class="btn" style="gap:6px;" @click="toggleFilterPanel">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
              Filters
              <span v-if="activeFilterCount" class="filter-active-count">{{ activeFilterCount }}</span>
              <span class="filter-chevron" :class="{ open: filterPanelOpen }">&#9660;</span>
            </button>

            <div class="col-toggle-wrap">
              <button class="col-toggle-btn" @click.stop="showColumns = !showColumns">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
                Columns
              </button>

              <div v-if="showColumns" class="col-dropdown open" @click.stop>
                <div style="padding:6px 14px 4px;font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.06em;">Always visible</div>
                <label style="opacity:.55;pointer-events:none;"><input type="checkbox" checked disabled> Supply Item</label>
                <label style="opacity:.55;pointer-events:none;"><input type="checkbox" checked disabled> Current Stock</label>
                <label style="opacity:.55;pointer-events:none;"><input type="checkbox" checked disabled> Status</label>
                <label style="opacity:.55;pointer-events:none;"><input type="checkbox" checked disabled> Actions</label>
                <hr class="col-dropdown-divider">
                <div style="padding:6px 14px 4px;font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.06em;">Optional columns</div>
                <label><input type="checkbox" v-model="columns.vol"> Pack Size</label>
                <label><input type="checkbox" v-model="columns.brand"> Brand</label>
                <label><input type="checkbox" v-model="columns.sec"> Category</label>
                <label><input type="checkbox" v-model="columns.fund"> Fund Source</label>
                <label><input type="checkbox" v-model="columns.added_date"> Date Added</label>
                <label><input type="checkbox" v-model="columns.quarter_delivered"> Quarter Delivered</label>
                <label><input type="checkbox" v-model="columns.exp"> Expiry</label>
                <label><input type="checkbox" v-model="columns.lot"> Lot Number</label>
                <label><input type="checkbox" v-model="columns.stockio"> Stock In / Out</label>
              
              </div>
            </div>
          </div>

          <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <button class="btn">CSV</button>
            <button class="btn">Backup</button>
            <button class="btn">Restore</button>
            <button class="btn" :style="showArchived ? 'background:var(--accent2)' : ''" @click="showArchived = !showArchived">Archived</button>
            <!-- <button class="btn">On Order</button> -->
            <button class="btn primary" @click="openAddItem">+ Add item</button>
          </div>
        </div>

        <!-- COLLAPSIBLE FILTER PANEL -->
        <div class="filter-panel" style="margin-bottom:14px;">
          <div v-show="filterPanelOpen" class="filter-panel-body" style="display:flex;">
            <select v-model="fSec">
              <option value="">All sections</option>
              <option v-for="s in sections" :key="s" :value="s">{{ s }}</option>
            </select>
            <select v-model="fStatus">
              <option value="">All statuses</option>
              <option value="ok">Adequate</option>
              <option value="low">Low stock</option>
              <option value="critical">Critical</option>
              <option value="expired">Expired</option>
              <option value="consume">Discard</option>
            </select>
            <select v-model="fFund">
              <option value="">All fund sources</option>
              <option v-for="f in FUND_SOURCES" :key="f" :value="f">{{ f }}</option>
            </select>
            <select v-model="fDelivery">
              <option value="">All delivery months</option>
              <option v-for="m in deliveryMonths" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <button class="btn" style="color:var(--red);border-color:#fca5a5;" @click="clearAllFilters">&#10005; Clear filters</button>
          </div>
        </div>

        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th v-for="h in inventoryHeaders" :key="h.key" :class="{ num: h.num }">{{ h.label }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filteredItems.length">
                <td :colspan="inventoryHeaders.length" class="empty">No items match your filters.</td>
              </tr>

              <template v-else v-for="(item, index) in filteredItems" :key="item.id">
                <tr v-if="isFirstInSection(index)" class="sec-row">
                  <td :colspan="inventoryHeaders.length">{{ item.sec || 'Unassigned' }}</td>
                </tr>
                <tr>
                  <td style="font-weight:500;max-width:170px;white-space:normal;">{{ item.name }}</td>
                  <td v-if="isColVisible('vol')" style="color:var(--c2);font-size:12px;">{{ item.vol || '—' }}</td>
                  <td v-if="isColVisible('brand')" style="font-weight:500;">{{ item.brand || '—' }}</td>
                  <td v-if="isColVisible('sec')"><span class="b-sec">{{ item.sec || '—' }}</span></td>
                  <td v-if="isColVisible('fund')">
                    <span v-if="item.fund" class="b-fund">{{ item.fund }}</span>
                    <span v-else style="color:var(--c4);font-size:11px;">—</span>
                  </td>
                  <td v-if="isColVisible('added_date')" style="font-size:11px;white-space:nowrap;">{{ item.added_date || '—' }}</td>
                   <td v-if="isColVisible('quarter_delivered')" style="font-size:11px;white-space:nowrap;">{{ item.quarter_delivered || '—' }}</td>
                  <td v-if="isColVisible('lot')" style="font-size:11px;font-family:var(--fm);color:var(--c2);">{{ item.lot || '—' }}</td>
                  <td v-if="isColVisible('exp')">
                    <div class="exp-cell">
                      <span>{{ item.exp || '—' }}</span>
                    </div>
                  </td>
                  <td v-if="isColVisible('stockio')" class="num"><span class="badge b-in">+{{ itemStockIn(item) }}</span></td>
                  <td v-if="isColVisible('stockio')" class="num"><span class="badge b-out">&#8722;{{ itemStockOut(item) }}</span></td>
                  <td class="num" :style="itemStock(item) === 0 ? 'color:var(--red);font-weight:700;' : itemStock(item) <= (item.min||0) ? 'color:var(--amber);font-weight:700;' : 'font-weight:600;'">
                    {{ itemStock(item) }} <span style="font-size:12px;color:#64748b;">{{ item.unit || 'pcs' }}</span>
                  </td>
                 
                  <td><span class="badge" :class="STATUS_BADGE_CLASS[itemStatus(item)]">{{ STATUS_LABEL[itemStatus(item)] }}</span></td>
                  <td style="white-space:nowrap;">
                    <div class="dot-menu-wrap">
                      <button class="dot-menu-btn" @click.stop="toggleDotMenu(item.id)" title="Actions">&#8943;</button>
                      <div class="dot-menu" :class="{ open: openMenuId === item.id }" @click="closeDotMenus">
                        <button class="dm-in" @click="openTxn(item.id, 'in')">&#43; Stock In</button>
                        <button class="dm-out" @click="openTxn(item.id, 'out')">&#8722; Stock Out</button>
                        <button class="dm-transfer" @click="openTransferModal(item)">
                          ↔ Transfer Item
                        </button>
                        <hr class="dot-menu-sep">
                        <button @click="openEditItem(item)">&#9998; Edit item</button>
                        <button class="dm-archive" @click="openArchiveItem(item)">&#128451; Archive item</button>
                        <hr class="dot-menu-sep">
                        <button class="dm-del" @click="permanentDeleteItem(item)">&#128465; Delete permanently</button>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div v-if="showArchived" class="arch-section">
          <div class="arch-head">
            <span style="display:flex;align-items:center;gap:6px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
              Archived items
            </span>
          </div>
          <div class="tbl-wrap" style="opacity:.85;">
            <table>
              <thead>
                <tr>
                  <th>Supply Item</th><th>Pack Size</th><th>Brand</th><th>Section</th>
                  <th>Lot</th><th>Expiration</th><th class="num">Total in</th>
                  <th class="num">Total out</th><th>Archived on</th><th>Reason</th><th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!archivedItems.length"><td colspan="11" class="empty">No archived items.</td></tr>
                <tr v-else v-for="item in archivedItems" :key="item.id">
                  <td style="font-weight:500;">{{ item.name }}</td>
                  <td>{{ item.vol || '—' }}</td>
                  <td>{{ item.brand || '—' }}</td>
                  <td><span class="b-sec">{{ item.sec || '—' }}</span></td>
                  <td style="font-family:var(--fm);">{{ item.lot || '—' }}</td>
                  <td>{{ item.exp || '—' }}</td>
                  <td class="num">{{ itemStockIn(item) }}</td>
                  <td class="num">{{ itemStockOut(item) }}</td>
                  <td>{{ item.archived_date || '—' }}</td>
                  <td>{{ item.archive_reason || '—' }}</td>
                  <td style="white-space:nowrap;">
                    <button class="btn" style="height:26px;padding:0 8px;font-size:11px;" @click="restoreItem(item)">Restore</button>
                    <button class="btn" style="height:26px;padding:0 8px;font-size:11px;color:var(--red);border-color:#fca5a5;" @click="permanentDeleteItem(item)">Delete</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ALTERNATIVE MODULE TABS -->
      <div v-else>
        <FastMovingTab v-if="activeTab === 'fastmoving'" :items="items" :transactions="transactions" />
        <TxnLogTab v-else-if="activeTab === 'log'" :items="items" :transactions="transactions" @stock="openTxn" />
        <MonthlyReportTab v-else-if="activeTab === 'monthly'" :items="items" :transactions="transactions" :wastage-records="wastageRecords" :cur-key="curKey" :cur-year="curYear" :cur-month="curMonth" />
        <WastageTab v-else-if="activeTab === 'wastage'" :items="items" :wastage-records="wastageRecords" @add="wastageModal.show = true" />
       <TransferTab v-else-if="activeTab === 'transfers'" :items="items" :transfers="transfers" />
        <PrintTab v-else-if="activeTab === 'print'" :items="items" :transactions="transactions" :cur-key="curKey" :lab-settings="labSettings" />
      </div>
    </div>

    <!-- MODAL FRAMEWORKS -->
    <ItemModal v-model:show="itemModal.show" :mode="itemModal.mode" :item="itemModal.item" />
    <TxnModal v-model:show="txnModal.show" :items="items" :transactions="transactions" :item-id="txnModal.itemId" :type="txnModal.type" />
    <WastageModal v-model:show="wastageModal.show" :items="items" :transactions="transactions" :cur-key="curKey" />
    <SettingsModal v-model:show="settingsModal.show" :lab-settings="labSettings" />
    <ArchiveModal v-model:show="archiveModal.show" :item="archiveModal.item"  :items="items"/>

    <!-- Transfer Modal -->
    <TransferModal 
      v-model:show="transferModal.show" 
      :item="transferModal.item"
      :current-stock="transferModal.item ? itemStock(transferModal.item) : 0"
    />
  </div>
</template>

<style scoped>
.pharm-app {
  --c:#0f172a;--c2:#64748b;--c3:#94a3b8;--c4:#cbd5e1;--c5:#e2e8f0;--c6:#f1f5f9;--c7:#f8fafc;
  --bg:#ffffff;--accent:#1e40af;--accent2:#dbeafe;
  --green:#166534;--green2:#dcfce7;--red:#991b1b;--red2:#fee2e2;
  --amber:#92400e;--amber2:#fef3c7;--r:6px;--r2:10px;
  --fs:system-ui,-apple-system,sans-serif;--fm:ui-monospace,monospace;
  font-family: var(--fs);
  background: var(--c6);
  color: var(--c);
  font-size: 13px;
  line-height: 1.5;
  min-height: 100vh;
}
.pharm-app :deep(input),
.pharm-app :deep(select),
.pharm-app :deep(button),
.pharm-app :deep(textarea) { font-family: inherit; font-size: 13px; }
.pharm-app :deep(input[type=text]),
.pharm-app :deep(input[type=number]),
.pharm-app :deep(input[type=date]),
.pharm-app :deep(select) {
  height: 34px; padding: 0 10px; border: 1px solid var(--c5); border-radius: var(--r);
  background: var(--bg); color: var(--c); outline: none; transition: border .15s;
}
.pharm-app :deep(input:focus),
.pharm-app :deep(select:focus) { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(30,64,175,.1); }

.wrap { max-width: 1400px; margin: 0 auto; padding: 16px; }

/* TOPBAR */
.topbar { display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:var(--bg);border-bottom:1px solid var(--c5);position:sticky;top:0;z-index:50; }
.topbar-left { display:flex;align-items:center;gap:14px; }
.logo-dot { width:8px;height:8px;border-radius:50%;background:var(--accent); }
.lab-name { font-size:14px;font-weight:600;color:var(--c); }
.lab-sub { font-size:11px;color:var(--c3);margin-top:1px; }
.topbar-right { display:flex;align-items:center;gap:8px;flex-wrap:wrap; }
.month-nav { display:flex;align-items:center;gap:6px;background:var(--c7);border:1px solid var(--c5);border-radius:var(--r);padding:4px 8px; }
.month-label { font-size:12px;font-weight:600;color:var(--c);min-width:100px;text-align:center; }
.month-arrow { background:none;border:none;color:var(--c2);cursor:pointer;padding:2px 6px;border-radius:4px;font-size:14px; }
.month-arrow:hover { background:var(--c5); }

/* TABS */
.tabs { display:flex;gap:1px;background:var(--c5);border-radius:var(--r);padding:2px;width:fit-content; }
.tab { padding:6px 14px;font-size:12px;font-weight:500;border:none;background:none;color:var(--c2);cursor:pointer;border-radius:5px;white-space:nowrap; }
.tab.on { background:var(--bg);color:var(--c);box-shadow:0 1px 3px rgba(0,0,0,.08); }
.tab:hover:not(.on) { color:var(--c); }

/* ALERT STRIP */
.alert-strip { background:var(--red2);border:1px solid #fca5a5;border-radius:var(--r);margin-bottom:16px;overflow:hidden; }
.alert-head { display:flex;align-items:center;justify-content:space-between;padding:10px 14px;cursor:pointer; }
.alert-title { display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;color:var(--red); }
.alert-badge { background:#fca5a5;color:var(--red);font-size:10px;padding:1px 6px;border-radius:20px;font-weight:700; }
.alert-chevron { color:var(--red);font-size:11px;transition:transform .2s; }
.alert-chevron.open { transform:rotate(180deg); }
.alert-body { display:none;background:var(--bg);border-top:1px solid #fca5a5; }
.alert-body.open { display:block; }
.alert-row { display:flex;align-items:center;gap:12px;padding:8px 14px;border-bottom:1px solid var(--c5);font-size:12px; }
.alert-row:last-child { border-bottom:none; }
.adot { width:6px;height:6px;border-radius:50%;flex-shrink:0; }
.adot.exp { background:#dc2626; }
.adot.soon { background:#d97706; }
.aname { flex:1;font-weight:500; }
.atag { font-size:10px;padding:1px 7px;border-radius:20px;font-weight:600;white-space:nowrap; }
.atag.exp { background:var(--red2);color:var(--red); }
.atag.s30 { background:#fee2e2;color:#9a1616; }
.atag.s60 { background:var(--amber2);color:var(--amber); }

/* STAT CARDS */
.stats { display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px; }
.stat { background:var(--bg);border:1px solid var(--c5);border-radius:var(--r2);padding:16px 18px; }
.stat-n { font-size:11px;color:var(--c3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px; }
.stat-v { font-size:26px;font-weight:700; }
.stat-v.b { color:var(--accent); }
.stat-v.a { color:var(--amber); }
.stat-v.r { color:var(--red); }

/* TOOLBAR */
.tb { display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap;position:relative; }
.tb-l { display:flex;gap:8px;flex:1;flex-wrap:wrap; }
.sbox { position:relative;flex:1;min-width:160px; }
.sbox svg { position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--c3); }
.sbox input { padding-left:32px;width:100%; }
.btn { height:34px;padding:0 14px;border:1px solid var(--c5);border-radius:var(--r);background:var(--bg);color:var(--c);cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500; }
.btn:hover { background:var(--c6); }
.btn.primary { background:var(--accent);color:#fff;border-color:var(--accent); }
.btn.primary:hover { background:#1d3a98; }
.btn.ghost { border-color:var(--accent2);color:var(--accent);background:var(--accent2); }
.btn.ghost:hover { background:#bfdbfe; }

/* FILTER PANEL */
.filter-panel { background:var(--bg);border:1px solid var(--c5);border-radius:var(--r2);overflow:hidden;transition:all .2s; }
.filter-panel-body { padding:12px 14px;border-top:1px solid var(--c5);flex-wrap:wrap;gap:8px; }
.filter-active-count { background:var(--accent);color:#fff;font-size:10px;padding:1px 6px;border-radius:20px;font-weight:700; }
.filter-chevron { color:var(--c3);font-size:11px;transition:transform .2s; }
.filter-chevron.open { transform:rotate(180deg); }

/* COLUMN TOGGLE DROPDOWN */
.col-toggle-wrap { position:relative; }
.col-toggle-btn { height:34px;padding:0 12px;border:1px solid var(--c5);border-radius:var(--r);background:var(--bg);color:var(--c2);cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500;white-space:nowrap; }
.col-toggle-btn:hover { background:var(--c6); }
.col-dropdown { display:none;position:absolute;top:calc(100% + 4px);right:0;background:var(--bg);border:1px solid var(--c5);border-radius:var(--r2);box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:80;min-width:180px;padding:8px 0; }
.col-dropdown.open { display:block; }
.col-dropdown label { display:flex;align-items:center;gap:8px;padding:6px 14px;font-size:12px;cursor:pointer;color:var(--c); }
.col-dropdown label:hover { background:var(--c7); }
.col-dropdown input[type=checkbox] { width:13px;height:13px;accent-color:var(--accent);cursor:pointer; }
.col-dropdown-divider { border:none;border-top:1px solid var(--c5);margin:4px 0; }

/* TABLE */
.tbl-wrap { background:var(--bg);border:1px solid var(--c5);border-radius:var(--r2);overflow:visible; }
.tbl-wrap table { width:100%;border-collapse:collapse; }
.tbl-wrap th { font-size:10px;font-weight:600;color:var(--c2);text-align:left;padding:10px 12px;background:var(--c7);border-bottom:1px solid var(--c5);text-transform:uppercase;letter-spacing:.06em;white-space:nowrap; }
.tbl-wrap td { padding:10px 12px;border-bottom:1px solid var(--c6);vertical-align:middle; }
.tbl-wrap tr:last-child td { border-bottom:none; }
.tbl-wrap tr:hover td { background:var(--c7); }
.num { text-align:right; }
.empty { text-align:center;padding:2.5rem;color:var(--c3);font-size:13px; }

/* SECTION ROW */
.sec-row td { background:var(--accent);color:#fff;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:6px 12px; }

/* BADGES */
.badge { display:inline-block;font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px; }
.b-ok { background:var(--green2);color:var(--green); }
.b-low { background:var(--amber2);color:var(--amber); }
.b-crit { background:var(--red2);color:var(--red); }
.b-exp { background:#1c1917;color:#f5f5f4; }
.b-consume { background:#fff7ed;color:#c2410c;border:1px solid #fed7aa; }
.b-fund { font-size:10px;padding:2px 7px;border-radius:20px;font-weight:600;white-space:nowrap;background:var(--c6);color:var(--c2); }
.b-in { background:#dcfce7;color:#14532d; }
.b-out { background:#fee2e2;color:#7f1d1d; }
.b-sec { background:var(--accent2);color:var(--accent);font-size:10px;padding:1px 7px;border-radius:20px; }

/* EXP CELL */
.exp-cell { display:flex;flex-direction:column;gap:2px; }

/* DOT MENU */
.dot-menu-wrap { position:relative;display:inline-block; }
.dot-menu-btn { width:28px;height:28px;border:1px solid var(--c5);border-radius:var(--r);background:var(--bg);color:var(--c2);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:16px;line-height:1; }
.dot-menu-btn:hover { background:var(--c6);color:var(--c); }
.dot-menu { display:none;position:absolute;top:calc(100% + 4px);right:0;background:var(--bg);border:1px solid var(--c5);border-radius:var(--r2);box-shadow:0 4px 16px rgba(0,0,0,.12);z-index:90;min-width:150px;padding:4px 0; }
.dot-menu.open { display:block; }
.dot-menu button { display:flex;align-items:center;gap:8px;width:100%;padding:7px 14px;background:none;border:none;font-size:12px;color:var(--c);cursor:pointer;text-align:left; }
.dot-menu button:hover { background:var(--c7); }
.dot-menu button.dm-in { color:var(--green); }
.dot-menu button.dm-out { color:var(--red); }
.dot-menu button.dm-archive { color:var(--amber); }
.dot-menu button.dm-del { color:var(--red); }
.dot-menu-sep { border:none;border-top:1px solid var(--c5);margin:4px 0; }

/* ARCHIVED SECTION */
.arch-section { margin-top:20px; }
.arch-head { font-size:11px;font-weight:600;color:var(--c2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;display:flex;align-items:center;gap:6px; }
</style>