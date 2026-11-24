<script setup lang="ts">
defineProps<{ value: number; indeterminate?: boolean; tone?: 'default' | 'success' | 'warning' | 'danger' }>()

const tones = {
  default: 'bg-slate-900',
  success: 'bg-emerald-600',
  warning: 'bg-amber-500',
  danger: 'bg-rose-600',
}
</script>

<template>
  <div
    class="h-2.5 w-full overflow-hidden rounded-full bg-slate-200"
    role="progressbar"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-valuenow="indeterminate ? undefined : value"
  >
    <div
      class="h-full rounded-full transition-[width] duration-500 ease-out"
      :class="[tones[tone ?? 'default'], indeterminate ? 'w-1/3 animate-pulse' : '']"
      :style="indeterminate ? undefined : { width: `${Math.max(2, value)}%` }"
    />
  </div>
</template>
