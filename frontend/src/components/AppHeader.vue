<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const router = useRouter()

async function logout(): Promise<void> {
  await session.logout()
  await router.push({ name: 'login' })
}

const link = 'rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900'
const active = '!bg-slate-900 !text-white'
</script>

<template>
  <header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 sm:px-6">
      <RouterLink :to="{ name: 'dashboard' }" class="text-base font-semibold tracking-tight">Financial Import</RouterLink>
      <nav class="flex gap-1" aria-label="Main">
        <RouterLink :to="{ name: 'dashboard' }" :class="link" :exact-active-class="active">Dashboard</RouterLink>
        <RouterLink :to="{ name: 'transactions' }" :class="link" :active-class="active">Transactions</RouterLink>
      </nav>
      <div class="ml-auto flex items-center gap-3 text-sm">
        <span class="hidden text-slate-500 sm:inline">{{ session.user?.name }}</span>
        <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50" @click="logout">
          Sign out
        </button>
      </div>
    </div>
  </header>
</template>
