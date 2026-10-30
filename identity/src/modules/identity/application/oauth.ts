const TRANSACTION_KEY = 'identity-admin.oauth.transaction'
export interface OAuthTransaction {
  state: string
  verifier: string
  redirectUri: string
  createdAt: number
}
export function base64Url(bytes: Uint8Array): string {
  return btoa(String.fromCharCode(...bytes))
    .replaceAll('+', '-')
    .replaceAll('/', '_')
    .replace(/=+$/, '')
}
export function randomToken(): string {
  return base64Url(crypto.getRandomValues(new Uint8Array(32)))
}
export async function pkceChallenge(verifier: string): Promise<string> {
  return base64Url(
    new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier))),
  )
}
export async function authorizationUrl(
  baseUrl: string,
  clientId: string,
  origin: string,
  storage: Storage,
): Promise<string> {
  const transaction: OAuthTransaction = {
    state: randomToken(),
    verifier: randomToken(),
    redirectUri: `${origin}/auth/callback`,
    createdAt: Date.now(),
  }
  const challenge = await pkceChallenge(transaction.verifier)
  storage.setItem(TRANSACTION_KEY, JSON.stringify(transaction))
  const url = new URL(`${baseUrl}/auth/sso/authorize`)
  url.search = new URLSearchParams({
    response_type: 'code',
    client_id: clientId,
    redirect_uri: transaction.redirectUri,
    scope: 'profile identity.clients.manage',
    state: transaction.state,
    code_challenge: challenge,
    code_challenge_method: 'S256',
  }).toString()
  return url.toString()
}
export function consumeTransaction(
  storage: Storage,
  state: string | null,
  origin: string,
): OAuthTransaction {
  const stored = storage.getItem(TRANSACTION_KEY)
  storage.removeItem(TRANSACTION_KEY)
  let parsed: unknown
  try {
    parsed = stored ? JSON.parse(stored) : null
  } catch {
    parsed = null
  }
  if (!parsed || typeof parsed !== 'object')
    throw new Error('Không tìm thấy giao dịch đăng nhập. Hãy đăng nhập lại từ trang này.')
  const transaction = parsed as Partial<OAuthTransaction>
  if (
    !state ||
    transaction.state !== state ||
    typeof transaction.verifier !== 'string' ||
    !/^[A-Za-z0-9_-]{43}$/.test(transaction.verifier) ||
    transaction.redirectUri !== `${origin}/auth/callback` ||
    typeof transaction.createdAt !== 'number' ||
    Date.now() - transaction.createdAt > 600000 ||
    transaction.createdAt > Date.now() + 30000
  ) {
    throw new Error('Giao dịch đăng nhập không hợp lệ hoặc đã hết hạn. Vui lòng đăng nhập lại.')
  }
  return transaction as OAuthTransaction
}
