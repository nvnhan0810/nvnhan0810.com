import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { expect, it, vi } from 'vitest'
import PageView from '../../../legacy/application/PageView.vue'

const state = vi.hoisted(() => ({ loadPage: vi.fn() }))
let route: { name: string; path: string; fullPath: string }
vi.mock('vue-router', () => ({ useRoute: () => route }))
vi.mock('@/application/navigation', () => ({
  loadPage: state.loadPage,
  page: { props: {}, revision: 0 },
}))
vi.mock('../../../legacy/presentation/Pages/Learn/Index.vue', () => ({
  default: { template: '<div data-screen="lookup">Lookup</div>' },
}))
vi.mock('../../../legacy/presentation/Pages/Vocab/Index.vue', () => ({
  default: { template: '<div data-screen="vocabulary">Vocabulary</div>' },
}))

it('keeps the current screen mounted while the next menu page loads', async () => {
  route = reactive({
    name: 'lookup',
    path: '/home/lookup',
    fullPath: '/home/lookup',
  })
  state.loadPage.mockResolvedValueOnce(undefined)
  const wrapper = mount(PageView)
  await flushPromises()
  const current = wrapper.get('[data-screen="lookup"]').element
  let finish!: () => void
  state.loadPage.mockImplementationOnce(
    () =>
      new Promise<void>((resolve) => {
        finish = resolve
      }),
  )
  Object.assign(route, {
    name: 'vocabulary',
    path: '/home/vocab',
    fullPath: '/home/vocab',
  })
  await flushPromises()
  expect(wrapper.get('[data-screen="lookup"]').element).toBe(current)
  expect(wrapper.find('[data-screen="vocabulary"]').exists()).toBe(false)
  finish()
  await flushPromises()
  expect(wrapper.find('[data-screen="lookup"]').exists()).toBe(false)
  expect(wrapper.get('[data-screen="vocabulary"]').text()).toBe('Vocabulary')
  wrapper.unmount()
})
