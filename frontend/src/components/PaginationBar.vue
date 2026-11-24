<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@/i18n'
import { formatNumber } from '@/utils/format'
import type { Paginated } from '@/types/api'

const props = defineProps<{ meta: Paginated<unknown>['meta']; label?: string }>()
const emit = defineEmits<{ page: [page: number] }>()

/** First, last, and two pages around the current one, with gaps. */
const pages = computed<(number | null)[]>(() => {
  const { current_page: current, last_page: last } = props.meta
  const wanted = new Set([1, last, current - 2, current - 1, current, current + 1, current + 2])
  const sorted = [...wanted].filter((page) => page >= 1 && page <= last).sort((a, b) => a - b)
  return sorted.flatMap((page, index) => (index > 0 && page - sorted[index - 1]! > 1 ? [null, page] : [page]))
})

const button = 'min-w-9 rounded-md border border-slate-300 px-2.5 py-1.5 text-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40'
</script>

<template>
  <nav class="flex flex-wrap items-center justify-between gap-3 text-sm" :aria-label="t('pagination.label')">
    <p class="text-slate-500">
      <template v-if="meta.total > 0">
        {{
          t('pagination.range', {
            from: formatNumber(meta.from ?? 0),
            to: formatNumber(meta.to ?? 0),
            total: formatNumber(meta.total),
            label: label ?? t('pagination.items'),
          })
        }}
      </template>
      <template v-else>{{ t('pagination.none', { label: label ?? t('pagination.items') }) }}</template>
    </p>
    <div v-if="meta.last_page > 1" class="flex flex-wrap items-center gap-1">
      <button type="button" :class="button" :disabled="meta.current_page === 1" @click="emit('page', meta.current_page - 1)">
        {{ t('pagination.previous') }}
      </button>
      <template v-for="(page, index) in pages" :key="index">
        <span v-if="page === null" class="px-1 text-slate-400">…</span>
        <button
          v-else
          type="button"
          :class="[button, page === meta.current_page ? 'border-slate-900 bg-slate-900 text-white hover:bg-slate-900' : '']"
          :aria-current="page === meta.current_page ? 'page' : undefined"
          @click="emit('page', page)"
        >
          {{ page }}
        </button>
      </template>
      <button
        type="button"
        :class="button"
        :disabled="meta.current_page === meta.last_page"
        @click="emit('page', meta.current_page + 1)"
      >
        {{ t('pagination.next') }}
      </button>
    </div>
  </nav>
</template>
