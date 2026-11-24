<script setup lang="ts">
import type { ImportStatus } from '@/types/api'

defineProps<{ status: ImportStatus }>()

const styles: Record<ImportStatus, { label: string; class: string }> = {
  pending: { label: 'Queued', class: 'bg-slate-100 text-slate-700' },
  processing: { label: 'Processing', class: 'bg-sky-100 text-sky-800' },
  completed: { label: 'Completed', class: 'bg-emerald-100 text-emerald-800' },
  completed_with_errors: { label: 'Completed with errors', class: 'bg-amber-100 text-amber-800' },
  failed: { label: 'Failed', class: 'bg-rose-100 text-rose-800' },
}
</script>

<template>
  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap" :class="styles[status].class">
    {{ styles[status].label }}
  </span>
</template>
