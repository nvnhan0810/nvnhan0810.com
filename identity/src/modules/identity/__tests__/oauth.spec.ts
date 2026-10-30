import { webcrypto } from 'node:crypto'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authorizationUrl, consumeTransaction, pkceChallenge } from '../application/oauth'

describe('OAuth transaction boundary', () => {
  beforeEach(() => {
    sessionStorage.clear()
    vi.stubGlobal('crypto', webcrypto)
  })
  it('uses the RFC 7636 S256 test vector', async () => {
    expect(await pkceChallenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk')).toBe(
      'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
    )
  })
  it('creates a public-client authorization request without audience or resource', async () => {
    const url = new URL(
      await authorizationUrl('https://api.example', 'admin', location.origin, sessionStorage),
    )
    expect(url.pathname).toBe('/auth/sso/authorize')
    expect(url.searchParams.get('code_challenge_method')).toBe('S256')
    expect(url.searchParams.get('redirect_uri')).toBe(`${location.origin}/auth/callback`)
    expect(url.searchParams.has('resource')).toBe(false)
    expect(url.searchParams.has('client_secret')).toBe(false)
    const transaction = consumeTransaction(
      sessionStorage,
      url.searchParams.get('state'),
      location.origin,
    )
    expect(await pkceChallenge(transaction.verifier)).toBe(url.searchParams.get('code_challenge'))
    expect(() => consumeTransaction(sessionStorage, transaction.state, location.origin)).toThrow()
  })
  it('rejects callback state mismatch and consumes the transaction', async () => {
    await authorizationUrl('https://api.example', 'admin', location.origin, sessionStorage)
    expect(() => consumeTransaction(sessionStorage, 'attacker-state', location.origin)).toThrow()
    expect(sessionStorage.length).toBe(0)
  })
  it('rejects an expired login attempt', async () => {
    const url = new URL(
      await authorizationUrl('https://api.example', 'admin', location.origin, sessionStorage),
    )
    const clock = vi.spyOn(Date, 'now').mockReturnValue(Date.now() + 600001)
    expect(() =>
      consumeTransaction(sessionStorage, url.searchParams.get('state'), location.origin),
    ).toThrow()
    clock.mockRestore()
  })
})
