import { mount } from '@vue/test-utils'
import { beforeEach, expect, it, vi } from 'vitest'
import EditTranscript from '@/presentation/Pages/Media/EditTranscript.vue'
const put = vi.hoisted(() => vi.fn())
vi.mock('@/application/navigation', () => ({
  Head: { template: '<span />' },
  Link: { template: '<a><slot /></a>' },
  router: { put },
}))
beforeEach(() => put.mockClear())
function editor() {
  return mount(EditTranscript, {
    props: {
      item: {
        id: 1,
        title: 'Test',
        type: 'youtube',
        source_id: 'test',
        url: null,
        difficulty: null,
        difficulty_label: null,
        transcript: null,
        transcript_segments: [
          { start: 10, end: 20, text: 'First' },
          { start: 5, end: 15, text: 'Second' },
        ],
      },
    },
    global: {
      stubs: {
        AppLayout: { template: '<div><slot /></div>' },
        YouTubeSyncedPlayer: true,
      },
    },
  })
}
it('saves overlapping segments in their entered order without changing end times', async () => {
  const wrapper = editor()
  try {
    await wrapper
      .get('.media-transcript-edit-footer .btn:last-child')
      .trigger('click')
    expect(put).toHaveBeenCalledWith(
      expect.any(String),
      {
        segments: [
          { start: 10, end: 20, text: 'First' },
          { start: 5, end: 15, text: 'Second' },
        ],
      },
      expect.any(Object),
    )
  } finally {
    wrapper.unmount()
  }
})
it('rejects end before start and empty content', async () => {
  const wrapper = editor()
  try {
    await wrapper.findAll('input')[1]!.setValue('9')
    await wrapper.findAll('textarea')[1]!.setValue(' ')
    await wrapper
      .get('.media-transcript-edit-footer .btn:last-child')
      .trigger('click')
    expect(put).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('End must be after start')
    expect(wrapper.text()).toContain('Subtitle text is required')
  } finally {
    wrapper.unmount()
  }
})
it('sets start to the previous end', async () => {
  const wrapper = editor()
  try {
    const buttons = wrapper
      .findAll('button')
      .filter((button) => button.text() === 'Start = previous')
    expect(buttons[0]!.attributes('disabled')).toBeDefined()
    await buttons[1]!.trigger('click')
    expect(wrapper.findAll('input')[2]!.element.value).toBe('0:20.000')
  } finally {
    wrapper.unmount()
  }
})
