<script setup lang="ts">
import { useRouter } from 'vue-router'
import { t } from '@/i18n'
import { useSessionStore } from '@/stores/session'
import LanguageSwitcher from './LanguageSwitcher.vue'

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
      <RouterLink :to="{ name: 'dashboard' }" class="text-base font-semibold tracking-tight">{{ t('app.name') }}</RouterLink>
      <nav class="flex gap-1" :aria-label="t('nav.main')">
        <RouterLink :to="{ name: 'dashboard' }" :class="link" :exact-active-class="active">{{ t('nav.dashboard') }}</RouterLink>
        <RouterLink :to="{ name: 'transactions' }" :class="link" :active-class="active">{{ t('nav.transactions') }}</RouterLink>
      </nav>
      <div class="ml-auto flex items-center gap-3 text-sm">
        <span class="hidden text-slate-500 sm:inline">{{ session.user?.name }}</span>
        <LanguageSwitcher />
        <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50" @click="logout">
          {{ t('nav.signOut') }}
        </button>
      </div>
    </div>
  </header>
</template>
