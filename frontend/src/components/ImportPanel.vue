<script setup lang="ts">
import { computed, ref } from 'vue'
import { api } from '@/api/endpoints'
import { describeError } from '@/api/errors'
import { useImportTracker } from '@/composables/useImportTracker'
import { formatNumber } from '@/utils/format'
import ImportFailuresTable from './ImportFailuresTable.vue'
import ProgressBar from './ProgressBar.vue'
import StatusBadge from './StatusBadge.vue'
import type { Import } from '@/types/api'

const emit = defineEmits<{ finished: [item: Import]; started: [item: Import] }>()

const tracker = useImportTracker((finished) => emit('finished', finished))
const { current, uploading, uploadError, duplicateOf, connectionLost } = tracker
const openError = ref<string | null>(null)

const input = ref<HTMLInputElement | null>(null)
const dragging = ref(false)
const showFailures = ref(false)

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
  } catch (caught) {
    openError.value = describeError(caught)
  }
}

function onDrop(event: DragEvent): void {
  dragging.value = false
  void send(event.dataTransfer?.files[0])
}

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
    <h2 id="import-title" class="text-base font-semibold">Import transactions</h2>
    <p class="mt-1 text-sm text-slate-500">
      CSV with the columns <code class="rounded bg-slate-100 px-1">date,description,amount,type</code>. Amounts in cents, type
      <em>Receita</em> or <em>Despesa</em>. Processing runs in the background.
    </p>

    <label
      class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-8 text-center transition focus-within:ring-2 focus-within:ring-slate-900 focus-within:ring-offset-2"
      :class="dragging ? 'border-slate-900 bg-slate-50' : 'border-slate-300 hover:border-slate-400'"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <span class="text-sm font-medium">{{ uploading ? 'Uploading…' : 'Drop a CSV file here or click to choose' }}</span>
      <span class="mt-1 text-xs text-slate-500">Up to 20 MB</span>
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
          Show import #{{ duplicateOf }}
        </button>
      </p>

      <p v-if="openError" class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">{{ openError }}</p>

      <div v-if="current" class="mt-4 space-y-3 rounded-lg bg-slate-50 p-4" data-test="import-status">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="truncate text-sm font-medium">#{{ current.id }} · {{ current.original_name }}</p>
          <StatusBadge :status="current.status" />
        </div>
        <ProgressBar
          :value="current.progress"
          :indeterminate="current.status === 'pending'"
          :tone="tone"
          :label="`Import #${current.id} progress`"
        />
        <p class="tabular text-sm text-slate-600">
          <template v-if="current.total_rows === null">Waiting for a worker…</template>
          <template v-else>
            {{ formatNumber(current.processed_rows) }} of {{ formatNumber(current.total_rows) }} rows ·
            <span class="text-emerald-700">{{ formatNumber(current.imported_rows) }} imported</span> ·
            <span :class="current.failed_rows ? 'text-rose-700' : ''">{{ formatNumber(current.failed_rows) }} rejected</span>
          </template>
        </p>
        <p v-if="current.failure_reason" class="text-sm text-rose-700">{{ current.failure_reason }}</p>
        <p v-if="connectionLost" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert" data-test="connection-lost">
          Lost connection to the server, so this progress may be out of date. The import keeps running in the background.
          <button type="button" class="ml-1 font-medium underline" @click="tracker.resume()">Resume</button>
        </p>
        <button
          v-if="current.is_finished && current.failed_rows > 0"
          type="button"
          class="text-sm font-medium underline"
          @click="showFailures = !showFailures"
        >
          {{ showFailures ? 'Hide' : 'Show' }} rejected lines
        </button>
        <ImportFailuresTable v-if="showFailures" :key="current.id" :import-id="current.id" />
      </div>
    </div>
  </section>
</template>
