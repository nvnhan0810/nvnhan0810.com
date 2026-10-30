import { describe, expect, it } from 'vitest'
import { clientInput } from '../application/client-form'
import type { ClientDraft } from '../application/client-form'
function draft(): ClientDraft {
  return {
    client_id: 'wallets-web',
    name: ' Wallets ',
    domain: '',
    client_type: 'public',
    redirects: 'https://wallets.example/callback',
    scopes: 'profile\nwallets:read',
    enabled: true,
  }
}
describe('client editing contract', () => {
  it('produces the Identity create payload without removed audience properties', () => {
    expect(clientInput(draft())).toEqual({
      client_id: 'wallets-web',
      name: 'Wallets',
      domain: null,
      client_type: 'public',
      redirect_uris: ['https://wallets.example/callback'],
      scopes: ['profile', 'wallets:read'],
      enabled: true,
    })
  })
  it('rejects duplicate scopes, wildcard and unsafe callbacks before submitting', () => {
    expect(() => clientInput({ ...draft(), scopes: 'profile profile' })).toThrow()
    for (const redirects of [
      'https://*.example/callback',
      'http://external.example/callback',
      'https://example/cb#fragment',
      'https://user:pass@example/cb',
    ]) {
      expect(() => clientInput({ ...draft(), redirects })).toThrow()
    }
    expect(
      clientInput({ ...draft(), redirects: 'http://127.0.0.1:5174/auth/callback' }).redirect_uris,
    ).toHaveLength(1)
  })
})
