import { describe, it, expect } from 'vitest'
import { route } from '../application/routes'
describe('Named source routes', () => {
  it('preserves URLs and query filters', () => {
    expect(route('wallets.update', 5)).toBe('/wallets/5')
    expect(route('loans.settle', { loan: 12 })).toBe('/loans/12/settle')
    expect(
      route('transactions.create', { wallet_id: 2, type: 'expense' }),
    ).toBe('/transactions/create?wallet_id=2&type=expense')
    expect(route('debt-goals.show')).toBe('/debt-goals/detail')
  })
  it('rejects missing identifiers', () => {
    expect(() => route('wallets.edit')).toThrow()
  })
})
