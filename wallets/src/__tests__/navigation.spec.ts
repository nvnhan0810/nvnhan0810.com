import { beforeEach, describe, it, expect, vi } from 'vitest'
const mocks = vi.hoisted(() => ({
  action: vi.fn(),
  request: vi.fn(),
  push: vi.fn(),
  replace: vi.fn(),
}))
vi.mock('../app/router', () => ({
  vueRouter: {
    currentRoute: { value: { fullPath: '/loans/create' } },
    push: mocks.push,
    replace: mocks.replace,
  },
}))
vi.mock('../app/services', () => ({
  session: { state: { user: null }, logout: vi.fn() },
}))
vi.mock('../infrastructure/wallets-api', async () => {
  const actual = await vi.importActual<
    typeof import('../infrastructure/wallets-api')
  >('../infrastructure/wallets-api')
  return { ...actual, action: mocks.action, request: mocks.request }
})
import { page, useForm } from '../application/navigation'
import { WalletsError } from '../infrastructure/wallets-api'
beforeEach(() => {
  vi.clearAllMocks()
  page.component = 'Loans/Create'
  page.props.errors = {}
})
describe('JSON forms', () => {
  it('renders preview responses without navigating away', async () => {
    mocks.action.mockResolvedValue({
      component: 'Loans/Preview',
      props: { loan: { name: 'Bank' }, schedule: [] },
    })
    const form = useForm({ name: 'Bank', principal_amount: 1000 })
    await form.post('/loans/preview')
    expect(mocks.action).toHaveBeenCalledWith('/loans/preview', 'POST', {
      name: 'Bank',
      principal_amount: 1000,
    })
    expect(page.component).toBe('Loans/Preview')
    expect(form.processing).toBe(false)
    expect(mocks.push).not.toHaveBeenCalled()
  })
  it('keeps validation errors on the form and page', async () => {
    mocks.action.mockRejectedValue(
      new WalletsError('Invalid', { amount: 'Phải lớn hơn 0' }),
    )
    const success = vi.fn(),
      form = useForm({ amount: 0 })
    await form.post('/transactions', { onSuccess: success })
    expect(form.errors.amount).toBe('Phải lớn hơn 0')
    expect(page.props.errors).toEqual(form.errors)
    expect(form.processing).toBe(false)
    expect(success).not.toHaveBeenCalled()
  })
  it('runs onSaved as soon as the server accepts, before redirecting', async () => {
    const order: string[] = []
    mocks.action.mockResolvedValue({ redirect: '/transactions' })
    mocks.push.mockImplementation(() => order.push('redirect'))
    mocks.request.mockRejectedValue(new Error('offline'))
    const form = useForm({ amount: 10 })
    await form.post('/transactions', {
      onSaved: () => order.push('saved'),
      onSuccess: () => order.push('success'),
    })
    expect(order).toEqual(['saved', 'redirect', 'success'])
  })
  it('does not run onSaved when saving fails', async () => {
    mocks.action.mockRejectedValue(new WalletsError('Invalid', {}))
    const saved = vi.fn()
    await useForm({ amount: 0 }).post('/transactions', { onSaved: saved })
    expect(saved).not.toHaveBeenCalled()
  })
  it('blocks double submission while request is pending', async () => {
    let resolve!: (value: unknown) => void
    mocks.action.mockReturnValue(
      new Promise((r) => {
        resolve = r
      }),
    )
    const form = useForm({ amount: 10 })
    const first = form.post('/transactions')
    await form.post('/transactions')
    expect(mocks.action).toHaveBeenCalledTimes(1)
    resolve({ component: 'Transactions/Create', props: {} })
    await first
    expect(form.processing).toBe(false)
  })
  it('transforms submitted data while preserving editable form values', async () => {
    mocks.action.mockResolvedValue({ component: 'Loans/Preview', props: {} })
    const form = useForm({ wallet_id: '5', name: 'Bank' })
    await form
      .transform((data) => ({ ...data, wallet_id: Number(data.wallet_id) }))
      .post('/loans/preview')
    expect(mocks.action).toHaveBeenLastCalledWith('/loans/preview', 'POST', {
      wallet_id: 5,
      name: 'Bank',
    })
    expect(form.wallet_id).toBe('5')
  })
})
