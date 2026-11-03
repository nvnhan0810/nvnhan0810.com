import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { expect, it, vi } from 'vitest'
import PageView from '../presentation/PageView.vue'

const mocks = vi.hoisted(() => ({ loadPage: vi.fn() }))
const route = reactive({ fullPath: '/first' })
const page = reactive({
  url: '/first',
  component: 'First',
  props: {},
  revision: 0,
  loading: false,
  error: '',
})
vi.mock('vue-router', () => ({ useRoute: () => route }))
vi.mock('../application/navigation', () => ({
  loadPage: (...args: unknown[]) => mocks.loadPage(...args),
  get page() {
    return page
  },
}))
vi.mock('../app/page-components', () => ({
  pageComponents: {
    First: { template: '<div data-screen="first">First</div>' },
    Second: { template: '<div data-screen="second">Second</div>' },
  },
}))

it('retains the mounted screen during navigation and errors, then swaps when ready', async () => {
  mocks.loadPage.mockResolvedValue(undefined)
  const wrapper = mount(PageView)
  await flushPromises()
  const current = wrapper.get('[data-screen="first"]').element
  mocks.loadPage.mockImplementationOnce(() => {
    page.loading = true
  })
  route.fullPath = '/second'
  await flushPromises()
  expect(wrapper.get('[data-screen="first"]').element).toBe(current)
  expect(wrapper.get('[role="status"]').attributes('aria-label')).toBe(
    'Đang tải trang',
  )
  Object.assign(page, { error: 'Không tải được trang.', loading: false })
  await flushPromises()
  expect(wrapper.get('[data-screen="first"]').element).toBe(current)
  await wrapper.get('button').trigger('click')
  expect(mocks.loadPage).toHaveBeenLastCalledWith('/second')
  Object.assign(page, {
    url: '/second',
    component: 'Second',
    loading: false,
    error: '',
  })
  await flushPromises()
  expect(wrapper.find('[data-screen="first"]').exists()).toBe(false)
  expect(wrapper.get('[data-screen="second"]').text()).toBe('Second')
  wrapper.unmount()
})
