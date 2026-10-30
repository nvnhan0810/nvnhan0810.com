import { IdentitySession } from '../modules/identity/application/session'
import { IdentityApi } from '../modules/identity/infrastructure/identity-api'
const baseUrl = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080').replace(/\/+$/, '')
const url = new URL(baseUrl)
if (
  !['http:', 'https:'].includes(url.protocol) ||
  url.pathname !== '/' ||
  url.search ||
  url.hash ||
  url.username ||
  url.password
)
  throw new Error('VITE_API_BASE_URL phải là một HTTP(S) origin.')
export const session = new IdentitySession({
  baseUrl,
  clientId: import.meta.env.VITE_SSO_CLIENT_ID || 'identity',
})
export const api = new IdentityApi(session)
