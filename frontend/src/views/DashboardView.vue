<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/api/endpoints'
import ImportPanel from '@/components/ImportPanel.vue'
import RecentImports from '@/components/RecentImports.vue'
import SummaryCards from '@/components/SummaryCards.vue'
import type { DashboardSummary, Import } from '@/types/api'

const summary = ref<DashboardSummary | null>(null)
const imports = ref<Import[]>([])
const panel = ref<InstanceType<typeof ImportPanel> | null>(null)

async function loadSummary(): Promise<void> {
  summary.value = await api.summary()
}

async function loadImports(): Promise<void> {
  imports.value = (await api.imports()).data
}

async function onFinished(): Promise<void> {
  await Promise.all([loadSummary(), loadImports()])
}

async function retry(item: Import): Promise<void> {
  await api.retryImport(item.id)
  await panel.value?.open(item.id)
  await loadImports()
}

onMounted(async () => {
  await Promise.all([loadSummary(), loadImports()])
  // Pick up an import that is still running (e.g. after a page reload).
  const running = imports.value.find((item) => !item.is_finished)
  if (running) await panel.value?.open(running.id)
})
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
    <SummaryCards :summary="summary" />
    <ImportPanel ref="panel" @started="loadImports" @finished="onFinished" />
    <RecentImports :items="imports" @select="(item) => panel?.open(item.id)" @retry="retry" />
  </div>
</template>
