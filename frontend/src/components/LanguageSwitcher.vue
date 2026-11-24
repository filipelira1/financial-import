<script setup lang="ts">
import { locale, locales, setLocale, t, type Locale } from '@/i18n'
</script>

<template>
  <label class="inline-flex items-center gap-2 text-sm">
    <span class="sr-only">{{ t('language.label') }}</span>
    <select
      :value="locale"
      class="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm focus:border-slate-900 focus:ring-1 focus:ring-slate-900 focus:outline-none"
      data-test="language"
      @change="setLocale(($event.target as HTMLSelectElement).value as Locale)"
    >
      <option v-for="option in locales" :key="option.code" :value="option.code">{{ option.label }}</option>
    </select>
  </label>
</template>
