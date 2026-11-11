export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
  ) {
    super(code)
    this.name = 'ApiError'
  }
}
export async function fetchJson<T>(
  url: string,
  init: RequestInit = {},
  transport: typeof fetch = fetch,
): Promise<T> {
  let response: Response
  try {
    response = await transport(url, {
      ...init,
      signal: init.signal ?? AbortSignal.timeout(15000),
      cache: 'no-store',
    })
  } catch {
    throw new ApiError(0, 'network_error')
  }
  let data: unknown
  try {
    data = await response.json()
  } catch {
    data = null
  }
  if (!response.ok) {
    const code =
      typeof data === 'object' &&
      data !== null &&
      'error' in data &&
      typeof data.error === 'string'
        ? data.error
        : `http_${response.status}`
    throw new ApiError(response.status, code)
  }
  return data as T
}
export function errorMessage(error: unknown): string {
  if (!(error instanceof ApiError))
    return error instanceof Error
      ? error.message
      : 'Something went wrong. Please try again.'
  const messages: Record<string, string> = {
    network_error:
      'Unable to connect to the Identity API. Check your network, API address, and CORS settings.',
    invalid_client:
      'The sign-in client is not registered, is disabled, or is configured incorrectly.',
    invalid_grant: 'Your session has expired. Please sign in again.',
    invalid_scope:
      'The client has not been granted the required scope. Check its settings in Identity.',
    access_denied:
      'Your account does not have permission to perform this action.',
    conflict: 'This client ID already exists. Please choose another ID.',
    version_conflict:
      'The data was changed elsewhere. Close the window, reload the list, and try again.',
    invalid_client_metadata:
      'The client details are invalid. Check the callback and scopes.',
    system_root_protected:
      'The system root account is protected and cannot be locked.',
    server_error: 'Identity is experiencing a problem. Please try again later.',
    invalid_request:
      'The request is invalid. Check the client and callback settings.',
  }
  return (
    messages[error.code] ??
    (error.status === 429
      ? 'Too many requests. Please wait a moment and try again.'
      : `Request failed (${error.status}).`)
  )
}
