import { afterEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/api/http'
import { formatCents, formatDate } from '@/utils/format'
import { locale, setLocale, t } from './index'
import { en, pt } from './messages'
import { translateReason } from './reasons'

const plain = (text: string) => text.replace(/\s/g, ' ')

describe('i18n', () => {
  afterEach(() => {
    setLocale('pt-BR')
    vi.unstubAllGlobals()
  })

  it('starts in Brazilian Portuguese', () => {
    expect(locale.value).toBe('pt-BR')
    expect(t('summary.balance')).toBe('Saldo')
    expect(t('import.rows', { processed: '5', total: '10' })).toBe('5 de 10 linhas')
  })

  it('has the same keys in both languages', () => {
    expect(Object.keys(pt).sort()).toEqual(Object.keys(en).sort())
  })

  it('switches to English, remembers the choice and sets the page language', () => {
    setLocale('en')
    expect(t('summary.balance')).toBe('Balance')
    expect(localStorage.getItem('locale')).toBe('en')
    expect(document.documentElement.lang).toBe('en')
  })

  it('keeps reais in both languages and only changes the separators', () => {
    expect(plain(formatCents(941006400))).toBe('R$ 9.410.064,00')
    expect(formatDate('2026-01-31')).toBe('31/01/2026')
    setLocale('en')
    expect(plain(formatCents(941006400))).toBe('R$9,410,064.00')
    expect(formatDate('2026-01-31')).toBe('01/31/2026')
  })

  it('asks the API for messages in the chosen language', async () => {
    const fetch = vi.fn().mockResolvedValue(new Response('{}', { status: 200 }))
    vi.stubGlobal('fetch', fetch)
    await http('/auth/me')
    setLocale('en')
    await http('/auth/me')
    expect(fetch.mock.calls.map(([, init]) => (init as RequestInit).headers)).toMatchObject([
      { 'Accept-Language': 'pt-BR' },
      { 'Accept-Language': 'en' },
    ])
  })

  it('translates the reasons stored by the queue and leaves unknown ones as they are', () => {
    expect(translateReason('amount: expected a positive integer in cents, got "-500"')).toBe(
      'valor: esperado um inteiro positivo em centavos, veio "-500"',
    )
    expect(translateReason('row: expected 4 columns, found 2')).toBe('linha: esperadas 4 colunas, encontradas 2')
    expect(translateReason('Row count mismatch: 10 imported + 2 rejected, but the file has 13 rows.')).toBe(
      'Contagem não confere: 10 importadas + 2 recusadas, mas o arquivo tem 13 linhas.',
    )
    expect(translateReason('Something new.')).toBe('Something new.')
    setLocale('en')
    expect(translateReason('row: expected 4 columns, found 2')).toBe('row: expected 4 columns, found 2')
  })
})
