import { flushPromises, mount } from '@vue/test-utils'
import { expect, it, vi } from 'vitest'
import TimedTranscriptList from '@/Components/media/TimedTranscriptList.vue'

it('centers the active paragraph and clamps scrolling at both ends', async () => {
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
    const scrollTo = vi.fn()
    list.scrollTo = scrollTo
    Object.defineProperties(list, {
      clientHeight: { value: 200 },
      scrollHeight: { value: 600 },
      scrollTop: { value: 100 },
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
      await wrapper.setProps({ activeIndex: index })
      await flushPromises()
      expect(scrollTo).toHaveBeenLastCalledWith({ top, behavior: 'smooth' })
    }
    await wrapper.setProps({ activeIndex: -1 })
    await flushPromises()
    expect(scrollTo).toHaveBeenCalledTimes(3)
  } finally {
    wrapper.unmount()
  }
})
