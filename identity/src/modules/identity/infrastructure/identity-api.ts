import type { IdentitySession } from '../application/session'
import type {
  ClientInput,
  ClientList,
  ClientResult,
  ClientUpdate,
  IdentityUser,
  UserList,
} from '../domain/models'
import { ApiError, fetchJson } from './transport'
export class IdentityApi {
  constructor(
    private readonly session: IdentitySession,
    private readonly transport: typeof fetch = fetch,
  ) {}
  private async request<T>(path: string, method = 'GET', body?: unknown): Promise<T> {
    const send = (token: string) =>
      fetchJson<T>(
        `${this.session.settings.baseUrl}/auth${path}`,
        {
          method,
          headers: {
            Authorization: `Bearer ${token}`,
            ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
          },
          ...(body === undefined ? {} : { body: JSON.stringify(body) }),
        },
        this.transport,
      )
    try {
      return await send(await this.session.accessToken())
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 401) throw error
      return send(await this.session.accessToken(true))
    }
  }
  clients(after?: string): Promise<ClientList> {
    return this.request(
      `/admin/sso-clients?limit=25${after ? `&after=${encodeURIComponent(after)}` : ''}`,
    )
  }
  createClient(input: ClientInput): Promise<ClientResult> {
    return this.request('/admin/sso-clients', 'POST', input)
  }
  updateClient(id: string, input: ClientUpdate): Promise<ClientResult> {
    return this.request(`/admin/sso-clients/${encodeURIComponent(id)}`, 'PATCH', input)
  }
  rotateSecret(id: string, version: number): Promise<ClientResult> {
    return this.request(`/admin/sso-clients/${encodeURIComponent(id)}/rotate-secret`, 'POST', {
      version,
    })
  }
  users(after?: string): Promise<UserList> {
    return this.request(
      `/admin/users?limit=25${after ? `&after=${encodeURIComponent(after)}` : ''}`,
    )
  }
  setUserEnabled(id: string, enabled: boolean): Promise<IdentityUser> {
    return this.request(`/admin/users/${encodeURIComponent(id)}`, 'PATCH', { enabled })
  }
  revokeUser(id: string): Promise<{ success: boolean }> {
    return this.request(`/admin/users/${encodeURIComponent(id)}/revoke`, 'POST')
  }
}
