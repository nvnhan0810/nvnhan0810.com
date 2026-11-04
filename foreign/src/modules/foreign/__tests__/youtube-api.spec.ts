import { expect, it, vi } from 'vitest'
import { foreignRequest } from '../infrastructure/foreign-api'
import { youtubeApi } from '../infrastructure/youtube-api'

vi.mock('../infrastructure/foreign-api', () => ({ foreignRequest: vi.fn() }))

it('sends the active duration limit with the channel and cursor', async () => {
  const signal = new AbortController().signal
  await youtubeApi.videos('channel-id', 'next-page', signal, 600)
  const [path, options] = vi.mocked(foreignRequest).mock.calls.at(-1)!
  const query = new URL(path, 'https://local.test').searchParams
  expect(query.get('max_duration_seconds')).toBe('600')
  expect(query.get('channel_id')).toBe('channel-id')
  expect(query.get('cursor')).toBe('next-page')
  expect(options?.signal).toBe(signal)
})

it('omits the duration limit when the filter is disabled', async () => {
  await youtubeApi.videos('', null, new AbortController().signal, null)
  const [path] = vi.mocked(foreignRequest).mock.calls.at(-1)!
  expect(
    new URL(path, 'https://local.test').searchParams.has(
      'max_duration_seconds',
    ),
  ).toBe(false)
})
