import { describe, expect, it } from 'vitest'
import { creditUsagePercent, summarizeWallets } from '../domain'

const base = { is_active: true, is_credit_card: false, balance: 0 }

describe('wallet summary', () => {
  it('adds active balances and credit card debt separately', () => {
    const s = summarizeWallets([
      { ...base, balance: 1000 },
      { ...base, balance: -200 },
      { ...base, balance: 5000, is_active: false },
      {
        ...base,
        is_credit_card: true,
        balance: 0,
        credit_limit: 10000,
        outstanding_balance: 2500,
      },
    ])
    expect(s).toEqual({
      cash: 800,
      creditDebt: 2500,
      creditAvailable: 7500,
      activeCount: 3,
      inactiveCount: 1,
    })
  })

  it('never reports negative available credit', () => {
    expect(
      summarizeWallets([
        {
          ...base,
          is_credit_card: true,
          credit_limit: 100,
          outstanding_balance: 150,
        },
      ]).creditAvailable,
    ).toBe(0)
  })

  it('clamps credit usage to 0–100', () => {
    const card = { ...base, is_credit_card: true }
    expect(creditUsagePercent({ ...card, credit_limit: 0 })).toBe(0)
    expect(
      creditUsagePercent({
        ...card,
        credit_limit: 200,
        outstanding_balance: 50,
      }),
    ).toBe(25)
    expect(
      creditUsagePercent({
        ...card,
        credit_limit: 100,
        outstanding_balance: 500,
      }),
    ).toBe(100)
  })
})
