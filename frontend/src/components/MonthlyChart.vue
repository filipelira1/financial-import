<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@/i18n'
import { formatCents, formatCompactCents, formatMonth } from '@/utils/format'
import type { MonthlyTotals } from '@/types/api'

/**
 * Income and expense per month as grouped bars. Plain HTML and CSS: no chart library to
 * load, and the bars reflow with the card on any screen width.
 *
 * Colours were checked for colour blindness (teal against rose, ΔE ≥ 10 for deutan and protan);
 * the legend, the tooltip and the hidden table carry the same values, so colour is never the only cue.
 */
const props = defineProps<{ months: MonthlyTotals[] | null }>()

const active = ref<number | null>(null)

/** A round top for the scale that splits into four readable steps (e.g. 4 mi -> 1, 2, 3, 4 mi). */
const top = computed(() => {
  const max = Math.max(0, ...(props.months ?? []).flatMap((month) => [month.income_cents, month.expense_cents]))
  if (max === 0) return 1
  const magnitude = 10 ** Math.floor(Math.log10(max))
  const step = [1, 2, 4, 6, 8, 10].find((factor) => factor * magnitude >= max) ?? 10
  return step * magnitude
})

const ticks = computed(() => [1, 0.75, 0.5, 0.25, 0].map((share) => share * top.value))

function height(cents: number): string {
  return `${(cents / top.value) * 100}%`
}

const activeMonth = computed(() => (active.value === null ? null : (props.months?.[active.value] ?? null)))
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="monthly-title">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
      <h2 id="monthly-title" class="text-base font-semibold">{{ t('monthly.title') }}</h2>
      <ul class="flex gap-4 text-sm text-slate-600" :aria-label="t('monthly.legend')">
        <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-[#0d9488]" aria-hidden="true" />{{ t('summary.income') }}</li>
        <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-[#e11d48]" aria-hidden="true" />{{ t('summary.expenses') }}</li>
      </ul>
    </div>

    <p v-if="months === null" class="mt-4 h-56 animate-pulse rounded-lg bg-slate-100" aria-busy="true" />
    <p v-else-if="months.length === 0" class="mt-4 text-sm text-slate-500">{{ t('monthly.empty') }}</p>

    <template v-else>
      <div class="mt-4 flex gap-2" aria-hidden="true">
        <!-- Scale -->
        <div class="tabular relative h-56 w-14 shrink-0 text-right text-xs text-slate-400">
          <span
            v-for="(tick, index) in ticks"
            :key="index"
            class="absolute right-0 -translate-y-1/2"
            :style="{ top: `${index * 25}%` }"
          >{{ formatCompactCents(tick) }}</span>
        </div>

        <div class="relative min-w-0 flex-1">
          <!-- Grid -->
          <div
            v-for="index in 5"
            :key="index"
            class="absolute inset-x-0 border-t"
            :class="index === 5 ? 'border-slate-300' : 'border-slate-100'"
            :style="{ top: `${(index - 1) * 25}%` }"
          />

          <!-- Bars: the whole column is the hover target, wider than the bars themselves -->
          <div class="relative flex h-56 items-end" @mouseleave="active = null">
            <div
              v-for="(month, index) in months"
              :key="month.month"
              class="flex h-full flex-1 items-end justify-center gap-[2px] rounded-t-md px-0.5 sm:px-2"
              :class="active === index ? 'bg-slate-100/70' : ''"
              @mouseenter="active = index"
            >
              <div class="max-w-6 min-w-[3px] flex-1 rounded-t bg-[#0d9488]" :style="{ height: height(month.income_cents) }" />
              <div class="max-w-6 min-w-[3px] flex-1 rounded-t bg-[#e11d48]" :style="{ height: height(month.expense_cents) }" />
            </div>
          </div>

          <!-- Tooltip -->
          <div
            v-if="activeMonth && active !== null"
            class="pointer-events-none absolute top-0 z-10 w-48 rounded-lg border border-slate-200 bg-white p-3 text-xs shadow-lg"
            :style="{
              left: `${((active + 0.5) / months.length) * 100}%`,
              transform: active < months.length / 2 ? 'translateX(8px)' : 'translateX(calc(-100% - 8px))',
            }"
            data-test="monthly-tooltip"
          >
            <p class="mb-1 font-semibold text-slate-900">{{ formatMonth(activeMonth.month, 'long') }}</p>
            <p class="flex justify-between gap-2 text-slate-600">
              <span class="flex items-center gap-1"><span class="size-2 rounded-sm bg-[#0d9488]" />{{ t('summary.income') }}</span>
              <span class="tabular text-slate-900">{{ formatCents(activeMonth.income_cents) }}</span>
            </p>
            <p class="flex justify-between gap-2 text-slate-600">
              <span class="flex items-center gap-1"><span class="size-2 rounded-sm bg-[#e11d48]" />{{ t('summary.expenses') }}</span>
              <span class="tabular text-slate-900">{{ formatCents(activeMonth.expense_cents) }}</span>
            </p>
            <p class="mt-1 flex justify-between gap-2 border-t border-slate-100 pt-1 font-medium text-slate-700">
              <span>{{ t('summary.balance') }}</span>
              <span class="tabular text-slate-900">{{ formatCents(activeMonth.balance_cents) }}</span>
            </p>
          </div>

          <!-- Month labels -->
          <div class="mt-2 flex">
            <span v-for="(month, index) in months" :key="month.month" class="relative h-4 min-w-0 flex-1">
              <span
              class="absolute left-1/2 -translate-x-1/2 text-xs whitespace-nowrap"
              :class="[
                active === index ? 'font-medium text-slate-900' : 'text-slate-500',
                // On a phone there is room for every third label only.
                index % 3 !== 0 ? 'max-sm:invisible' : '',
              ]"
            >{{ formatMonth(month.month, 'short') }}</span>
            </span>
          </div>
        </div>
      </div>

      <!-- The same numbers for screen readers -->
      <table class="sr-only">
        <caption>{{ t('monthly.title') }}</caption>
        <thead>
          <tr>
            <th scope="col">{{ t('monthly.month') }}</th>
            <th scope="col">{{ t('summary.income') }}</th>
            <th scope="col">{{ t('summary.expenses') }}</th>
            <th scope="col">{{ t('summary.balance') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="month in months" :key="month.month">
            <th scope="row">{{ formatMonth(month.month, 'long') }}</th>
            <td>{{ formatCents(month.income_cents) }}</td>
            <td>{{ formatCents(month.expense_cents) }}</td>
            <td>{{ formatCents(month.balance_cents) }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </section>
</template>
