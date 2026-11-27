import { mount } from '@vue/test-utils'
import { beforeEach, expect, it, vi } from 'vitest'
import Show from '@/presentation/Pages/Media/Show.vue'

vi.mock('@/application/navigation', () => ({
  Head: { template: '<span />' },
  Link: { template: '<a><slot /></a>' },
  usePage: () => ({ props: {} }),
  useForm: () => ({ processing: false, post: vi.fn() }),
  router: { reload: vi.fn() },
}))

const storage = new Map<string, string>()
beforeEach(() => {
  storage.clear()
  vi.stubGlobal('localStorage', {
    getItem: (key: string) => storage.get(key) ?? null,
    setItem: (key: string, value: string) => storage.set(key, value),
  })
})

function page() {
  return mount(Show, {
    props: {
      item: {
        id: 1,
        title: 'Video',
        type: 'youtube',
        source_id: 'abc',
        url: null,
        frequency: null,
        difficulty: null,
        difficulty_label: null,
        transcript: 'Hello world',
        transcript_segments: null,
        analysis_status: null,
      },
    },
    global: {
      stubs: {
        AppLayout: { template: '<div><slot /></div>' },
        YouTubeSyncedPlayer: {
          template: '<div class="player"><slot name="toolbar-actions" /></div>',
        },
      },
    },
  })
}

it('toggles full width video and remembers the choice', async () => {
  const wrapper = page()
  const main = wrapper.get('.media-show-main')
  const toggle = wrapper.get('.media-layout-toggle')
  expect(main.classes()).not.toContain('is-video-wide')
  expect(toggle.attributes('aria-pressed')).toBe('false')
  await toggle.trigger('click')
  expect(main.classes()).toContain('is-video-wide')
  expect(toggle.attributes('aria-pressed')).toBe('true')
  expect(storage.get('foreign.media.videoWide')).toBe('1')
  wrapper.unmount()
  const again = page()
  expect(again.get('.media-show-main').classes()).toContain('is-video-wide')
  again.unmount()
})

it('collapses and expands the transcript next to the edit button', async () => {
  const wrapper = page()
  const actions = wrapper.get('.transcript-panel-actions')
  const toggle = actions.get('.transcript-collapse-toggle')
  expect(toggle.element.previousElementSibling?.textContent?.trim()).toBe(
    'Edit',
  )
  const body = wrapper.get('#media-transcript-body')
  expect((body.element as HTMLElement).style.display).toBe('')
  expect(toggle.attributes('aria-expanded')).toBe('true')
  await toggle.trigger('click')
  expect((body.element as HTMLElement).style.display).toBe('none')
  expect(toggle.attributes('aria-expanded')).toBe('false')
  expect(wrapper.get('.media-show-main').classes()).toContain(
    'is-transcript-collapsed',
  )
  expect(storage.get('foreign.media.transcriptCollapsed')).toBe('1')
  await toggle.trigger('click')
  expect((body.element as HTMLElement).style.display).toBe('')
  wrapper.unmount()
})
