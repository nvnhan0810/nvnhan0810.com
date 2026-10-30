import { shallowReactive } from 'vue'
import { ROOT_EMAIL } from '../domain/models'
import type { Tokens, UserProfile } from '../domain/models'
import { ApiError, fetchJson } from '../infrastructure/transport'
import { authorizationUrl, consumeTransaction } from './oauth'
export interface IdentitySettings {
  baseUrl: string
  clientId: string
}
export class IdentitySession {
  readonly state = shallowReactive<{ user: UserProfile | null; busy: boolean }>({
    user: null,
    busy: false,
  })
  private tokens: Tokens | null = null
  private expiresAt = 0
  private refreshing: Promise<string> | null = null
  private epoch = 0
  constructor(
    readonly settings: IdentitySettings,
    private readonly transport: typeof fetch = fetch,
  ) {}
  async login(): Promise<void> {
    this.state.busy = true
    try {
      window.location.assign(
        await authorizationUrl(
          this.settings.baseUrl,
          this.settings.clientId,
          window.location.origin,
          sessionStorage,
        ),
      )
    } catch (error) {
      this.state.busy = false
      throw error
    }
  }
  private install(tokens: Tokens): void {
    if (
      typeof tokens?.access_token !== 'string' ||
      typeof tokens.refresh_token !== 'string' ||
      tokens.token_type?.toLowerCase() !== 'bearer' ||
      !Number.isFinite(tokens.expires_in) ||
      tokens.expires_in <= 0
    )
      throw new Error('Identity trả về token không hợp lệ.')
    this.tokens = tokens
    this.expiresAt = Date.now() + tokens.expires_in * 1000
  }
  private tokenRequest(body: URLSearchParams): Promise<Tokens> {
    body.set('client_id', this.settings.clientId)
    return fetchJson(
      `${this.settings.baseUrl}/auth/sso/token`,
      { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body },
      this.transport,
    )
  }
  async completeLogin(url: URL): Promise<void> {
    this.state.busy = true
    const epoch = ++this.epoch
    try {
      const transaction = consumeTransaction(
        sessionStorage,
        url.searchParams.get('state'),
        window.location.origin,
      )
      // Remove authorization credentials from the visible URL before HTTP calls.
      window.history.replaceState({}, '', '/auth/callback')
      if (url.searchParams.has('error'))
        throw new Error('Đăng nhập Google chưa hoàn tất. Vui lòng thử lại.')
      const code = url.searchParams.get('code')
      if (!code) throw new Error('Identity không trả về authorization code.')
      const tokens = await this.tokenRequest(
        new URLSearchParams({
          grant_type: 'authorization_code',
          code,
          redirect_uri: transaction.redirectUri,
          code_verifier: transaction.verifier,
        }),
      )
      if (epoch !== this.epoch) return
      this.install(tokens)
      const user = await fetchJson<UserProfile>(
        `${this.settings.baseUrl}/auth/me`,
        { headers: { Authorization: `Bearer ${tokens.access_token}` } },
        this.transport,
      )
      if (epoch !== this.epoch) return
      if (user.email?.toLowerCase() !== ROOT_EMAIL) {
        await this.logout()
        throw new Error(
          'Trang quản trị chỉ dành cho system root. Hãy đăng nhập bằng tài khoản được cấp quyền.',
        )
      }
      this.state.user = user
    } catch (error) {
      if (epoch === this.epoch) {
        this.tokens = null
        this.state.user = null
      }
      throw error
    } finally {
      this.state.busy = false
    }
  }
  async accessToken(forceRefresh = false): Promise<string> {
    if (this.refreshing) return this.refreshing
    if (!this.tokens) throw new ApiError(401, 'invalid_grant')
    if (!forceRefresh && this.expiresAt > Date.now() + 30000) return this.tokens.access_token
    const epoch = this.epoch
    const refreshing = this.tokenRequest(
      new URLSearchParams({
        grant_type: 'refresh_token',
        refresh_token: this.tokens.refresh_token,
      }),
    )
      .then((tokens) => {
        if (epoch !== this.epoch) throw new ApiError(401, 'invalid_grant')
        this.install(tokens)
        return tokens.access_token
      })
      .catch((error) => {
        if (
          epoch === this.epoch &&
          error instanceof ApiError &&
          [400, 401, 403].includes(error.status)
        ) {
          this.tokens = null
          this.state.user = null
        }
        throw error
      })
      .finally(() => {
        if (this.refreshing === refreshing) this.refreshing = null
      })
    this.refreshing = refreshing
    return refreshing
  }
  async logout(): Promise<void> {
    const token = this.tokens?.refresh_token
    this.epoch++
    this.refreshing = null
    this.tokens = null
    this.expiresAt = 0
    this.state.user = null
    if (token) {
      await fetchJson(
        `${this.settings.baseUrl}/auth/sso/revoke`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            client_id: this.settings.clientId,
            token,
            token_type_hint: 'refresh_token',
          }),
        },
        this.transport,
      )
    }
  }
}
