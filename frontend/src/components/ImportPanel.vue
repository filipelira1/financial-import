<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { api } from '@/api/endpoints'
import { describeError } from '@/api/errors'
import { useImportTracker } from '@/composables/useImportTracker'
import { locale, t } from '@/i18n'
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

/** The upload limit is 10 per minute, so one drop never sends more than that. */
const MAX_FILES = 10

interface BatchResult {
  name: string
  error: string | null
  duplicateOf: number | null
}

const batch = ref<BatchResult[]>([])
const skipped = ref(0)
const batchProblems = computed(() => batch.value.filter((result) => result.error))

/**
 * Send one or more files. Each becomes its own import, queued one after the other; the panel
 * follows the last one sent and "Recent imports" lists them all.
 */
async function send(list: FileList | File[] | null | undefined): Promise<void> {
  const files = Array.from(list ?? [])
  if (files.length === 0) return
  showFailures.value = false
  downloadError.value = null
  batch.value = []
  skipped.value = Math.max(0, files.length - MAX_FILES)

  const sending = files.slice(0, MAX_FILES)
  const results: BatchResult[] = []

  for (const file of sending) {
    const before = current.value
    await tracker.upload(file)
    if (current.value && current.value !== before && !uploadError.value) emit('started', current.value)
    results.push({ name: file.name, error: uploadError.value, duplicateOf: duplicateOf.value })
  }

  if (sending.length > 1) {
    // Shown once every file has been sent; problems are listed per file, not as a single message.
    batch.value = results
    uploadError.value = null
    duplicateOf.value = null
  }
  if (input.value) input.value.value = ''
}

const downloading = ref(false)
const downloadError = ref<string | null>(null)

async function downloadRejected(): Promise<void> {
  if (!current.value) return
  downloading.value = true
  downloadError.value = null
  try {
    await api.exportImportFailures(current.value.id)
  } catch (caught) {
    downloadError.value = describeError(caught)
  } finally {
    downloading.value = false
  }
}

// The API translates stored reasons: a language switch reloads a finished import being shown
// (a running one is refreshed by the polling within a second anyway).
watch(locale, async () => {
  if (!current.value?.is_finished) return
  try {
    tracker.track(await api.import(current.value.id))
  } catch {
    // Keep what is on screen; the next action will report a real problem.
  }
})

/** Show an existing import here (and follow it if it is still running). */
async function open(id: number): Promise<void> {
  showFailures.value = false
  downloadError.value = null
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
  void send(event.dataTransfer?.files)
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
        multiple
        class="sr-only"
        :disabled="uploading"
        data-test="file-input"
        @change="send(($event.target as HTMLInputElement).files)"
      />
    </label>

    <div aria-live="polite">
      <p v-if="uploadError" class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">
        {{ uploadError }}
        <button v-if="duplicateOf" type="button" class="ml-1 font-medium underline" @click="open(duplicateOf)">
          {{ t('import.showDuplicate', { id: duplicateOf }) }}
        </button>
      </p>

      <div v-if="batch.length" class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-sm" data-test="batch-result">
        <p class="font-medium">{{ t('import.batchSent', { sent: batch.length - batchProblems.length, total: batch.length }) }}</p>
        <ul v-if="batchProblems.length" class="mt-1 space-y-0.5 text-rose-800" role="alert">
          <li v-for="problem in batchProblems" :key="problem.name">
            <span class="font-medium">{{ problem.name }}:</span> {{ problem.error }}
            <button v-if="problem.duplicateOf" type="button" class="ml-1 font-medium underline" @click="open(problem.duplicateOf)">
              {{ t('import.showDuplicate', { id: problem.duplicateOf }) }}
            </button>
          </li>
        </ul>
      </div>
      <p v-if="skipped" class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert">
        {{ t('import.tooManyFiles', { max: MAX_FILES, skipped }) }}
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
        <p v-if="current.failure_reason" class="text-sm text-rose-700">{{ current.failure_reason }}</p>
        <p v-if="connectionLost" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert" data-test="connection-lost">
          {{ t('import.connectionLost') }}
          <button type="button" class="ml-1 font-medium underline" @click="tracker.resume()">{{ t('import.resume') }}</button>
        </p>
        <div v-if="current.is_finished && current.failed_rows > 0" class="flex flex-wrap items-center gap-x-4 gap-y-1">
          <button type="button" class="text-sm font-medium underline" @click="showFailures = !showFailures">
            {{ showFailures ? t('import.hideRejected') : t('import.showRejected') }}
          </button>
          <button type="button" class="text-sm font-medium underline disabled:opacity-50" :disabled="downloading" data-test="download-rejected" @click="downloadRejected">
            {{ downloading ? t('common.downloading') : t('import.downloadRejected') }}
          </button>
        </div>
        <p v-if="downloadError" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">{{ downloadError }}</p>
        <ImportFailuresTable v-if="showFailures" :key="current.id" :import-id="current.id" />
      </div>
    </div>
  </section>
</template>
