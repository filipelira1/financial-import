<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/api/endpoints'
import { describeError } from '@/api/errors'
import ImportPanel from '@/components/ImportPanel.vue'
import MonthlyChart from '@/components/MonthlyChart.vue'
import RecentImports from '@/components/RecentImports.vue'
import SummaryCards from '@/components/SummaryCards.vue'
import { usePolling } from '@/composables/usePolling'
import { t } from '@/i18n'
import type { DashboardSummary, Import, MonthlyTotals } from '@/types/api'

const summary = ref<DashboardSummary | null>(null)
const monthly = ref<MonthlyTotals[] | null>(null)
const imports = ref<Import[]>([])
const panel = ref<InstanceType<typeof ImportPanel> | null>(null)
const error = ref<string | null>(null)

async function refresh(): Promise<void> {
  try {
    const [totals, months, page] = await Promise.all([api.summary(), api.monthly(), api.imports()])
    summary.value = totals
    monthly.value = months
    imports.value = page.data
    error.value = null
    if (page.data.some((item) => !item.is_finished)) listPolling.start()
  } catch (caught) {
    error.value = describeError(caught)
  }
}

/**
 * Several files may be queued at once while the panel follows only one: keep the list (and the
 * totals, once everything is done) up to date until every import in it has finished.
 */
const listPolling = usePolling(() => api.imports(), {
  isDone: (page) => page.data.every((item) => item.is_finished),
  onResult: (page) => {
    imports.value = page.data
    if (page.data.every((item) => item.is_finished)) void refresh()
  },
})

/** Keep the "Recent imports" row in step with the progress shown above it. */
function replaceImport(item: Import): void {
  imports.value = imports.value.map((existing) => (existing.id === item.id ? item : existing))
}

async function retry(item: Import): Promise<void> {
  try {
    await api.retryImport(item.id)
    await panel.value?.open(item.id)
    await refresh()
  } catch (caught) {
    error.value = describeError(caught)
  }
}

onMounted(async () => {
  await refresh()
  // Pick up an import that is still running (e.g. after a page reload).
  const running = imports.value.find((item) => !item.is_finished)
  if (running) await panel.value?.open(running.id)
})
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-xl font-semibold tracking-tight">{{ t('dashboard.title') }}</h1>
    <p v-if="error" class="flex flex-wrap items-center gap-2 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">
      {{ error }}
      <button type="button" class="font-medium underline" @click="refresh">{{ t('errors.tryAgain') }}</button>
    </p>
    <SummaryCards :summary="summary" />
    <MonthlyChart :months="monthly" />
    <ImportPanel ref="panel" @started="refresh" @updated="replaceImport" @finished="refresh" />
    <RecentImports :items="imports" @select="(item) => panel?.open(item.id)" @retry="retry" />
  </div>
</template>
