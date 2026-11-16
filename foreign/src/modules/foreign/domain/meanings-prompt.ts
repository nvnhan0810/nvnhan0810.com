import type { DictionaryEntry } from './dictionary'
interface PromptMeaning {
  part_of_speech: string
  definition: string
  definition_vi?: string
  example_translations?: Record<string, string>
  examples: string[]
  synonyms: string[]
  antonyms: string[]
}
export function promptMeanings(entry: DictionaryEntry): PromptMeaning[] {
  return entry.meanings.flatMap((m) =>
    m.definitions.map((d) => ({
      part_of_speech: m.partOfSpeech,
      definition: d.definition,
      ...(d.definition_vi !== undefined
        ? { definition_vi: d.definition_vi }
        : {}),
      ...(d.example_translations !== undefined
        ? { example_translations: d.example_translations }
        : {}),
      examples: d.examples?.length ? d.examples : d.example ? [d.example] : [],
      synonyms: [...new Set([...m.synonyms, ...d.synonyms])],
      antonyms: [...new Set([...m.antonyms, ...d.antonyms])],
    })),
  )
}
export { buildMeaningsAiPrompt } from '../../../legacy/domain/meanings-json'
