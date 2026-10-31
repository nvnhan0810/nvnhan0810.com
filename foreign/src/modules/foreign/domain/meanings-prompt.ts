import type { DictionaryEntry } from './dictionary'
interface PromptMeaning {
  part_of_speech: string
  definition: string
  examples: string[]
  synonyms: string[]
  antonyms: string[]
}
function meaningsToPrettyJson(meanings: readonly PromptMeaning[]) {
  return JSON.stringify(meanings, null, 2)
}
export function promptMeanings(entry: DictionaryEntry): PromptMeaning[] {
  return entry.meanings.flatMap((m) =>
    m.definitions.map((d) => ({
      part_of_speech: m.partOfSpeech,
      definition: d.definition,
      examples: d.examples?.length ? d.examples : d.example ? [d.example] : [],
      synonyms: [...new Set([...m.synonyms, ...d.synonyms])],
      antonyms: [...new Set([...m.antonyms, ...d.antonyms])],
    })),
  )
}
export function buildMeaningsAiPrompt(
  word: string,
  currentMeanings: readonly PromptMeaning[] = [],
): string {
  const label = word.trim() !== '' ? word.trim() : '{WORD}'
  const hasCurrent = currentMeanings.some(
    (m) => typeof m.definition === 'string' && m.definition.trim() !== '',
  )
  const currentBlock = hasCurrent
    ? `

Current meanings JSON (starting point — refine, do not discard useful senses without reason):
${meaningsToPrettyJson(currentMeanings)}
`
    : ''

  return `You are helping curate an English dictionary entry for FLC.

Word / phrase: ${label}
${currentBlock}
Return ONLY a valid JSON array (no markdown fences, no commentary) of meanings in this exact schema:

[
  {
    "part_of_speech": "adjective",
    "definition": "Feeling or showing pleasure",
    "examples": ["She looks happy today."],
    "synonyms": ["joyful", "glad"],
    "antonyms": ["sad", "unhappy"]
  }
]

Rules:
- Top-level MUST be a JSON array (or optionally {"meanings":[...]}).
- Each item MUST have a non-empty string "definition".
- "part_of_speech" is optional (noun, verb, adjective, adverb, phrase, idiom, ...). Use null or omit if unknown.
- "examples", "synonyms", "antonyms" MUST be arrays of strings. Use [] when empty.
- Prefer clear learner-friendly English definitions.
- ALL string values in the JSON MUST be English only. Do NOT put Vietnamese (or any non-English language) in definition, examples, synonyms, antonyms, or part_of_speech.
- Include multiple meanings when the word has distinct senses.
- Do not include extra keys (no "example" singular — use "examples").
- Prefer one clear entry per distinct meaning; deduplicate overlapping senses.
- If Current meanings JSON is present, improve that JSON: fix gaps, add missing senses/examples/synonyms/antonyms, keep valid existing senses.`
}
