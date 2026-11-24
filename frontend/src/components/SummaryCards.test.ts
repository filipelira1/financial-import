import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SummaryCards from './SummaryCards.vue'

describe('SummaryCards', () => {
  it('shows income, expenses and balance from cents', () => {
    const wrapper = mount(SummaryCards, {
      props: {
        summary: { currency: 'BRL', income_cents: 3611960974, expense_cents: 2670954574, balance_cents: 941006400, transaction_count: 15000 },
      },
    })

    const text = (selector: string) => wrapper.get(selector).text().replace(/\s/g, ' ')
    expect(text('[data-test=income]')).toBe('R$ 36.119.609,74')
    expect(text('[data-test=expense]')).toBe('R$ 26.709.545,74')
    expect(text('[data-test=balance]')).toBe('R$ 9.410.064,00')
  })

  it('shows placeholders while loading', () => {
    expect(mount(SummaryCards, { props: { summary: null } }).get('[data-test=balance]').text()).toBe('—')
  })
})
