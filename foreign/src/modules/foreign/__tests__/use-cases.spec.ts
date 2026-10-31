import { describe, expect, it, vi } from 'vitest'
import { LookupWord } from '../application/use-cases/lookup-word'
import {
  sessionDestination,
  initializeSession,
} from '../application/use-cases/initialize-session'
import type { ForeignSession } from '../application/session'
describe('Application use cases', () => {
  it('normalizes before invoking the gateway and rejects invalid input', async () => {
    const gateway = { lookup: vi.fn().mockResolvedValue([]) }
    const useCase = new LookupWord(gateway)
    const signal = new AbortController().signal
    await useCase.execute(' Hello ', signal)
    expect(gateway.lookup).toHaveBeenCalledWith('hello', signal)
    expect(() => useCase.execute('../hello', signal)).toThrow()
    expect(gateway.lookup).toHaveBeenCalledTimes(1)
  })
  it('routes index, private routes and login by session', () => {
    expect(sessionDestination('/', false)).toBe('/login')
    expect(sessionDestination('/', true)).toBe('/lookup')
    expect(sessionDestination('/lookup', false)).toBe('/login')
    expect(sessionDestination('/profile', false)).toBe('/login')
    expect(sessionDestination('/login', true)).toBe('/lookup')
    expect(sessionDestination('/login', false)).toBeNull()
    expect(sessionDestination('/lookup', true)).toBeNull()
  })
  it('completes callback before routing to lookup and otherwise restores', async () => {
    const session = { completeLogin: vi.fn(), restore: vi.fn() }
    expect(
      await initializeSession(
        session as unknown as ForeignSession,
        new URL('http://localhost/auth/callback?code=test&state=test'),
      ),
    ).toBe('/lookup')
    expect(session.completeLogin).toHaveBeenCalledTimes(1)
    expect(session.restore).not.toHaveBeenCalled()
    expect(location.search).toBe('')
    await initializeSession(
      session as unknown as ForeignSession,
      new URL('http://localhost/lookup'),
    )
    expect(session.restore).toHaveBeenCalledTimes(1)
  })
})
