import { intlLocale } from '@/i18n'

// Amounts are always Brazilian reais; only the separators follow the chosen language.

/** 115346 -> "R$ 1.153,46". Cents stay integers until the very last step. */
export function formatCents(cents: number): string {
  return new Intl.NumberFormat(intlLocale(), { style: 'currency', currency: 'BRL' }).format(cents / 100)
}

export function formatNumber(value: number): string {
  return new Intl.NumberFormat(intlLocale()).format(value)
}

/** "2026-01-31" -> "31/01/2026" (or "01/31/2026" in English), without going through Date (no timezone shift). */
export function formatDate(isoDate: string): string {
  const [year, month, day] = isoDate.slice(0, 10).split('-')
  return intlLocale() === 'en-US' ? `${month}/${day}/${year}` : `${day}/${month}/${year}`
}

/** ISO timestamp -> "24/11/2026 14:05" in the browser's timezone. */
export function formatDateTime(iso: string | null): string {
  if (!iso) return '—'
  return new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso))
}

/** 312345600 -> "R$ 3,1 mi" (or "R$3.1M"): short labels for a chart scale. */
export function formatCompactCents(cents: number): string {
  return new Intl.NumberFormat(intlLocale(), {
    style: 'currency',
    currency: 'BRL',
    notation: 'compact',
    minimumFractionDigits: 0,
    maximumFractionDigits: 1,
  }).format(cents / 100)
}

/**
 * "2026-01" -> "jan/26" (short) or "janeiro de 2026" (long). Built from the parts with UTC,
 * so the month never shifts with the browser's timezone.
 */
export function formatMonth(yearMonth: string, style: 'short' | 'long'): string {
  const [year, month] = yearMonth.split('-').map(Number)
  const date = new Date(Date.UTC(year!, month! - 1, 1))
  if (style === 'long') {
    return new Intl.DateTimeFormat(intlLocale(), { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(date)
  }
  const name = new Intl.DateTimeFormat(intlLocale(), { month: 'short', timeZone: 'UTC' }).format(date).replace('.', '')
  return `${name}/${String(year).slice(2)}`
}
