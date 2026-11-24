import { ref } from 'vue'
import { en, pt, type Messages } from './messages'

/**
 * A small typed translator: two languages, no runtime dependency.
 * Portuguese is the default; the choice is remembered in localStorage.
 */

export type Locale = 'pt-BR' | 'en'

export const locales: { code: Locale; label: string }[] = [
  { code: 'pt-BR', label: 'Português' },
  { code: 'en', label: 'English' },
]

const dictionaries: Record<Locale, Messages> = { 'pt-BR': pt, en }
const storageKey = 'locale'

export const locale = ref<Locale>(readStoredLocale())

export function setLocale(next: Locale): void {
  locale.value = next
  document.documentElement.lang = next
  try {
    localStorage.setItem(storageKey, next)
  } catch {
    // Storage blocked (private mode): the choice lasts until the page closes.
  }
}

type Params<K extends keyof Messages> = Messages[K] extends (params: infer P) => string ? [params: P] : []

export function t<K extends keyof Messages>(key: K, ...params: Params<K>): string {
  const message = dictionaries[locale.value][key] as string | ((params: unknown) => string)
  return typeof message === 'function' ? message(params[0]) : message
}

/** Intl locale for numbers, currency and dates. */
export function intlLocale(): string {
  return locale.value === 'en' ? 'en-US' : 'pt-BR'
}

function readStoredLocale(): Locale {
  try {
    const stored = localStorage.getItem(storageKey)
    if (stored === 'en' || stored === 'pt-BR') return stored
  } catch {
    // Storage blocked: fall back to the default.
  }
  return 'pt-BR'
}

if (typeof document !== 'undefined') document.documentElement.lang = locale.value
