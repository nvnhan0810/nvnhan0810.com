import { flushPromises, mount } from '@vue/test-utils'
import { expect, it, vi } from 'vitest'
import Player from '@/Components/media/YouTubeSyncedPlayer.vue'
import { createYoutubePlayer } from '@/infrastructure/youtube-iframe-api'

vi.mock('@/infrastructure/youtube-iframe-api', () => ({
  YoutubePlayerState: { Playing: 1 },
  createYoutubePlayer: vi.fn(),
}))

it('supports playback, bounded seeking and source link without fullscreen', async () => {
  const player = {
    getCurrentTime: () => 5,
    getDuration: () => 100,
    seekTo: vi.fn(),
    playVideo: vi.fn(),
    pauseVideo: vi.fn(),
    destroy: vi.fn(),
    getPlayerState: () => 2,
  }
  vi.mocked(createYoutubePlayer).mockImplementation(async (options) => {
    options.onReady?.(player)
    return player
  })
  const wrapper = mount(Player, {
    props: { videoId: 'example', title: 'Video' },
  })
  await vi.dynamicImportSettled()
  expect(wrapper.find('.simple-youtube-interaction-shield').exists()).toBe(true)
  await wrapper.get('[aria-label="Play"]').trigger('click')
  expect(player.playVideo).toHaveBeenCalled()
  expect(wrapper.find('.simple-youtube-interaction-shield').exists()).toBe(true)
  await wrapper.get('[aria-label="Pause"]').trigger('click')
  expect(player.pauseVideo).toHaveBeenCalled()
  expect(wrapper.find('.simple-youtube-interaction-shield').exists()).toBe(true)
  await wrapper.get('[aria-label="Rewind 10 seconds"]').trigger('click')
  expect(player.seekTo).toHaveBeenLastCalledWith(0, true)
  await wrapper.get('input').setValue('95')
  await wrapper.get('[aria-label="Forward 10 seconds"]').trigger('click')
  expect(player.seekTo).toHaveBeenLastCalledWith(100, true)
  expect(wrapper.get('a').attributes('href')).toBe(
    'https://www.youtube.com/watch?v=example',
  )
  expect(wrapper.find('[aria-label="Fullscreen"]').exists()).toBe(false)
  wrapper.unmount()
  expect(player.destroy).toHaveBeenCalled()
})

it('retries caption suppression after playback starts and cancels retries on unmount', async () => {
  vi.useFakeTimers()
  const player = {
    getCurrentTime: () => 0,
    getDuration: () => 100,
    seekTo: vi.fn(),
    playVideo: vi.fn(),
    pauseVideo: vi.fn(),
    destroy: vi.fn(),
    getPlayerState: () => 1,
    unloadModule: vi.fn(),
  }
  let options: Parameters<typeof createYoutubePlayer>[0] | undefined
  vi.mocked(createYoutubePlayer).mockImplementation(async (params) => {
    options = params
    params.onReady?.(player)
    return player
  })
  const wrapper = mount(Player, {
    props: { videoId: 'example', title: 'Video' },
  })
  try {
    await flushPromises()
    expect(player.unloadModule).toHaveBeenCalledWith('captions')
    player.unloadModule.mockClear()
    options!.onStateChange?.(1, player)
    expect(player.unloadModule).toHaveBeenCalledTimes(1)
    vi.advanceTimersByTime(2500)
    expect(player.unloadModule).toHaveBeenCalledTimes(4)
    options!.onStateChange?.(1, player)
    wrapper.unmount()
    const calls = player.unloadModule.mock.calls.length
    vi.advanceTimersByTime(3000)
    expect(player.unloadModule).toHaveBeenCalledTimes(calls)
  } finally {
    if (wrapper.exists()) wrapper.unmount()
    vi.useRealTimers()
  }
})

it('supports keyboard playback and bounded ten-second seeking, ignoring typing and cleaning up', async () => {
  const player = {
    getCurrentTime: () => 5,
    getDuration: () => 100,
    seekTo: vi.fn(),
    playVideo: vi.fn(),
    pauseVideo: vi.fn(),
    destroy: vi.fn(),
    getPlayerState: () => 2,
  }
  vi.mocked(createYoutubePlayer).mockImplementation(async (options) => {
    options.onReady?.(player)
    return player
  })
  const wrapper = mount(Player, {
    props: { videoId: 'example', title: 'Video' },
  })
  const press = (code: string, options: KeyboardEventInit = {}) => {
    const event = new KeyboardEvent('keydown', {
      code,
      cancelable: true,
      ...options,
    })
    window.dispatchEvent(event)
    return event
  }
  try {
    await flushPromises()
    expect(press('Space').defaultPrevented).toBe(true)
    press('Space', { repeat: true })
    expect(player.pauseVideo).not.toHaveBeenCalled()
    press('Space')
    expect(player.pauseVideo).toHaveBeenCalledTimes(1)
    press('ArrowLeft')
    expect(player.seekTo).toHaveBeenLastCalledWith(0, true)
    press('ArrowRight')
    expect(player.seekTo).toHaveBeenLastCalledWith(10, true)
    for (let i = 0; i < 12; i++) press('ArrowRight')
    expect(player.seekTo).toHaveBeenLastCalledWith(100, true)
    expect(player.playVideo).toHaveBeenCalledTimes(1)
    player.seekTo.mockClear()
    press('ArrowRight', { ctrlKey: true })
    press('ArrowRight', { isComposing: true })
    for (const tag of ['input', 'textarea', 'select']) {
      const input = document.createElement(tag)
      document.body.append(input)
      try {
        for (const code of ['Space', 'ArrowLeft', 'ArrowRight']) {
          const event = new KeyboardEvent('keydown', {
            code,
            bubbles: true,
            cancelable: true,
          })
          input.dispatchEvent(event)
          expect(event.defaultPrevented).toBe(false)
        }
      } finally {
        input.remove()
      }
    }
    expect(player.seekTo).not.toHaveBeenCalled()
  } finally {
    wrapper.unmount()
  }
  expect(press('ArrowRight').defaultPrevented).toBe(false)
  press('Space')
  expect(player.seekTo).not.toHaveBeenCalled()
  expect(player.playVideo).toHaveBeenCalledTimes(1)
})
