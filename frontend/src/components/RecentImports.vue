<script setup lang="ts">
import { t } from '@/i18n'
import { formatDateTime, formatNumber } from '@/utils/format'
import StatusBadge from './StatusBadge.vue'
import type { Import } from '@/types/api'

defineProps<{ items: Import[] }>()
const emit = defineEmits<{ select: [item: Import]; retry: [item: Import] }>()
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="recent-title">
    <h2 id="recent-title" class="px-5 pt-5 text-base font-semibold">{{ t('recent.title') }}</h2>
    <p v-if="items.length === 0" class="px-5 pt-2 pb-5 text-sm text-slate-500">{{ t('recent.empty') }}</p>
    <div v-else class="mt-3 overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="border-y border-slate-200 bg-slate-50 text-xs text-slate-500 uppercase">
          <tr>
            <th scope="col" class="px-5 py-2 font-medium">{{ t('recent.file') }}</th>
            <th scope="col" class="px-3 py-2 font-medium">{{ t('recent.status') }}</th>
            <th scope="col" class="px-3 py-2 text-right font-medium">{{ t('recent.imported') }}</th>
            <th scope="col" class="px-3 py-2 text-right font-medium">{{ t('recent.rejected') }}</th>
            <th scope="col" class="px-3 py-2 font-medium">{{ t('recent.uploaded') }}</th>
            <th scope="col" class="px-5 py-2"><span class="sr-only">{{ t('recent.actions') }}</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="item in items" :key="item.id">
            <td class="max-w-56 truncate px-5 py-2.5">#{{ item.id }} · {{ item.original_name }}</td>
            <td class="px-3 py-2.5"><StatusBadge :status="item.status" /></td>
            <td class="tabular px-3 py-2.5 text-right">{{ formatNumber(item.imported_rows) }}</td>
            <td class="tabular px-3 py-2.5 text-right">{{ formatNumber(item.failed_rows) }}</td>
            <td class="px-3 py-2.5 whitespace-nowrap text-slate-500">{{ formatDateTime(item.created_at) }}</td>
            <td class="px-5 py-2.5 text-right whitespace-nowrap">
              <button type="button" class="font-medium underline" @click="emit('select', item)">{{ t('recent.details') }}</button>
              <button v-if="item.status === 'failed'" type="button" class="ml-3 font-medium text-rose-700 underline" @click="emit('retry', item)">
                {{ t('recent.retry') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
