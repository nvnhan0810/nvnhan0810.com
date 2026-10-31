import { webcrypto } from 'node:crypto'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ForeignSession } from '../application/session'
import { authorizationUrl } from '../application/oauth'
const ROOT_EMAIL = 'learner@example.com'
const settings = { baseUrl: 'https://api.example', clientId: 'admin' }
const tokens = {
  access_token: 'access-credential',
  token_type: 'Bearer',
  expires_in: 900,
  scope: 'profile',
}
function json(data: unknown, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}
async function callback() {
  const url = new URL(
    await authorizationUrl(
      settings.baseUrl,
      settings.clientId,
      location.origin,
      sessionStorage,
    ),
  )
  return new URL(
    `${location.origin}/auth/callback?code=one-time-code&state=${url.searchParams.get('state')}`,
  )
}
describe('Identity session', () => {
  beforeEach(() => {
    sessionStorage.clear()
    localStorage.clear()
    vi.stubGlobal('crypto', webcrypto)
    history.replaceState({}, '', '/')
  })
  it('exchanges PKCE, verifies profile and keeps tokens out of browser storage', async () => {
    const transport = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(json(tokens))
      .mockResolvedValueOnce(
        json({ id: 'root', name: 'Root', email: ROOT_EMAIL, avatar: null }),
      )
    const session = new ForeignSession(settings, transport)
    await session.completeLogin(await callback())
    expect(session.state.user?.email).toBe(ROOT_EMAIL)
    expect(location.search).toBe('')
    expect(await session.accessToken()).toBe(tokens.access_token)
    expect(sessionStorage.length).toBe(0)
    expect(localStorage.length).toBe(0)
    const body = transport.mock.calls[0]?.[1]?.body as URLSearchParams
    expect(body.get('code_verifier')).toHaveLength(43)
    expect(body.has('client_secret')).toBe(false)
  })
  it('restores a new page through the HttpOnly cookie endpoint', async () => {
    const transport = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(
        json({ ...tokens, access_token: 'restored-access' }),
      )
      .mockResolvedValueOnce(
        json({ id: 'root', name: 'Root', email: ROOT_EMAIL }),
      )
    const session = new ForeignSession(settings, transport)
    await session.restore()
    expect(session.state.user?.email).toBe(ROOT_EMAIL)
    expect(await session.accessToken()).toBe('restored-access')
    expect(transport.mock.calls[0]?.[0]).toBe(
      `${settings.baseUrl}/auth/sso/token`,
    )
    const init = transport.mock.calls[0]?.[1]
    expect(init?.credentials).toBe('include')
    expect((init?.body as URLSearchParams).has('refresh_token')).toBe(false)
    expect(sessionStorage.length).toBe(0)
  })
  it('stays logged out when the cookie is absent or revoked', async () => {
    const session = new ForeignSession(
      settings,
      vi
        .fn<typeof fetch>()
        .mockResolvedValueOnce(json({ error: 'invalid_grant' }, 401)),
    )
    await session.restore()
    expect(session.state.user).toBeNull()
    await expect(session.accessToken()).rejects.toThrow('invalid_grant')
  })
  it('reports network errors without authenticating', async () => {
    const session = new ForeignSession(
      settings,
      vi.fn<typeof fetch>().mockRejectedValueOnce(new TypeError('offline')),
    )
    await expect(session.restore()).rejects.toThrow('network_error')
    expect(session.state.user).toBeNull()
  })
  it('serializes concurrent refresh calls so a rotated token is only used once', async () => {
    const transport = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(json({ ...tokens, expires_in: 1 }))
      .mockResolvedValueOnce(
        json({ id: 'root', name: 'Root', email: ROOT_EMAIL }),
      )
    const session = new ForeignSession(settings, transport)
    await session.completeLogin(await callback())
    let resolveRefresh: ((response: Response) => void) | undefined
    transport.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          resolveRefresh = resolve
        }),
    )
    const first = session.accessToken()
    const second = session.accessToken()
    expect(transport).toHaveBeenCalledTimes(3)
    resolveRefresh?.(
      json({
        ...tokens,
        access_token: 'new-access',
        refresh_token: 'new-refresh',
      }),
    )
    expect(await Promise.all([first, second])).toEqual([
      'new-access',
      'new-access',
    ])
  })
  it('does not repeat a failed refresh and clears the expired session', async () => {
    const transport = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(json({ ...tokens, expires_in: 1 }))
      .mockResolvedValueOnce(
        json({ id: 'root', name: 'Root', email: ROOT_EMAIL }),
      )
      .mockResolvedValueOnce(json({ error: 'invalid_grant' }, 401))
    const session = new ForeignSession(settings, transport)
    await session.completeLogin(await callback())
    await expect(session.accessToken()).rejects.toThrow('invalid_grant')
    expect(transport).toHaveBeenCalledTimes(3)
    expect(session.state.user).toBeNull()
    expect(sessionStorage.length).toBe(0)
  })
  it('clears local credentials even when logout cannot reach Identity', async () => {
    const transport = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(json(tokens))
      .mockResolvedValueOnce(
        json({ id: 'root', name: 'Root', email: ROOT_EMAIL }),
      )
    const session = new ForeignSession(settings, transport)
    await session.completeLogin(await callback())
    transport.mockRejectedValueOnce(new TypeError('offline'))
    await expect(session.logout()).rejects.toThrow()
    expect(session.state.user).toBeNull()
    expect(sessionStorage.length).toBe(0)
    await expect(session.accessToken()).rejects.toThrow()
  })
})
