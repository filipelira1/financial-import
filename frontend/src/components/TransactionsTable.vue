<script setup lang="ts">
import { formatCents, formatDate } from '@/utils/format'
import type { Transaction } from '@/types/api'

defineProps<{ items: Transaction[]; loading?: boolean }>()
</script>

<template>
  <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm" :aria-busy="loading">
    <table class="w-full text-left text-sm">
      <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 uppercase">
        <tr>
          <th scope="col" class="px-4 py-2.5 font-medium">Date</th>
          <th scope="col" class="px-4 py-2.5 font-medium">Description</th>
          <th scope="col" class="px-4 py-2.5 font-medium">Type</th>
          <th scope="col" class="px-4 py-2.5 text-right font-medium">Amount</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100" :class="{ 'opacity-50': loading }">
        <tr v-for="transaction in items" :key="transaction.id">
          <td class="tabular px-4 py-2.5 whitespace-nowrap text-slate-600">{{ formatDate(transaction.occurred_on) }}</td>
          <td class="px-4 py-2.5">{{ transaction.description }}</td>
          <td class="px-4 py-2.5">
            <span
              class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
              :class="transaction.type === 'income' ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'"
            >
              {{ transaction.type === 'income' ? 'Income' : 'Expense' }}
            </span>
          </td>
          <td
            class="tabular px-4 py-2.5 text-right font-medium whitespace-nowrap"
            :class="transaction.type === 'income' ? 'text-emerald-700' : 'text-rose-700'"
          >
            {{ transaction.type === 'expense' ? '−' : '' }}{{ formatCents(transaction.amount_cents) }}
          </td>
        </tr>
        <tr v-if="!loading && items.length === 0">
          <td colspan="4" class="px-4 py-10 text-center text-slate-500">No transactions match these filters.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
