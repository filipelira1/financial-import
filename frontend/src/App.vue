<script setup lang="ts">
import AppHeader from './components/AppHeader.vue'
import { useSessionStore } from './stores/session'

const session = useSessionStore()
</script>

<template>
  <AppHeader v-if="session.isAuthenticated" />
  <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
    <RouterView />
  </main>
</template>
