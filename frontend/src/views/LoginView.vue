<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import { t } from '@/i18n'
import { useSessionStore } from '@/stores/session'

const session = useSessionStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const submitting = ref(false)
const error = ref<string | null>(route.query.expired ? t('login.expired') : null)

async function submit(): Promise<void> {
  submitting.value = true
  error.value = null
  try {
    await session.login(email.value, password.value)
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/'
    await router.replace(redirect)
  } catch (caught) {
    error.value =
      caught instanceof ApiError
        ? caught.status === 429
          ? t('login.throttled')
          : (caught.fieldError('email') ?? caught.message)
        : t('errors.network')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto mt-12 max-w-sm sm:mt-24">
    <div class="flex items-start justify-between gap-4">
      <h1 class="text-2xl font-semibold tracking-tight">{{ t('app.name') }}</h1>
      <LanguageSwitcher />
    </div>
    <p class="mt-1 text-sm text-slate-500">{{ t('login.subtitle') }}</p>

    <form class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
      <div>
        <label for="email" class="block text-sm font-medium">{{ t('login.email') }}</label>
        <input
          id="email"
          v-model="email"
          type="email"
          autocomplete="username"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none"
        />
      </div>
      <div>
        <label for="password" class="block text-sm font-medium">{{ t('login.password') }}</label>
        <input
          id="password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none"
        />
      </div>
      <p v-if="error" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">{{ error }}</p>
      <button
        type="submit"
        :disabled="submitting"
        class="w-full rounded-md bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-800 disabled:opacity-60"
      >
        {{ submitting ? t('login.submitting') : t('login.submit') }}
      </button>
    </form>
    <p class="mt-4 text-center text-xs text-slate-500">{{ t('login.demo') }}</p>
  </div>
</template>
