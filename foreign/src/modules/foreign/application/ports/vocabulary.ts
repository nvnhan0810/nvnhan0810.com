import type { DictionaryEntry } from '../../domain/dictionary'
import type { VocabularyPage } from '../../domain/vocabulary'
export interface VocabularyGateway {
  save(entry: DictionaryEntry): Promise<{ id: number; word: string }>
  list(
    query: string,
    before?: number,
    signal?: AbortSignal,
  ): Promise<VocabularyPage>
}
