import type { DictionaryEntry } from '../domain/dictionary'
import { fetchJson } from './transport'
const baseUrl = (
  import.meta.env.VITE_API_BASE_URL || 'http://localhost:8088'
).replace(/\/+$/, '')
export function lookupWord(
  word: string,
  signal: AbortSignal,
): Promise<DictionaryEntry[]> {
  return fetchJson<DictionaryEntry[]>(
    `${baseUrl}/foreign/dictionary?${new URLSearchParams({ q: word })}`,
    { signal },
  )
}
