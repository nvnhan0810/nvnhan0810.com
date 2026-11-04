import { foreignRequest } from './foreign-api'

export interface YoutubeChannel {
  channel_id: string
  title: string
  thumbnail_url: string
}
export interface YoutubeVideo {
  video_id: string
  title: string
  channel_id: string
  channel_title: string
  thumbnail_url: string
  duration: string | null
  published_at: string | null
  media_id: number | null
}
export interface YoutubeVideoPage {
  items: YoutubeVideo[]
  next_cursor: string | null
}
function json(data: unknown): RequestInit {
  return {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  }
}
export const youtubeApi = {
  channels: () =>
    foreignRequest<{ items: YoutubeChannel[] }>('/youtube/channels'),
  saveChannel: (url: string) =>
    foreignRequest('/youtube/channels', json({ url })),
  removeChannel: (id: string) =>
    foreignRequest(`/youtube/channels/${encodeURIComponent(id)}`, {
      method: 'DELETE',
    }),
  videos: (
    channel: string,
    cursor: string | null,
    signal: AbortSignal,
    maxDurationSeconds: number | null = null,
  ) => {
    const query = new URLSearchParams()
    if (channel) query.set('channel_id', channel)
    if (cursor) query.set('cursor', cursor)
    if (maxDurationSeconds !== null)
      query.set('max_duration_seconds', String(maxDurationSeconds))
    return foreignRequest<YoutubeVideoPage>(`/youtube/videos?${query}`, {
      signal,
    })
  },
  addListening: (id: string, difficulty: string) =>
    foreignRequest<{ media_id: number; captions_queued: boolean }>(
      `/youtube/videos/${encodeURIComponent(id)}/listening`,
      json({ difficulty }),
    ),
}
