import { session } from '../app/services'
import { ApiError } from './transport'
export class WalletsError extends Error {
  constructor(
    message: string,
    readonly errors: Record<string, string>,
  ) {
    super(message)
  }
}
export async function request<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const send = async (refresh = false) =>
    fetch(`${session.settings.baseUrl}/wallets${path}`, {
      ...init,
      cache: 'no-store',
      signal: init.signal ?? AbortSignal.timeout(15000),
      headers: {
        ...init.headers,
        Authorization: `Bearer ${await session.accessToken(refresh)}`,
      },
    })
  let response = await send()
  if (response.status === 401) response = await send(true)
  const body = await response.json()
  if (!response.ok) {
    if (response.status === 401) throw new ApiError(401, 'invalid_grant')
    throw new WalletsError(
      body.error ?? 'Không thể thực hiện yêu cầu.',
      body.errors ?? { request: body.error ?? 'Không thể thực hiện yêu cầu.' },
    )
  }
  return body as T
}
export function action<T>(path: string, method: string, data: unknown) {
  return request<T>('/actions', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ path, method, data }),
  })
}
