import { RoutePath } from '../../../../app/router/paths'
import type { ForeignSession } from '../session'
export async function initializeSession(session: ForeignSession, url: URL) {
  if (url.pathname === RoutePath.authCallback) {
    window.history.replaceState({}, '', RoutePath.authCallback)
    await session.completeLogin(url)
    return RoutePath.lookup
  }
  await session.restore()
  return null
}
export function sessionDestination(
  path: string,
  authenticated: boolean,
): string | null {
  if (path === RoutePath.index || path === RoutePath.authCallback)
    return authenticated ? RoutePath.lookup : RoutePath.login
  if (!authenticated && path !== RoutePath.login) return RoutePath.login
  if (authenticated && path === RoutePath.login) return RoutePath.lookup
  return null
}
