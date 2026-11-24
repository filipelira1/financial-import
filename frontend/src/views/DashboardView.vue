<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/api/endpoints'
import { describeError } from '@/api/errors'
import ImportPanel from '@/components/ImportPanel.vue'
import RecentImports from '@/components/RecentImports.vue'
import SummaryCards from '@/components/SummaryCards.vue'
import type { DashboardSummary, Import } from '@/types/api'

const summary = ref<DashboardSummary | null>(null)
const imports = ref<Import[]>([])
const panel = ref<InstanceType<typeof ImportPanel> | null>(null)
const error = ref<string | null>(null)

async function refresh(): Promise<void> {
  try {
    const [totals, page] = await Promise.all([api.summary(), api.imports()])
    summary.value = totals
    imports.value = page.data
    error.value = null
  } catch (caught) {
    error.value = describeError(caught)
  }
}

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
    <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
    <p v-if="error" class="flex flex-wrap items-center gap-2 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">
      {{ error }}
      <button type="button" class="font-medium underline" @click="refresh">Try again</button>
    </p>
    <SummaryCards :summary="summary" />
    <ImportPanel ref="panel" @started="refresh" @updated="replaceImport" @finished="refresh" />
    <RecentImports :items="imports" @select="(item) => panel?.open(item.id)" @retry="retry" />
  </div>
</template>
