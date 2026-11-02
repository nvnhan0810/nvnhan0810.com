import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { Component } from 'vue'
import fixtures from './fixtures/pages.json'
import { route } from '../application/routes'
const shared = vi.hoisted(() => ({
  url: '/',
  props: {
    auth: { user: { name: 'Tester' } },
    flash: {},
    errors: {},
    debtGoal: null,
  },
}))
vi.mock('../application/navigation', async () => {
  const { defineComponent, h, reactive } = await import('vue')
  return {
    usePage: () => shared,
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
      props: ['href'],
      setup:
        (props, { slots }) =>
        () =>
          h('a', { href: props.href }, slots.default?.()),
    }),
    router: { post: vi.fn(), get: vi.fn(), delete: vi.fn() },
    useForm: <T extends Record<string, unknown>>(data: T) =>
      reactive({
        ...data,
        errors: {},
        processing: false,
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
        setError: vi.fn(),
        transform: vi.fn().mockReturnThis(),
      }),
  }
})
vi.mock('chart.js/auto', () => ({
  default: class {
    static defaults = { font: {} }
    destroy() {}
  },
}))
const modules = import.meta.glob('../presentation/Pages/**/*.vue', {
  eager: true,
  import: 'default',
})
Object.assign(globalThis, { route })
describe('Rust page contracts render in the preserved Vue screens', () => {
  for (const fixture of fixtures)
    it(fixture.component, () => {
      const component = modules[
        `../presentation/Pages/${fixture.component}.vue`
      ] as Component
      expect(component).toBeDefined()
      const wrapper = mount(component, {
        props: fixture.props as Record<string, unknown>,
        global: { mocks: { route } },
      })
      expect(wrapper.text().length).toBeGreaterThan(0)
      wrapper.unmount()
    })
})
