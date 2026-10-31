import type { VocabularyGateway } from '../ports/vocabulary'
import type { DictionaryEntry } from '../../domain/dictionary'
export class SaveWord {
  constructor(private readonly gateway: VocabularyGateway) {}
  execute(entry: DictionaryEntry) {
    if (
      !entry.word.trim() ||
      !entry.meanings.some((m) =>
        m.definitions.some((d) => d.definition.trim()),
      )
    )
      throw new Error('A word and definition are required.')
    return this.gateway.save(entry)
  }
}
export class ListMyWords {
  constructor(private readonly gateway: VocabularyGateway) {}
  execute(query: string, before?: number, signal?: AbortSignal) {
    return this.gateway.list(query.trim().slice(0, 100), before, signal)
  }
}
