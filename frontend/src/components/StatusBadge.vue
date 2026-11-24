<script setup lang="ts">
import { t } from '@/i18n'
import type { ImportStatus } from '@/types/api'

defineProps<{ status: ImportStatus }>()

const styles: Record<ImportStatus, string> = {
  pending: 'bg-slate-100 text-slate-700',
  processing: 'bg-sky-100 text-sky-800',
  completed: 'bg-emerald-100 text-emerald-800',
  completed_with_errors: 'bg-amber-100 text-amber-800',
  failed: 'bg-rose-100 text-rose-800',
}
</script>

<template>
  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap" :class="styles[status]">
    {{ t(`status.${status}`) }}
  </span>
</template>
