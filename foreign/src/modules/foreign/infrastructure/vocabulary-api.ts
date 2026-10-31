import type { DictionaryEntry } from '../domain/dictionary'
import type { VocabularyGateway } from '../application/ports/vocabulary'
import type { VocabularyPage } from '../domain/vocabulary'
import { foreignRequest } from './foreign-api'
export class HttpVocabularyGateway implements VocabularyGateway {
  save(entry: DictionaryEntry) {
    return foreignRequest<{ id: number; word: string }>('/vocabularies', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(entry),
    })
  }
  list(query: string, before?: number, signal?: AbortSignal) {
    const params = new URLSearchParams({ q: query })
    if (before !== undefined) params.set('before', String(before))
    return foreignRequest<VocabularyPage>(`/vocabularies?${params}`, {
      ...(signal ? { signal } : {}),
    })
  }
}
