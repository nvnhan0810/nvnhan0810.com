import { withLoading } from '../../../shared/loading'
import { session } from '../../../app/services'
import { ApiError, fetchJson } from './transport'
export async function foreignRequest<T>(
  path: string,
  init: RequestInit = {},
  options: { background?: boolean } = {},
): Promise<T> {
  return options.background
    ? request<T>(path, init)
    : withLoading(() => request<T>(path, init))
}
async function request<T>(path: string, init: RequestInit): Promise<T> {
  const send = (token: string) =>
    fetchJson<T>(`${session.settings.baseUrl}/foreign${path}`, {
      ...init,
      headers: { ...init.headers, Authorization: `Bearer ${token}` },
    })
  const token = await session.accessToken()
  try {
    return await send(token)
  } catch (error) {
    if (!(error instanceof ApiError) || error.status !== 401) throw error
    return send(await session.accessToken(true))
  }
}
