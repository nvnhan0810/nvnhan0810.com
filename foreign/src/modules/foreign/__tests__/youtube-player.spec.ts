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
