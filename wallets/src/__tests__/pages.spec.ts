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
  lastForm: null as null | Record<string, unknown>,
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
      (shared.lastForm = reactive({
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
      })),
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

describe('VietQR transfer on the create screen', () => {
  const fixture = fixtures.find((f) => f.component === 'Transactions/Create')!
  it('fills an expense from a scanned code and picks the bank wallet', async () => {
    const { buildVietQr } = await import('../domain')
    const component = modules[
      '../presentation/Pages/Transactions/Create.vue'
    ] as Component
    const wrapper = mount(component, {
      props: {
        ...(fixture.props as Record<string, unknown>),
        prefill: { scan: '1' },
      },
      global: { mocks: { route }, stubs: { teleport: true } },
    })
    const scanner = wrapper.findComponent({ name: 'QrScanner' })
    expect(scanner.props('open')).toBe(true)
    const accept = scanner.props('accept') as (raw: string) => true | string
    expect(accept('hello')).toMatch(/VietQR/)
    expect(
      accept(
        buildVietQr({
          bin: '970422',
          accountNumber: '0123456789',
          amount: 50000,
          message: 'Tien nha',
        }),
      ),
    ).toBe(true)
    await wrapper.vm.$nextTick()
    const text = wrapper.text()
    expect(text).toContain('Chuyển khoản đến')
    expect(text).toContain('0123456789')
    expect(text).toContain('MBBank')
    expect(text).toContain('Vietcombank') // source bank wallet from fixtures
    const values = wrapper
      .findAll('input')
      .map((i) => (i.element as HTMLInputElement).value)
    expect(values).toContain('Tien nha')
    expect(values).toContain('Chuyển khoản')
    expect(scanner.props('open')).toBe(false)
    wrapper.unmount()
  })
})

describe('bank wallet form', () => {
  it('takes BIN and code from the VietQR master list when a bank is picked', async () => {
    const component = modules[
      '../presentation/Pages/Wallets/Form.vue'
    ] as Component
    const wrapper = mount(component, {
      props: { wallet: null },
      global: { mocks: { route }, stubs: { teleport: true } },
    })
    const form = shared.lastForm as Record<string, unknown> & {
      transform: ReturnType<typeof vi.fn>
      post: ReturnType<typeof vi.fn>
    }
    form.name = 'VCB lương'
    form.type = 'bank'
    await wrapper.vm.$nextTick()
    expect(wrapper.find('#bank_bin').exists()).toBe(true)
    // Only the bank picker and account fields are inputs: no BIN/code to type.
    expect(wrapper.find('input[name="bank_code"]').exists()).toBe(false)
    wrapper
      .findComponent({ name: 'AppSelect' })
      .vm.$emit('update:modelValue', '970436')
    form.account_number = '0011 0019 32418'
    await wrapper.vm.$nextTick()
    const info = wrapper.find('[data-testid="bank-master-info"]').text()
    expect(info).toContain('970436')
    expect(info).toContain('VCB')
    expect(info).toContain('VCB Digibank')

    await wrapper.find('form').trigger('submit')
    expect(form.post).toHaveBeenCalled()
    const toPayload = form.transform.mock.calls[0][0] as (
      data: Record<string, unknown>,
    ) => Record<string, unknown>
    const payload = toPayload({ ...form, bank_code: 'WRONG' })
    expect(payload.bank_bin).toBe('970436')
    expect(payload.bank_code).toBe('VCB')
    expect(payload.account_number).toBe('0011001932418')
    wrapper.unmount()
  })
})
