import { describe, expect, it } from 'vitest'
import { formatCents, formatDate } from './format'

// Intl separates "R$" and the number with a non-breaking space.
const plain = (text: string) => text.replace(/\s/g, ' ')

describe('formatCents', () => {
  it('formats integer cents as Brazilian reais', () => {
    expect(plain(formatCents(115346))).toBe('R$ 1.153,46')
    expect(plain(formatCents(941006400))).toBe('R$ 9.410.064,00')
    expect(plain(formatCents(5))).toBe('R$ 0,05')
  })

  it('keeps the sign of a negative balance', () => {
    expect(plain(formatCents(-80000))).toBe('-R$ 800,00')
  })
})

describe('formatDate', () => {
  it('reorders an ISO date without shifting the day across timezones', () => {
    expect(formatDate('2026-01-01')).toBe('01/01/2026')
    expect(formatDate('2025-12-31')).toBe('31/12/2025')
  })
})
