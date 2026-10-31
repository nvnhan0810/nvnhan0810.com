import type { ForeignSession } from '../session'
export async function initializeSession(session: ForeignSession, url: URL) {
  if (url.pathname === '/auth/callback') {
    window.history.replaceState({}, '', '/auth/callback')
    await session.completeLogin(url)
    return '/lookup'
  }
  await session.restore()
  return null
}
export function sessionDestination(
  path: string,
  authenticated: boolean,
): string | null {
  if (path === '/' || path === '/auth/callback')
    return authenticated ? '/lookup' : '/login'
  if (!authenticated && path !== '/login') return '/login'
  if (authenticated && path === '/login') return '/lookup'
  return null
}
