<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '@/api/endpoints'
import { describeError } from '@/api/errors'
import PaginationBar from '@/components/PaginationBar.vue'
import TransactionsTable from '@/components/TransactionsTable.vue'
import type { Paginated, Transaction, TransactionFilters, TransactionType } from '@/types/api'

const route = useRoute()
const router = useRouter()

const result = ref<Paginated<Transaction> | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

/** Filters live in the URL, so a reload or a shared link keeps them. */
function filtersFromQuery(): TransactionFilters {
  const query = route.query
  return {
    type: query.type === 'income' || query.type === 'expense' ? (query.type as TransactionType) : undefined,
    from: typeof query.from === 'string' ? query.from : undefined,
    to: typeof query.to === 'string' ? query.to : undefined,
    per_page: Number(query.per_page) || 20,
    page: Number(query.page) || 1,
  }
}

const form = reactive({ type: '', from: '', to: '', per_page: 20 })

function syncForm(filters: TransactionFilters): void {
  Object.assign(form, { type: filters.type ?? '', from: filters.from ?? '', to: filters.to ?? '', per_page: filters.per_page ?? 20 })
}

/** Only the latest request may update the table: a slow earlier response must not win. */
let latestRequest = 0

async function load(): Promise<void> {
  const filters = filtersFromQuery()
  syncForm(filters)
  const request = ++latestRequest
  loading.value = true
  error.value = null
  try {
    const response = await api.transactions(filters)
    if (request === latestRequest) result.value = response
  } catch (caught) {
    if (request === latestRequest) error.value = describeError(caught)
  } finally {
    if (request === latestRequest) loading.value = false
  }
}

function apply(page = 1): void {
  const query: Record<string, string> = {}
  if (form.type) query.type = form.type
  if (form.from) query.from = form.from
  if (form.to) query.to = form.to
  if (form.per_page !== 20) query.per_page = String(form.per_page)
  if (page > 1) query.page = String(page)
  void router.push({ query })
}

function clear(): void {
  Object.assign(form, { type: '', from: '', to: '', per_page: 20 })
  apply()
}

watch(() => route.query, load, { immediate: true })

const field = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none'
</script>

<template>
  <div class="space-y-5">
    <h1 class="text-xl font-semibold tracking-tight">Transactions</h1>

    <form
      class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto]"
      @submit.prevent="apply()"
    >
      <label class="block text-sm font-medium">
        Type
        <select v-model="form.type" :class="field">
          <option value="">All</option>
          <option value="income">Income</option>
          <option value="expense">Expense</option>
        </select>
      </label>
      <label class="block text-sm font-medium">
        From
        <input v-model="form.from" type="date" :class="field" />
      </label>
      <label class="block text-sm font-medium">
        To
        <input v-model="form.to" type="date" :min="form.from || undefined" :class="field" />
      </label>
      <label class="block text-sm font-medium">
        Per page
        <select v-model.number="form.per_page" :class="field">
          <option :value="20">20</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
        </select>
      </label>
      <div class="flex items-end gap-2">
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Apply</button>
        <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" @click="clear">
          Clear
        </button>
      </div>
    </form>

    <p v-if="error" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">{{ error }}</p>

    <TransactionsTable :items="result?.data ?? []" :loading="loading" />
    <PaginationBar v-if="result" :meta="result.meta" label="transactions" @page="apply" />
  </div>
</template>
