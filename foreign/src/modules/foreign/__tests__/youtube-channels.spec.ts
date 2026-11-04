import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import YoutubeChannelsPage from '../presentation/pages/YoutubeChannelsPage.vue'
import {
  youtubeApi,
  type YoutubeVideoPage,
} from '../infrastructure/youtube-api'
import { ApiError } from '../infrastructure/transport'

vi.mock('../infrastructure/youtube-api', () => ({
  youtubeApi: {
    channels: vi.fn(),
    videos: vi.fn(),
    saveChannel: vi.fn(),
    removeChannel: vi.fn(),
    addListening: vi.fn(),
  },
}))
vi.mock('../../../legacy/application/navigation', () => ({
  Head: defineComponent({ render: () => null }),
}))
vi.mock('../../../legacy/presentation/Layouts/AppLayout.vue', () => ({
  default: defineComponent({ template: '<main><slot /></main>' }),
}))
const channel = {
  channel_id: 'UC1234567890123456789012',
  title: 'English lessons',
  thumbnail_url: '',
}
const video = {
  video_id: 'dQw4w9WgXcQ',
  title: 'A listening lesson',
  channel_id: channel.channel_id,
  channel_title: channel.title,
  thumbnail_url: '',
  duration: 'PT12M34S',
  published_at: '2026-11-04T10:00:00Z',
  media_id: null,
}
function mountPage() {
  return mount(YoutubeChannelsPage, {
    global: {
      stubs: {
        RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
      },
    },
  })
}
beforeEach(() => {
  vi.resetAllMocks()
  HTMLDialogElement.prototype.showModal = function () {
    this.setAttribute('open', '')
  }
  HTMLDialogElement.prototype.close = function () {
    this.removeAttribute('open')
  }
  vi.mocked(youtubeApi.channels).mockResolvedValue({ items: [channel] })
  vi.mocked(youtubeApi.videos).mockResolvedValue({
    items: [{ ...video }],
    next_cursor: 'next-page',
  })
})
it('paginates, filters saved channels and resets the cursor when the filter changes', async () => {
  const wrapper = mountPage()
  await flushPromises()
  expect(wrapper.text()).toContain(video.title)
  expect(wrapper.get('.channel-video-duration').text()).toBe('12:34')
  const next = () =>
    wrapper.findAll('button').find((b) => b.text() === 'Next →')!
  await next().trigger('click')
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    '',
    'next-page',
    expect.any(AbortSignal),
  )
  expect(wrapper.text()).toContain('Page 2')
  await wrapper.get('#channel-filter').setValue(channel.channel_id)
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    channel.channel_id,
    null,
    expect.any(AbortSignal),
  )
  expect(wrapper.text()).toContain('Page 1')
  wrapper.unmount()
})
it('adds a video once with the selected difficulty and marks it saved', async () => {
  const wrapper = mountPage()
  await flushPromises()
  vi.mocked(youtubeApi.addListening).mockResolvedValue({
    media_id: 42,
    captions_queued: true,
  })
  expect(wrapper.find('#video-difficulty').exists()).toBe(false)
  await wrapper
    .findAll('button')
    .find((b) => b.text() === '+ Add to listening')!
    .trigger('click')
  expect(youtubeApi.addListening).not.toHaveBeenCalled()
  expect(wrapper.get('dialog').attributes('open')).toBeDefined()
  await wrapper.get('#video-difficulty').setValue('beginner')
  await wrapper.get('dialog form').trigger('submit')
  await flushPromises()
  expect(wrapper.get('dialog').attributes('open')).toBeUndefined()
  expect(youtubeApi.addListening).toHaveBeenCalledWith(
    video.video_id,
    'beginner',
  )
  expect(wrapper.get('.channel-saved-badge').text()).toContain('Saved')
  expect(wrapper.get('.channel-video-body a').attributes('href')).toBe(
    '/media/42',
  )
  expect(
    wrapper.findAll('button').some((b) => b.text() === '+ Add to listening'),
  ).toBe(false)
  wrapper.unmount()
})
it('marks existing listening videos and does not offer to add them again', async () => {
  vi.mocked(youtubeApi.videos).mockResolvedValue({
    items: [{ ...video, media_id: 12 }],
    next_cursor: null,
  })
  const wrapper = mountPage()
  await flushPromises()
  expect(wrapper.text()).toContain('✓ Saved')
  expect(wrapper.get('.channel-video-body a').attributes('href')).toBe(
    '/media/12',
  )
  expect(youtubeApi.addListening).not.toHaveBeenCalled()
  wrapper.unmount()
})
it('keeps the latest filter results if an older request completes late', async () => {
  const wrapper = mountPage()
  await flushPromises()
  let finish!: (page: YoutubeVideoPage) => void
  vi.mocked(youtubeApi.videos).mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        finish = resolve
      }),
  )
  await wrapper.get('#channel-filter').setValue(channel.channel_id)
  // Trigger a second change directly to simulate queued user events.
  await wrapper.get('#channel-filter').setValue('')
  await flushPromises()
  finish({ items: [{ ...video, title: 'Stale result' }], next_cursor: null })
  await flushPromises()
  expect(wrapper.text()).not.toContain('Stale result')
  expect(wrapper.text()).toContain(video.title)
  wrapper.unmount()
})
it('shows a useful error and can retry', async () => {
  vi.mocked(youtubeApi.videos).mockRejectedValueOnce(
    new ApiError(503, 'youtube_not_configured'),
  )
  const wrapper = mountPage()
  await flushPromises()
  expect(wrapper.get('[role="alert"]').text()).toContain('not available yet')
  await wrapper
    .findAll('button')
    .find((b) => b.text() === 'Retry')!
    .trigger('click')
  await flushPromises()
  expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  expect(wrapper.text()).toContain(video.title)
  wrapper.unmount()
})
it('saves a channel and refreshes the feed', async () => {
  const wrapper = mountPage()
  await flushPromises()
  await wrapper.get('#channel-url').setValue('@english')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(youtubeApi.saveChannel).toHaveBeenCalledWith('@english')
  expect(youtubeApi.channels).toHaveBeenCalledTimes(2)
  expect(wrapper.text()).toContain('Channel saved.')
  wrapper.unmount()
})

it('cancels the difficulty picker without saving a video', async () => {
  const wrapper = mountPage()
  await flushPromises()
  await wrapper
    .findAll('button')
    .find((b) => b.text() === '+ Add to listening')!
    .trigger('click')
  await wrapper.get('dialog button[type="button"]').trigger('click')
  expect(wrapper.get('dialog').attributes('open')).toBeUndefined()
  expect(youtubeApi.addListening).not.toHaveBeenCalled()
  wrapper.unmount()
})
it('keeps the selected difficulty and shows save errors inside the dialog', async () => {
  vi.mocked(youtubeApi.addListening).mockRejectedValueOnce(
    new ApiError(403, 'youtube_key_restricted'),
  )
  const wrapper = mountPage()
  await flushPromises()
  await wrapper
    .findAll('button')
    .find((b) => b.text() === '+ Add to listening')!
    .trigger('click')
  await wrapper.get('#video-difficulty').setValue('advanced')
  await wrapper.get('dialog form').trigger('submit')
  await flushPromises()
  expect(wrapper.get('dialog').attributes('open')).toBeDefined()
  expect(wrapper.get('dialog [role="alert"]').text()).toContain('restrictions')
  expect(
    (wrapper.get('#video-difficulty').element as HTMLSelectElement).value,
  ).toBe('advanced')
  wrapper.unmount()
})
