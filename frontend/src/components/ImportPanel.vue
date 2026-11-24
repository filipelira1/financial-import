<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { api } from '@/api/endpoints'
import { describeError } from '@/api/errors'
import { useImportTracker } from '@/composables/useImportTracker'
import { t } from '@/i18n'
import { translateReason } from '@/i18n/reasons'
import { formatNumber } from '@/utils/format'
import ImportFailuresTable from './ImportFailuresTable.vue'
import ProgressBar from './ProgressBar.vue'
import StatusBadge from './StatusBadge.vue'
import type { Import } from '@/types/api'

const emit = defineEmits<{ finished: [item: Import]; started: [item: Import]; updated: [item: Import] }>()

const tracker = useImportTracker(
  (finished) => emit('finished', finished),
  (item) => emit('updated', item),
)
const { current, uploading, uploadError, duplicateOf, connectionLost } = tracker
const openError = ref<string | null>(null)

const input = ref<HTMLInputElement | null>(null)
const dragging = ref(false)
const showFailures = ref(false)
const statusBox = ref<HTMLElement | null>(null)
const highlight = ref(false)
let highlightTimer: number | undefined

async function send(file: File | undefined): Promise<void> {
  if (!file) return
  showFailures.value = false
  await tracker.upload(file)
  if (current.value) emit('started', current.value)
  if (input.value) input.value.value = ''
}

/** Show an existing import here (and follow it if it is still running). */
async function open(id: number): Promise<void> {
  showFailures.value = false
  openError.value = null
  try {
    const item = await api.import(id)
    tracker.reset()
    tracker.track(item)
    // Rejected lines are what "details" means for a finished import, so open them right away.
    showFailures.value = item.is_finished && item.failed_rows > 0
    await nextTick()
    reveal()
  } catch (caught) {
    openError.value = describeError(caught)
  }
}

/** Bring the status box into view and flash it, so opening an import is visible even when it was already shown. */
function reveal(): void {
  const box = statusBox.value
  if (!box) return
  const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
  box.scrollIntoView?.({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' })
  box.focus({ preventScroll: true })
  // Restart the flash on every click: a timer left by a previous click must not cut this one short.
  window.clearTimeout(highlightTimer)
  highlight.value = false
  requestAnimationFrame(() => {
    highlight.value = true
    highlightTimer = window.setTimeout(() => (highlight.value = false), 1200)
  })
}

function onDrop(event: DragEvent): void {
  dragging.value = false
  void send(event.dataTransfer?.files[0])
}

/** The help sentence with its code and emphasis kept as markup. */
const helpParts = computed(() => t('import.help').split(/(\{\w+\})/))

const tone = computed(() => {
  switch (current.value?.status) {
    case 'completed':
      return 'success'
    case 'completed_with_errors':
      return 'warning'
    case 'failed':
      return 'danger'
    default:
      return 'default'
  }
})

defineExpose({ open })
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="import-title">
    <h2 id="import-title" class="text-base font-semibold">{{ t('import.title') }}</h2>
    <p class="mt-1 text-sm text-slate-500">
      <template v-for="(part, index) in helpParts" :key="index">
        <code v-if="part === '{columns}'" class="rounded bg-slate-100 px-1">date,description,amount,type</code>
        <em v-else-if="part === '{income}'">Receita</em>
        <em v-else-if="part === '{expense}'">Despesa</em>
        <template v-else>{{ part }}</template>
      </template>
    </p>

    <label
      class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-8 text-center transition focus-within:ring-2 focus-within:ring-slate-900 focus-within:ring-offset-2"
      :class="dragging ? 'border-slate-900 bg-slate-50' : 'border-slate-300 hover:border-slate-400'"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <span class="text-sm font-medium">{{ uploading ? t('import.uploading') : t('import.drop') }}</span>
      <span class="mt-1 text-xs text-slate-500">{{ t('import.maxSize') }}</span>
      <input
        ref="input"
        type="file"
        accept=".csv,text/csv"
        class="sr-only"
        :disabled="uploading"
        data-test="file-input"
        @change="send(($event.target as HTMLInputElement).files?.[0])"
      />
    </label>

    <div aria-live="polite">
      <p v-if="uploadError" class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">
        {{ uploadError }}
        <button v-if="duplicateOf" type="button" class="ml-1 font-medium underline" @click="open(duplicateOf)">
          {{ t('import.showDuplicate', { id: duplicateOf }) }}
        </button>
      </p>

      <p v-if="openError" class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">{{ openError }}</p>

      <div
        v-if="current"
        ref="statusBox"
        tabindex="-1"
        class="mt-4 space-y-3 rounded-lg p-4 outline-none transition-colors duration-700"
        :class="highlight ? 'bg-amber-50 ring-2 ring-amber-300' : 'bg-slate-50'"
        data-test="import-status"
      >
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="truncate text-sm font-medium">#{{ current.id }} · {{ current.original_name }}</p>
          <StatusBadge :status="current.status" />
        </div>
        <ProgressBar
          :value="current.progress"
          :indeterminate="current.status === 'pending'"
          :tone="tone"
          :label="t('import.progressLabel', { id: current.id })"
        />
        <p class="tabular text-sm text-slate-600">
          <template v-if="current.total_rows === null">{{ t('import.waiting') }}</template>
          <template v-else>
            {{ t('import.rows', { processed: formatNumber(current.processed_rows), total: formatNumber(current.total_rows) }) }} ·
            <span class="text-emerald-700">{{ t('import.imported', { count: formatNumber(current.imported_rows) }) }}</span> ·
            <span :class="current.failed_rows ? 'text-rose-700' : ''">{{ t('import.rejected', { count: formatNumber(current.failed_rows) }) }}</span>
          </template>
        </p>
        <p v-if="current.failure_reason" class="text-sm text-rose-700">{{ translateReason(current.failure_reason) }}</p>
        <p v-if="connectionLost" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert" data-test="connection-lost">
          {{ t('import.connectionLost') }}
          <button type="button" class="ml-1 font-medium underline" @click="tracker.resume()">{{ t('import.resume') }}</button>
        </p>
        <button
          v-if="current.is_finished && current.failed_rows > 0"
          type="button"
          class="text-sm font-medium underline"
          @click="showFailures = !showFailures"
        >
          {{ showFailures ? t('import.hideRejected') : t('import.showRejected') }}
        </button>
        <ImportFailuresTable v-if="showFailures" :key="current.id" :import-id="current.id" />
      </div>
    </div>
  </section>
</template>
