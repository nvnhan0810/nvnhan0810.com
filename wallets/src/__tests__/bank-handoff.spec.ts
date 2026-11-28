import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

const device = vi.hoisted(() => ({ active: true, openExternal: vi.fn() }))
vi.mock('../infrastructure/device', async () => {
  const actual = await vi.importActual<
    typeof import('../infrastructure/device')
  >('../infrastructure/device')
  return {
    ...actual,
    hasUserActivation: () => device.active,
    openExternal: device.openExternal,
  }
})

import {
  dismissBankHandoff,
  pendingHandoff,
  planBankHandoff,
  startBankHandoff,
} from '../application/bank-handoff'
import BankHandoffPrompt from '../presentation/Components/BankHandoffPrompt.vue'

const VCB_LINK = 'https://dl.vietqr.io/pay?app=vcb'
const plan = () =>
  planBankHandoff({ type: 'bank', bank_bin: '970436' }, undefined, 'android')!

function setVisibility(state: DocumentVisibilityState) {
  Object.defineProperty(document, 'visibilityState', {
    configurable: true,
    get: () => state,
  })
  document.dispatchEvent(new Event('visibilitychange'))
}

afterEach(() => {
  dismissBankHandoff()
  setVisibility('visible')
  device.active = true
  vi.clearAllMocks()
})

describe('bank app handoff after saving', () => {
  it('opens the bank app right away while the tap is still active', () => {
    startBankHandoff(plan())
    expect(device.openExternal).toHaveBeenCalledWith(VCB_LINK)
    expect(pendingHandoff.handoff?.link).toBe(VCB_LINK)
  })

  it('keeps a prompt to open it by tap when the browser would block it', () => {
    device.active = false
    startBankHandoff(plan(), '0123456789')
    expect(device.openExternal).not.toHaveBeenCalled()
    const wrapper = mount(BankHandoffPrompt)
    const link = wrapper.get('a')
    expect(link.attributes('href')).toBe(VCB_LINK)
    expect(link.text()).toBe('Mở app VCB Digibank')
    expect(wrapper.text()).toContain('0123456789')
    wrapper.unmount()
  })

  it('closes the prompt once the bank app took over the screen', () => {
    device.active = false
    startBankHandoff(plan())
    setVisibility('hidden')
    expect(pendingHandoff.handoff).toBeNull()
  })

  it('closes the prompt when the user taps open or later', async () => {
    device.active = false
    startBankHandoff(plan())
    const wrapper = mount(BankHandoffPrompt)
    await wrapper.get('button').trigger('click')
    expect(pendingHandoff.handoff).toBeNull()
    expect(wrapper.find('[data-testid="bank-handoff-prompt"]').exists()).toBe(
      false,
    )
    wrapper.unmount()
  })
})
