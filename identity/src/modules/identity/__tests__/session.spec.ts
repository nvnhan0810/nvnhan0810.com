import { webcrypto } from 'node:crypto'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { IdentitySession } from '../application/session'
import { authorizationUrl } from '../application/oauth'
import { ROOT_EMAIL } from '../domain/models'
const settings = { baseUrl: 'https://api.example', clientId: 'admin' }
const tokens = {
  access_token: 'access-credential',
  refresh_token: 'refresh-credential',
  token_type: 'Bearer',
  expires_in: 900,
  scope: 'profile identity.clients.manage',
}
function json(data: unknown, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}
async function callback() {
  const url = new URL(
    await authorizationUrl(settings.baseUrl, settings.clientId, location.origin, sessionStorage),
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
  it('exchanges PKCE, verifies profile and keeps bearer tokens out of browser storage', async () => {
    const transport = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(json(tokens))
      .mockResolvedValueOnce(json({ id: 'root', name: 'Root', email: ROOT_EMAIL, avatar: null }))
    const session = new IdentitySession(settings, transport)
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
  it('serializes concurrent refresh calls so a rotated token is only used once', async () => {
    const transport = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(json({ ...tokens, expires_in: 1 }))
      .mockResolvedValueOnce(json({ id: 'root', name: 'Root', email: ROOT_EMAIL }))
    const session = new IdentitySession(settings, transport)
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
    resolveRefresh?.(json({ ...tokens, access_token: 'new-access', refresh_token: 'new-refresh' }))
    expect(await Promise.all([first, second])).toEqual(['new-access', 'new-access'])
  })
  it('revokes and rejects a non-root Google profile', async () => {
    const transport = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(json(tokens))
      .mockResolvedValueOnce(json({ id: 'user', name: 'User', email: 'other@example.com' }))
      .mockResolvedValueOnce(json(null))
    const session = new IdentitySession(settings, transport)
    await expect(session.completeLogin(await callback())).rejects.toThrow('system root')
    expect(session.state.user).toBeNull()
    await expect(session.accessToken()).rejects.toThrow()
    expect(transport.mock.calls[2]?.[0]).toBe('https://api.example/auth/sso/revoke')
  })
  it('clears local credentials even when logout cannot reach Identity', async () => {
    const transport = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(json(tokens))
      .mockResolvedValueOnce(json({ id: 'root', name: 'Root', email: ROOT_EMAIL }))
    const session = new IdentitySession(settings, transport)
    await session.completeLogin(await callback())
    transport.mockRejectedValueOnce(new TypeError('offline'))
    await expect(session.logout()).rejects.toThrow()
    expect(session.state.user).toBeNull()
    await expect(session.accessToken()).rejects.toThrow()
  })
})
