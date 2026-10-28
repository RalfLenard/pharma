<script setup>
import { ref, computed } from 'vue'

/**
 * Reusable pagination bar (same look as the Inventory page).
 * Place it directly AFTER the table wrapper — on page change it scrolls
 * back to the top of that previous sibling element.
 *
 * Usage:
 *   <Pagination v-model:page="page" v-model:perPage="perPage" :total="rows.length" label="records" />
 *   <Pagination v-model:page="page" :per-page="15" :total="total" :show-size="false" />   (server-side)
 */
const props = defineProps({
  page: { type: Number, required: true },
  perPage: { type: Number, default: 20 },
  total: { type: Number, required: true },
  options: { type: Array, default: () => [20, 50, 100] },
  label: { type: String, default: 'items' },
  showSize: { type: Boolean, default: true },
  offset: { type: Number, default: 76 }, // sticky top bar height
})

const emit = defineEmits(['update:page', 'update:perPage'])

const root = ref(null)

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)))
const rangeStart = computed(() => (props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1))
const rangeEnd = computed(() => Math.min(props.page * props.perPage, props.total))

const pageNumbers = computed(() => {
  const total = totalPages.value
  const cur = props.page
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const pages = [1]
  const start = Math.max(2, cur - 1)
  const end = Math.min(total - 1, cur + 1)
  if (start > 2) pages.push('…')
  for (let p = start; p <= end; p++) pages.push(p)
  if (end < total - 1) pages.push('…')
  pages.push(total)
  return pages
})

function scrollToTable() {
  const el = root.value?.previousElementSibling
  if (!el) return
  const top = el.getBoundingClientRect().top
  if (top < props.offset) {
    window.scrollTo({ top: Math.max(0, top + window.scrollY - props.offset), behavior: 'smooth' })
  }
}

function go(p) {
  if (typeof p !== 'number') return
  const next = Math.min(Math.max(1, p), totalPages.value)
  if (next === props.page) return
  emit('update:page', next)
  scrollToTable()
}
</script>

<template>
  <div ref="root" class="pager">
    <div class="pager-info">
      Showing <strong>{{ rangeStart }}–{{ rangeEnd }}</strong> of <strong>{{ total }}</strong> {{ label }}
    </div>

    <div v-if="totalPages > 1" class="pager-nav">
      <button class="pg-btn" :disabled="page === 1" title="First page" @click="go(1)">&laquo;</button>
      <button class="pg-btn" :disabled="page === 1" title="Previous page" @click="go(page - 1)">&lsaquo;</button>
      <button
        v-for="(p, i) in pageNumbers" :key="`${p}-${i}`"
        class="pg-btn"
        :class="{ on: p === page, gap: p === '…' }"
        :disabled="p === '…'"
        @click="go(p)"
      >{{ p }}</button>
      <button class="pg-btn" :disabled="page === totalPages" title="Next page" @click="go(page + 1)">&rsaquo;</button>
      <button class="pg-btn" :disabled="page === totalPages" title="Last page" @click="go(totalPages)">&raquo;</button>
    </div>

    <label v-if="showSize" class="pager-size">
      Rows per page
      <select :value="perPage" @change="emit('update:perPage', Number($event.target.value))">
        <option v-for="n in options" :key="n" :value="n">{{ n }}</option>
      </select>
    </label>
  </div>
</template>

<style scoped>
.pager {
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  margin-top: 12px; padding: 10px 14px;
  background: var(--bg, #fff); border: 1px solid var(--c5, #e2e8f0); border-radius: var(--r2, 10px);
}
.pager-info { font-size: 12px; color: var(--c2, #64748b); }
.pager-info strong { color: var(--c, #0f172a); font-weight: 600; }
.pager-nav { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.pg-btn {
  min-width: 30px; height: 30px; padding: 0 8px;
  border: 1px solid var(--c5, #e2e8f0); border-radius: var(--r, 6px);
  background: var(--bg, #fff); color: var(--c, #0f172a);
  font-size: 12px; font-weight: 500; cursor: pointer;
}
.pg-btn:hover:not(:disabled):not(.on) { background: var(--c6, #f1f5f9); }
.pg-btn.on { background: var(--accent, #1e40af); border-color: var(--accent, #1e40af); color: #fff; }
.pg-btn:disabled { color: var(--c4, #cbd5e1); cursor: default; }
.pg-btn.gap { border-color: transparent; background: none; }
.pg-btn:focus-visible { outline: 2px solid var(--accent, #1e40af); outline-offset: 1px; }
.pager-size { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--c2, #64748b); }
.pager-size select {
  height: 30px !important; padding: 0 8px; border: 1px solid var(--c5, #e2e8f0);
  border-radius: var(--r, 6px); background: var(--bg, #fff); color: var(--c, #0f172a);
}

@media (max-width: 900px) { .pager { justify-content: center; } }
@media print { .pager { display: none !important; } }
</style>