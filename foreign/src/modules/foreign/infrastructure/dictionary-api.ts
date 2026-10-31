import type { DictionaryEntry } from '../domain/dictionary'
import type { DictionaryGateway } from '../application/ports/dictionary'
import { LookupFailure } from '../domain/lookup-error'
import { session } from '../../../app/services'
import { ApiError, fetchJson } from './transport'
export class HttpDictionaryGateway implements DictionaryGateway {
  async lookup(word: string, signal: AbortSignal): Promise<DictionaryEntry[]> {
    const send = (token: string) =>
      fetchJson<DictionaryEntry[]>(
        `${session.settings.baseUrl}/foreign/dictionary?${new URLSearchParams({ q: word })}`,
        { signal, headers: { Authorization: `Bearer ${token}` } },
      )
    try {
      const token = await session.accessToken()
      try {
        return await send(token)
      } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 401) throw error
        return await send(await session.accessToken(true))
      }
    } catch (error) {
      if (error instanceof ApiError && error.code === 'word_not_found')
        throw new LookupFailure('not_found')
      if (error instanceof ApiError && error.status === 401)
        throw new LookupFailure('unauthorized')
      throw new LookupFailure('unavailable')
    }
  }
}
// Composition adapter retained for callers and component test injection.
export function lookupWord(word: string, signal: AbortSignal) {
  return new HttpDictionaryGateway().lookup(word, signal)
}
