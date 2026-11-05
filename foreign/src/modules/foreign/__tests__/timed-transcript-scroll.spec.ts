import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, expect, it, vi } from 'vitest'
import TimedTranscriptList from '@/Components/media/TimedTranscriptList.vue'

afterEach(() => vi.unstubAllGlobals())

it('centers the active paragraph and clamps scrolling at both ends', async () => {
  let frame: FrameRequestCallback | undefined
  vi.stubGlobal(
    'requestAnimationFrame',
    vi.fn((callback: FrameRequestCallback) => {
      frame = callback
      return 1
    }),
  )
  vi.stubGlobal(
    'cancelAnimationFrame',
    vi.fn(() => {
      frame = undefined
    }),
  )
  const advanceFrame = (time: number) => {
    const callback = frame
    frame = undefined
    callback?.(time)
  }
  const wrapper = mount(TimedTranscriptList, {
    props: {
      segments: Array.from({ length: 3 }, (_, index) => ({
        start: index * 10,
        end: index * 10 + 10,
        text: `Paragraph ${index}`,
      })),
      activeIndex: -1,
    },
  })
  try {
    const list = wrapper.element as HTMLElement
    Object.defineProperties(list, {
      clientHeight: { value: 200 },
      scrollHeight: { value: 600 },
      scrollTop: { value: 100, writable: true },
    })
    vi.spyOn(list, 'getBoundingClientRect').mockReturnValue({
      top: 50,
    } as DOMRect)
    const positions = [-50, 250, 500]
    wrapper.findAll('button').forEach((button, index) => {
      vi.spyOn(button.element, 'getBoundingClientRect').mockReturnValue({
        top: positions[index],
        height: 40,
      } as DOMRect)
    })
    for (const [index, top] of [0, 220, 400].entries()) {
      list.scrollTop = 100
      await wrapper.setProps({ activeIndex: index })
      await flushPromises()
      advanceFrame(0)
      expect(list.scrollTop).toBe(100)
      advanceFrame(300)
      expect(list.scrollTop).toBeCloseTo((100 + top) / 2)
      advanceFrame(600)
      expect(list.scrollTop).toBe(top)
    }
    await wrapper.setProps({ activeIndex: 0 })
    await flushPromises()
    advanceFrame(0)
    advanceFrame(150)
    const interruptedTop = list.scrollTop
    await wrapper.trigger('wheel')
    advanceFrame(600)
    expect(list.scrollTop).toBe(interruptedTop)
    expect(frame).toBeUndefined()

    await wrapper.setProps({ activeIndex: 1 })
    await flushPromises()
    expect(frame).toBeDefined()
    await wrapper.setProps({ activeIndex: -1 })
    await flushPromises()
    expect(frame).toBeUndefined()
  } finally {
    wrapper.unmount()
  }
})
