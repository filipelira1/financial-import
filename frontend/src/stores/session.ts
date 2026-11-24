import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/api/endpoints'
import type { User } from '@/types/api'

/**
 * The token lives in sessionStorage: it survives a reload but not closing the browser,
 * and it is not shared with other tabs. The API also expires it after 8 hours.
 */
const STORAGE_KEY = 'financial-import.session'

interface StoredSession {
  token: string
  user: User
}

function readStoredSession(): StoredSession | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as StoredSession) : null
  } catch {
    return null
  }
}

export const useSessionStore = defineStore('session', () => {
  const stored = readStoredSession()
  const token = ref<string | null>(stored?.token ?? null)
  const user = ref<User | null>(stored?.user ?? null)

  const isAuthenticated = computed(() => token.value !== null)

  async function login(email: string, password: string): Promise<void> {
    const session = await api.login(email, password)
    token.value = session.token
    user.value = session.user
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ token: session.token, user: session.user }))
  }

  async function logout(): Promise<void> {
    try {
      if (token.value) await api.logout()
    } finally {
      clear()
    }
  }

  function clear(): void {
    token.value = null
    user.value = null
    sessionStorage.removeItem(STORAGE_KEY)
  }

  return { token, user, isAuthenticated, login, logout, clear }
})
