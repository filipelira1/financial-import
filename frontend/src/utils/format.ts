const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const integer = new Intl.NumberFormat('pt-BR')

/** 115346 -> "R$ 1.153,46". Cents stay integers until the very last step. */
export function formatCents(cents: number): string {
  return currency.format(cents / 100)
}

export function formatNumber(value: number): string {
  return integer.format(value)
}

/** "2026-01-31" -> "31/01/2026", without going through Date (no timezone shift). */
export function formatDate(isoDate: string): string {
  const [year, month, day] = isoDate.slice(0, 10).split('-')
  return `${day}/${month}/${year}`
}

/** ISO timestamp -> "24/11/2026 14:05" in the browser's timezone. */
export function formatDateTime(iso: string | null): string {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso))
}
