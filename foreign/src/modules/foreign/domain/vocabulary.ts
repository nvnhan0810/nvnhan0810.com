import type { DictionaryEntry } from './dictionary'
export interface SavedWord {
  id: number
  word: string
  entry: DictionaryEntry
  created_at: string
}
export interface VocabularyPage {
  items: SavedWord[]
  next_cursor: number | null
}
