<script setup lang="ts">
import { formatCents, formatNumber } from '@/utils/format'
import type { DashboardSummary } from '@/types/api'

defineProps<{ summary: DashboardSummary | null }>()
</script>

<template>
  <section class="grid gap-4 sm:grid-cols-3" aria-label="Summary">
    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="text-sm font-medium text-slate-500">Income</h2>
      <p class="tabular mt-2 text-2xl font-semibold text-emerald-700" data-test="income">
        {{ summary ? formatCents(summary.income_cents) : '—' }}
      </p>
    </article>
    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="text-sm font-medium text-slate-500">Expenses</h2>
      <p class="tabular mt-2 text-2xl font-semibold text-rose-700" data-test="expense">
        {{ summary ? formatCents(summary.expense_cents) : '—' }}
      </p>
    </article>
    <article class="rounded-xl border border-slate-900 bg-slate-900 p-5 text-white shadow-sm">
      <h2 class="text-sm font-medium text-slate-300">Balance</h2>
      <p class="tabular mt-2 text-2xl font-semibold" data-test="balance">
        {{ summary ? formatCents(summary.balance_cents) : '—' }}
      </p>
      <p v-if="summary" class="mt-1 text-xs text-slate-400">{{ formatNumber(summary.transaction_count) }} transactions</p>
    </article>
  </section>
</template>
