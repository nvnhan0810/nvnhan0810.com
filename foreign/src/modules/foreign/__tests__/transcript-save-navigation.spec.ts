import { expect, it, vi } from 'vitest'
const mocks = vi.hoisted(() => ({
  request: vi.fn(),
  push: vi.fn(),
  replace: vi.fn(),
}))
vi.mock('../../../app/router', () => ({
  router: {
    currentRoute: { value: { fullPath: '/media/1/transcript/edit' } },
    push: mocks.push,
    replace: mocks.replace,
  },
}))
vi.mock('../../../app/services', () => ({ session: {} }))
vi.mock('../../../app/auth', () => ({ logout: vi.fn() }))
vi.mock('../infrastructure/foreign-api', () => ({
  foreignRequest: mocks.request,
}))
import { page, router } from '@/application/navigation'

it('preserves the editor state and ignores the action redirect when saving in place', async () => {
  mocks.request.mockResolvedValue({
    redirect: '/home/media/1',
    props: { item: {} },
  })
  page.props = { item: { title: 'Current editor' } }
  const props = page.props
  const revision = page.revision
  const success = vi.fn(),
    finish = vi.fn()
  await router.put(
    '/home/media/1/transcript',
    { segments: [] },
    {
      stayOnPage: true,
      onSuccess: success,
      onFinish: finish,
    },
  )
  expect(mocks.request).toHaveBeenCalledTimes(1)
  expect(mocks.push).not.toHaveBeenCalled()
  expect(mocks.replace).not.toHaveBeenCalled()
  expect(page.props).toBe(props)
  expect(page.revision).toBe(revision)
  expect(success).toHaveBeenCalledOnce()
  expect(finish).toHaveBeenCalledOnce()
})
