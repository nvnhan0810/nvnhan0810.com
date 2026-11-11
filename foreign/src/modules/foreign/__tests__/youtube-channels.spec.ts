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
  description:
    'Practice your listening.\nA second line with <b>plain text</b>.',
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
  localStorage.clear()
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
it('previews videos in a dialog and removes the player on close or Escape', async () => {
  const wrapper = mountPage()
  await flushPromises()
  expect(wrapper.find('iframe').exists()).toBe(false)
  expect(wrapper.find('a[target="_blank"]').exists()).toBe(false)
  await wrapper.get('.channel-video-thumb').trigger('click')
  await flushPromises()
  const dialog = wrapper.get('.video-preview-dialog')
  expect(dialog.attributes('open')).toBeDefined()
  expect(dialog.get('h2').text()).toBe(video.title)
  expect(dialog.get('iframe').attributes('src')).toBe(
    `https://www.youtube.com/embed/${video.video_id}?playsinline=1`,
  )
  expect(dialog.get('.video-preview-description').text()).toBe(
    video.description,
  )
  expect(dialog.find('.video-preview-description b').exists()).toBe(false)
  await dialog.get('button[aria-label="Close video preview"]').trigger('click')
  expect(dialog.attributes('open')).toBeUndefined()
  expect(dialog.find('iframe').exists()).toBe(false)
  await wrapper.get('.channel-video-title').trigger('click')
  await flushPromises()
  expect(dialog.find('iframe').exists()).toBe(true)
  await dialog.trigger('cancel')
  expect(dialog.attributes('open')).toBeUndefined()
  expect(dialog.find('iframe').exists()).toBe(false)
  wrapper.unmount()
})
it('shows a fallback for videos with no description', async () => {
  vi.mocked(youtubeApi.videos).mockResolvedValue({
    items: [{ ...video, description: '' }],
    next_cursor: null,
  })
  const wrapper = mountPage()
  await flushPromises()
  await wrapper.get('.channel-video-thumb').trigger('click')
  expect(wrapper.get('.video-preview-description').text()).toBe(
    'No description available.',
  )
  wrapper.unmount()
})
it('paginates, filters saved channels and resets the cursor when the filter changes', async () => {
  const wrapper = mountPage()
  await flushPromises()
  expect(wrapper.text()).toContain(video.title)
  expect(wrapper.get('.channel-video-duration').text()).toBe('12:34')
  const next = () => wrapper.get('button[aria-label="Next page"]')
  await next().trigger('click')
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    '',
    'next-page',
    expect.any(AbortSignal),
    null,
  )
  expect(wrapper.text()).toContain('Page 2')
  await wrapper
    .get(`button[data-channel-id="${channel.channel_id}"]`)
    .trigger('click')
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    channel.channel_id,
    null,
    expect.any(AbortSignal),
    null,
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
  await wrapper.get('button[aria-label="Add to listening"]').trigger('click')
  expect(youtubeApi.addListening).not.toHaveBeenCalled()
  expect(wrapper.get('.add-listening-dialog').attributes('open')).toBeDefined()
  await wrapper.get('#video-difficulty').setValue('beginner')
  await wrapper.get('.add-listening-dialog form').trigger('submit')
  await flushPromises()
  expect(
    wrapper.get('.add-listening-dialog').attributes('open'),
  ).toBeUndefined()
  expect(youtubeApi.addListening).toHaveBeenCalledWith(
    video.video_id,
    'beginner',
  )
  expect(wrapper.get('.channel-saved-badge').text()).toContain('Saved')
  expect(wrapper.get('.channel-video-body a').attributes('href')).toBe(
    '/media/42',
  )
  expect(wrapper.find('button[aria-label="Add to listening"]').exists()).toBe(
    false,
  )
  wrapper.unmount()
})
it('marks existing listening videos and does not offer to add them again', async () => {
  vi.mocked(youtubeApi.videos).mockResolvedValue({
    items: [{ ...video, media_id: 12 }],
    next_cursor: null,
  })
  const wrapper = mountPage()
  await flushPromises()
  expect(wrapper.text()).toContain('Saved')
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
  await wrapper
    .get(`button[data-channel-id="${channel.channel_id}"]`)
    .trigger('click')
  // Trigger a second change directly to simulate queued user events.
  await wrapper.get('button[data-channel-id=""]').trigger('click')
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
  await wrapper.get('button[aria-label="Retry"]').trigger('click')
  await flushPromises()
  expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  expect(wrapper.text()).toContain(video.title)
  wrapper.unmount()
})
it('saves a channel and refreshes the feed', async () => {
  const wrapper = mountPage()
  await flushPromises()
  expect(wrapper.get('.channel-dialog').attributes('open')).toBeUndefined()
  await wrapper.get('button[aria-label="Add channel"]').trigger('click')
  expect(wrapper.get('.channel-dialog').attributes('open')).toBeDefined()
  await wrapper.get('#channel-url').setValue('@english')
  await wrapper.get('#channel-add-form').trigger('submit')
  await flushPromises()
  expect(youtubeApi.saveChannel).toHaveBeenCalledWith('@english')
  expect(youtubeApi.channels).toHaveBeenCalledTimes(2)
  expect(wrapper.text()).toContain('Channel saved.')
  expect(wrapper.get('.channel-dialog').attributes('open')).toBeUndefined()
  expect(wrapper.find('.channels-intro').exists()).toBe(false)
  wrapper.unmount()
})

it('cancels the difficulty picker without saving a video', async () => {
  const wrapper = mountPage()
  await flushPromises()
  await wrapper.get('button[aria-label="Add to listening"]').trigger('click')
  await wrapper
    .get('.add-listening-dialog button[type="button"]')
    .trigger('click')
  expect(
    wrapper.get('.add-listening-dialog').attributes('open'),
  ).toBeUndefined()
  expect(youtubeApi.addListening).not.toHaveBeenCalled()
  wrapper.unmount()
})
it('keeps the selected difficulty and shows save errors inside the dialog', async () => {
  vi.mocked(youtubeApi.addListening).mockRejectedValueOnce(
    new ApiError(403, 'youtube_key_restricted'),
  )
  const wrapper = mountPage()
  await flushPromises()
  await wrapper.get('button[aria-label="Add to listening"]').trigger('click')
  await wrapper.get('#video-difficulty').setValue('advanced')
  await wrapper.get('.add-listening-dialog form').trigger('submit')
  await flushPromises()
  expect(wrapper.get('.add-listening-dialog').attributes('open')).toBeDefined()
  expect(wrapper.get('.add-listening-dialog [role="alert"]').text()).toContain(
    'restrictions',
  )
  expect(
    (wrapper.get('#video-difficulty').element as HTMLSelectElement).value,
  ).toBe('advanced')
  wrapper.unmount()
})

it('uses saved duration settings on the initial video request', async () => {
  localStorage.setItem(
    'foreign.youtube.duration-filter',
    JSON.stringify({ enabled: true, minutes: 10 }),
  )
  const wrapper = mountPage()
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    '',
    null,
    expect.any(AbortSignal),
    600,
  )
  expect(
    (wrapper.get('#duration-filter-enabled').element as HTMLInputElement)
      .checked,
  ).toBe(true)
  expect(
    (wrapper.get('#max-duration-minutes').element as HTMLInputElement).value,
  ).toBe('10')
  wrapper.unmount()
})
it('reloads from page one when duration settings change and sends no limit when switched off', async () => {
  const wrapper = mountPage()
  await flushPromises()
  await wrapper.get('button[aria-label="Next page"]').trigger('click')
  await flushPromises()
  await wrapper.get('button[aria-label="Duration settings"]').trigger('click')
  await wrapper.get('#duration-filter-enabled').setValue(true)
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    '',
    null,
    expect.any(AbortSignal),
    900,
  )
  expect(wrapper.text()).toContain('Page 1')
  await wrapper.get('#max-duration-minutes').setValue('8')
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    '',
    null,
    expect.any(AbortSignal),
    480,
  )
  expect(
    JSON.parse(localStorage.getItem('foreign.youtube.duration-filter')!),
  ).toEqual({ enabled: true, minutes: 8 })
  await wrapper.get('#duration-filter-enabled').setValue(false)
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    '',
    null,
    expect.any(AbortSignal),
    null,
  )
  expect(
    wrapper.get('#max-duration-minutes').attributes('disabled'),
  ).toBeDefined()
  wrapper.unmount()
})
it('validates the duration limit and explains empty filtered pages without losing the next page', async () => {
  const wrapper = mountPage()
  await flushPromises()
  await wrapper.get('button[aria-label="Duration settings"]').trigger('click')
  await wrapper.get('#duration-filter-enabled').setValue(true)
  await flushPromises()
  const before = vi.mocked(youtubeApi.videos).mock.calls.length
  await wrapper.get('#max-duration-minutes').setValue('0')
  await flushPromises()
  expect(wrapper.text()).toContain('from 1 to 1440 minutes')
  expect(youtubeApi.videos).toHaveBeenCalledTimes(before)
  vi.mocked(youtubeApi.videos).mockResolvedValueOnce({
    items: [],
    next_cursor: 'filtered-next',
  })
  await wrapper.get('#max-duration-minutes').setValue('5')
  await flushPromises()
  expect(wrapper.text()).toContain('No videos within your duration limit')
  await wrapper
    .get('button[aria-label="Close duration settings"]')
    .trigger('click')
  const next = wrapper.get('button[aria-label="Next page"]')
  expect(next.attributes('disabled')).toBeUndefined()
  await next.trigger('click')
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    '',
    'filtered-next',
    expect.any(AbortSignal),
    300,
  )
  wrapper.unmount()
})

it('keeps the add-channel popup open with errors so the URL can be corrected', async () => {
  vi.mocked(youtubeApi.saveChannel).mockRejectedValueOnce(
    new ApiError(404, 'not_found'),
  )
  const wrapper = mountPage()
  await flushPromises()
  await wrapper.get('button[aria-label="Add channel"]').trigger('click')
  await wrapper.get('#channel-url').setValue('@missing')
  await wrapper.get('#channel-add-form').trigger('submit')
  await flushPromises()
  expect(wrapper.get('.channel-dialog').attributes('open')).toBeDefined()
  expect(wrapper.get('.channel-dialog [role="alert"]').text()).toContain(
    'not found',
  )
  expect((wrapper.get('#channel-url').element as HTMLInputElement).value).toBe(
    '@missing',
  )
  await wrapper.get('button[aria-label="Close channels"]').trigger('click')
  expect(wrapper.get('.channel-dialog').attributes('open')).toBeUndefined()
  wrapper.unmount()
})
it('keeps search and a duration button in the toolbar and manages channels in the popup', async () => {
  const wrapper = mountPage()
  await flushPromises()
  expect(wrapper.find('.channel-toolbar #channel-search').exists()).toBe(true)
  expect(wrapper.find('.channel-toolbar #max-duration-minutes').exists()).toBe(
    false,
  )
  expect(
    wrapper
      .get('.channel-toolbar button[aria-label="Duration settings"]')
      .text(),
  ).toBe('15')
  await wrapper.get('button[aria-label="Add channel"]').trigger('click')
  expect(
    wrapper.get('.channel-dialog [aria-label="Saved channels"]').text(),
  ).toContain(channel.title)
  const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true)
  await wrapper
    .get(`button[aria-label="Remove ${channel.title}"]`)
    .trigger('click')
  await flushPromises()
  expect(youtubeApi.removeChannel).toHaveBeenCalledWith(channel.channel_id)
  confirm.mockRestore()
  wrapper.unmount()
})

it('searches channel tags by name without fetching videos until a tag is selected', async () => {
  const other = {
    ...channel,
    channel_id: 'other-channel',
    title: 'Science lessons',
    thumbnail_url: 'https://example.com/channel.jpg',
  }
  vi.mocked(youtubeApi.channels).mockResolvedValue({ items: [channel, other] })
  const wrapper = mountPage()
  await flushPromises()
  const all = wrapper.get('button[data-channel-id=""]')
  expect(wrapper.get('.channel-tags button').text()).toBe('All')
  expect(all.attributes('aria-pressed')).toBe('true')
  const requests = vi.mocked(youtubeApi.videos).mock.calls.length
  await wrapper.get('#channel-search').setValue('SCIENCE')
  expect(
    wrapper
      .find(`.channel-tags [data-channel-id="${channel.channel_id}"]`)
      .exists(),
  ).toBe(false)
  expect(
    wrapper.get('.channel-tags [data-channel-id="other-channel"]').text(),
  ).toBe('Science lessons')
  expect(youtubeApi.videos).toHaveBeenCalledTimes(requests)
  await wrapper
    .get('.channel-tags [data-channel-id="other-channel"]')
    .trigger('click')
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    'other-channel',
    null,
    expect.any(AbortSignal),
    null,
  )
  expect(all.attributes('aria-pressed')).toBe('false')
  await all.trigger('click')
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    '',
    null,
    expect.any(AbortSignal),
    null,
  )
  await wrapper.get('#channel-search').setValue('no such channel')
  expect(wrapper.text()).toContain('No channels found.')
  expect(wrapper.find('.channel-tags [data-channel-id=""]').exists()).toBe(true)
  wrapper.unmount()
})
it('refreshes the current page and keeps the selected channel and active duration limit', async () => {
  localStorage.setItem(
    'foreign.youtube.duration-filter',
    JSON.stringify({ enabled: true, minutes: 10 }),
  )
  const wrapper = mountPage()
  await flushPromises()
  await wrapper
    .get(`button[data-channel-id="${channel.channel_id}"]`)
    .trigger('click')
  await flushPromises()
  await wrapper.get('button[aria-label="Next page"]').trigger('click')
  await flushPromises()
  await wrapper.get('button[aria-label="Refresh videos"]').trigger('click')
  await flushPromises()
  expect(youtubeApi.videos).toHaveBeenLastCalledWith(
    channel.channel_id,
    'next-page',
    expect.any(AbortSignal),
    600,
  )
  expect(wrapper.text()).toContain('Page 2')
  wrapper.unmount()
})
it('shows only the number of minutes on the settings button and highlights it only while filtering is enabled', async () => {
  const wrapper = mountPage()
  await flushPromises()
  const button = wrapper.get('button[aria-label="Duration settings"]')
  expect(button.text()).toBe('15')
  expect(button.classes()).not.toContain('channel-icon-primary')
  await button.trigger('click')
  expect(
    wrapper.get('.duration-settings-dialog').attributes('open'),
  ).toBeDefined()
  await wrapper.get('#duration-filter-enabled').setValue(true)
  await flushPromises()
  await wrapper.get('#max-duration-minutes').setValue('8')
  await flushPromises()
  expect(button.text()).toBe('8')
  expect(button.classes()).toContain('channel-icon-primary')
  await wrapper.get('#duration-filter-enabled').setValue(false)
  await flushPromises()
  expect(button.text()).toBe('8')
  expect(button.classes()).not.toContain('channel-icon-primary')
  await wrapper
    .get('button[aria-label="Close duration settings"]')
    .trigger('click')
  expect(
    wrapper.get('.duration-settings-dialog').attributes('open'),
  ).toBeUndefined()
  wrapper.unmount()
})
