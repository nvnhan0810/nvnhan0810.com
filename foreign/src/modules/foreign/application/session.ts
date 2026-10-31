import { shallowReactive } from 'vue'
import type { Tokens, UserProfile } from '../domain/models'
import { ApiError, fetchJson } from '../infrastructure/transport'
import { authorizationUrl, consumeTransaction } from './oauth'
export interface ForeignSettings {
  baseUrl: string
  clientId: string
}
export class ForeignSession {
  readonly state = shallowReactive<{ user: UserProfile | null; busy: boolean }>(
    {
      user: null,
      busy: false,
    },
  )
  private tokens: Tokens | null = null
  private expiresAt = 0
  private refreshing: Promise<string> | null = null
  private epoch = 0
  private clear(): void {
    this.tokens = null
    this.expiresAt = 0
    this.state.user = null
  }
  constructor(
    readonly settings: ForeignSettings,
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
      tokens.token_type?.toLowerCase() !== 'bearer' ||
      !Number.isFinite(tokens.expires_in) ||
      tokens.expires_in <= 0
    )
      throw new Error('Identity trả về token không hợp lệ.')
    // The shared OAuth response includes refresh_token for native clients.
    // This browser client relies on the HttpOnly cookie instead.
    this.tokens = {
      access_token: tokens.access_token,
      token_type: tokens.token_type,
      expires_in: tokens.expires_in,
      scope: tokens.scope,
    }
    this.expiresAt = Date.now() + tokens.expires_in * 1000
  }
  async restore(): Promise<void> {
    this.state.busy = true
    const epoch = ++this.epoch
    try {
      const tokens = await this.tokenRequest(
        new URLSearchParams({ grant_type: 'refresh_token' }),
      )
      if (epoch !== this.epoch) return
      this.install(tokens)
      const user = await fetchJson<UserProfile>(
        `${this.settings.baseUrl}/auth/me`,
        { headers: { Authorization: `Bearer ${tokens.access_token}` } },
        this.transport,
      )
      if (epoch !== this.epoch) return
      this.state.user = user
    } catch (error) {
      if (
        epoch === this.epoch &&
        error instanceof ApiError &&
        [400, 401, 403].includes(error.status)
      )
        this.clear()
      if (
        !(error instanceof ApiError) ||
        error.status !== 401 ||
        error.code !== 'invalid_grant'
      )
        throw error
    } finally {
      this.state.busy = false
    }
  }
  private tokenRequest(body: URLSearchParams): Promise<Tokens> {
    body.set('client_id', this.settings.clientId)
    return this.cookieRequest<Tokens>('/auth/sso/token', body)
  }
  private cookieRequest<T>(path: string, body: URLSearchParams): Promise<T> {
    const send = () =>
      fetchJson<T>(
        `${this.settings.baseUrl}${path}`,
        {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body,
        },
        this.transport,
      )
    // Cookie rotation is shared by tabs. Serialize it where Web Locks is available.
    if (typeof navigator !== 'undefined' && navigator.locks) {
      return navigator.locks.request(
        `identity-session:${this.settings.baseUrl}:${this.settings.clientId}`,
        send,
      )
    }
    return send()
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
        throw new Error('Đăng nhập chưa hoàn tất. Vui lòng thử lại.')
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
      this.state.user = user
    } catch (error) {
      if (epoch === this.epoch) {
        this.clear()
      }
      throw error
    } finally {
      this.state.busy = false
    }
  }
  async accessToken(forceRefresh = false): Promise<string> {
    if (this.refreshing) return this.refreshing
    if (!this.tokens) throw new ApiError(401, 'invalid_grant')
    if (!forceRefresh && this.expiresAt > Date.now() + 30000)
      return this.tokens.access_token
    const epoch = this.epoch
    const refreshing = this.tokenRequest(
      new URLSearchParams({
        grant_type: 'refresh_token',
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
          this.clear()
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
    this.epoch++
    this.refreshing = null
    this.clear()
    await this.cookieRequest(
      '/auth/sso/revoke',
      new URLSearchParams({ client_id: this.settings.clientId }),
    )
  }
}
