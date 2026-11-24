<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/api/endpoints'
import PaginationBar from './PaginationBar.vue'
import type { ImportFailure, Paginated } from '@/types/api'

const props = defineProps<{ importId: number }>()

const page = ref<Paginated<ImportFailure> | null>(null)
const loading = ref(false)

async function load(number = 1): Promise<void> {
  loading.value = true
  try {
    page.value = await api.importFailures(props.importId, number)
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-3">
    <div class="overflow-x-auto rounded-lg border border-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
          <tr>
            <th scope="col" class="px-3 py-2 font-medium">Line</th>
            <th scope="col" class="px-3 py-2 font-medium">Content</th>
            <th scope="col" class="px-3 py-2 font-medium">Why it was rejected</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100" :class="{ 'opacity-50': loading }">
          <tr v-for="failure in page?.data ?? []" :key="failure.line_number" class="align-top">
            <td class="tabular px-3 py-2 font-medium">{{ failure.line_number }}</td>
            <td class="max-w-xs px-3 py-2 font-mono text-xs break-all text-slate-600">{{ failure.raw || '(empty)' }}</td>
            <td class="px-3 py-2">
              <ul class="space-y-0.5 text-rose-700">
                <li v-for="reason in failure.errors" :key="reason">{{ reason }}</li>
              </ul>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <PaginationBar v-if="page" :meta="page.meta" label="rejected lines" @page="load" />
  </div>
</template>
