import type { DictionaryEntry } from '../../domain/dictionary'
export interface DictionaryGateway {
  lookup(word: string, signal: AbortSignal): Promise<DictionaryEntry[]>
}
