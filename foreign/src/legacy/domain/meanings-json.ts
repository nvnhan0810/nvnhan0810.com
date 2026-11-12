export type Meaning = {
  part_of_speech: string | null
  definition: string
  examples: string[]
  synonyms: string[]
  antonyms: string[]
}

export type PhraseEntry = DictionaryLookupResult & { meanings: Meaning[] }

export type DictionaryLookupResult = {
  phrases?: PhraseEntry[]
  word: string
  phonetic?: string | null
  audio_url?: string | null
  examples?: string[]
  meanings?: MeaningLike[]
  synonyms?: string[]
  antonyms?: string[]
  source?: string | null
  curated?: boolean
}

/** Loose meaning shape from API / Inertia props before normalization. */
export type MeaningLike = {
  part_of_speech?: string | null
  definition?: string | null
  example?: string | null
  examples?: unknown
  synonyms?: unknown
  antonyms?: unknown
}

/**
 * Normalize AI/clipboard JSON into meanings for save.
 * Accepts a raw array, { meanings: [...] }, or markdown-fenced JSON.
 */
export function parseMeaningsJson(raw: string): Meaning[] {
  const trimmed = stripMarkdownFences(raw.trim())
  if (trimmed === '') {
    throw new Error('JSON is empty.')
  }

  let decoded: unknown
  try {
    decoded = JSON.parse(trimmed) as unknown
  } catch {
    throw new Error('Invalid JSON.')
  }

  let items: unknown[]
  if (Array.isArray(decoded)) {
    items = decoded
  } else if (
    decoded !== null &&
    typeof decoded === 'object' &&
    Array.isArray((decoded as { meanings?: unknown }).meanings)
  ) {
    items = (decoded as { meanings: unknown[] }).meanings
  } else {
    throw new Error('JSON must be a meanings array or { "meanings": [...] }.')
  }

  const meanings: Meaning[] = []
  for (const item of items) {
    if (item === null || typeof item !== 'object') {
      continue
    }
    const row = item as Record<string, unknown>
    const definition =
      typeof row.definition === 'string' ? row.definition.trim() : ''
    if (definition === '') {
      continue
    }

    const examples = stringList(row.examples)
    if (
      examples.length === 0 &&
      typeof row.example === 'string' &&
      row.example.trim() !== ''
    ) {
      examples.push(row.example.trim())
    }

    const pos =
      typeof row.part_of_speech === 'string' && row.part_of_speech.trim() !== ''
        ? row.part_of_speech.trim()
        : null

    meanings.push({
      part_of_speech: pos,
      definition,
      examples,
      synonyms: stringList(row.synonyms),
      antonyms: stringList(row.antonyms),
    })
  }

  if (meanings.length === 0) {
    throw new Error('Need at least one meaning with a definition.')
  }

  return meanings
}

export function meaningsToPrettyJson(meanings: readonly MeaningLike[]): string {
  const payload = meanings.map((m) => {
    const fromExamples = stringList(m.examples)
    const examples =
      fromExamples.length > 0
        ? fromExamples
        : typeof m.example === 'string' && m.example.trim() !== ''
          ? [m.example.trim()]
          : []

    return {
      part_of_speech:
        typeof m.part_of_speech === 'string' && m.part_of_speech.trim() !== ''
          ? m.part_of_speech.trim()
          : null,
      definition: typeof m.definition === 'string' ? m.definition : '',
      examples,
      synonyms: stringList(m.synonyms),
      antonyms: stringList(m.antonyms),
    }
  })

  return JSON.stringify(payload, null, 2)
}

export function entryToPrettyJson(
  entry: DictionaryLookupResult,
  meanings: readonly MeaningLike[],
): string {
  return JSON.stringify(
    {
      phonetic: entry.phonetic ?? '',
      audio_url: entry.audio_url ?? '',
      examples: entry.examples ?? [],
      synonyms: entry.synonyms ?? [],
      antonyms: entry.antonyms ?? [],
      meanings: JSON.parse(meaningsToPrettyJson(meanings)),
      phrases: (entry.phrases ?? []).map(({ word, meanings, ...metadata }) => ({
        word,
        ...metadata,
        meanings: JSON.parse(meaningsToPrettyJson(meanings)),
      })),
    },
    null,
    2,
  )
}

/** Legacy arrays update meanings only; omitted entry fields preserve existing values. */
export function parseEntryJson(
  raw: string,
): Partial<DictionaryLookupResult> & { meanings: Meaning[] } {
  const meanings = parseMeaningsJson(raw)
  const decoded = JSON.parse(stripMarkdownFences(raw.trim()))
  if (Array.isArray(decoded)) return { meanings }
  const result: Partial<DictionaryLookupResult> & { meanings: Meaning[] } = {
    meanings,
  }
  for (const key of ['phonetic', 'audio_url'] as const) {
    if (!(key in decoded)) continue
    if (decoded[key] !== null && typeof decoded[key] !== 'string')
      throw new Error(`${key} must be a string or null.`)
    result[key] = decoded[key]?.trim() ?? ''
  }
  if (result.audio_url) {
    let url: URL
    try {
      url = new URL(result.audio_url)
    } catch {
      throw new Error('audio_url must be an HTTPS URL or empty.')
    }
    if (url.protocol !== 'https:' || url.username || url.password)
      throw new Error('audio_url must be an HTTPS URL or empty.')
  }
  for (const key of ['examples', 'synonyms', 'antonyms'] as const) {
    if (!(key in decoded)) continue
    if (
      !Array.isArray(decoded[key]) ||
      decoded[key].some((v: unknown) => typeof v !== 'string')
    )
      throw new Error(`${key} must be an array of strings.`)
    result[key] = stringList(decoded[key])
  }
  if ('phrases' in decoded) {
    if (!Array.isArray(decoded.phrases) || decoded.phrases.length > 100)
      throw new Error('phrases must be an array with at most 100 entries.')
    const seen = new Set<string>()
    result.phrases = decoded.phrases.map((phrase: unknown): PhraseEntry => {
      if (!phrase || typeof phrase !== 'object')
        throw new Error('Invalid phrase.')
      const row = phrase as Record<string, unknown>
      const word =
        typeof row.word === 'string' ? row.word.trim().toLowerCase() : ''
      if (!word || word.length > 100 || !/^[a-z '\-]+$/.test(word))
        throw new Error('Each phrase needs a valid English word field.')
      if (seen.has(word)) throw new Error('Duplicate phrase.')
      seen.add(word)
      if ('phrases' in row) throw new Error('Nested phrases are not supported.')
      return { ...parseEntryJson(JSON.stringify(row)), word }
    })
  }
  return result
}

export function buildMeaningsAiPrompt(
  word: string,
  currentMeanings: readonly MeaningLike[] = [],
  entry: Partial<DictionaryLookupResult> = {},
  kind: 'word' | 'phrase' = word.trim().split(/\s+/).length > 1
    ? 'phrase'
    : 'word',
): string {
  const isPhrase = kind === 'phrase'
  const schema = isPhrase
    ? `{
  "phonetic": "",
  "audio_url": "",
  "examples": ["We broke the ice by introducing ourselves."],
  "synonyms": [],
  "antonyms": [],
  "phrases": [],
  "meanings": [{
    "part_of_speech": "idiom",
    "definition": "To make people feel more relaxed when meeting or starting a conversation",
    "examples": ["Her joke helped break the ice."],
    "synonyms": [],
    "antonyms": []
  }]
}`
    : `{
  "phonetic": "/ˈhæpi/",
  "audio_url": "",
  "examples": ["She looks happy today."],
  "synonyms": ["joyful"],
  "antonyms": ["sad"],
  "phrases": [{"word":"look forward to","meanings":[{"part_of_speech":"phrasal verb","definition":"To feel pleased about a future event","examples":["I look forward to seeing you."],"synonyms":[],"antonyms":[]}]}],
  "meanings": [{
    "part_of_speech": "adjective",
    "definition": "Feeling or showing pleasure",
    "examples": ["I am happy to help."],
    "synonyms": ["glad"],
    "antonyms": ["unhappy"]
  }]
}`
  return `You are helping curate ${isPhrase ? 'a standalone English phrase / idiom / sentence' : 'an English dictionary entry'} for FLC.
Word / phrase: ${word.trim() || '{WORD}'}
Current dictionary JSON (preserve valid information and improve missing details):
${entryToPrettyJson({ ...entry, word }, currentMeanings)}

Return ONLY a valid JSON object, without markdown or commentary, using this schema:
${schema}
Rules:
${
  kind === 'word'
    ? '- Suggest useful related phrases, phrasal verbs, idioms and clause patterns in phrases. Each needs word and meanings with definitions and examples. Preserve useful existing phrases. Use [] if none are relevant.'
    : '- This is a standalone phrase / idiom / sentence saved independently. Describe only this phrase and return phrases: []; do not suggest or change other words or phrases. Do not create or save a word entry, choose a parent word, or attach this expression to any specific word. Put the explanation of the complete expression directly in top-level meanings, never inside phrases.'
}
- Describe the requested ${isPhrase ? 'expression, not the example idiom' : 'word, not the example word'} in the schema.
- phonetic is IPA for the whole ${isPhrase ? 'expression' : 'word'}. Use an empty string if unknown.
- audio_url is a verified HTTPS pronunciation audio URL. Preserve a valid existing URL; never invent a URL. Use an empty string if unavailable.
- Top-level examples, synonyms, and antonyms apply to the whole ${isPhrase ? 'expression' : 'word'}. Sense-specific examples and related words belong inside meanings.
- All examples, synonyms, and antonyms MUST be arrays of strings; use [] when empty.
- Include at least one meaning with a non-empty definition and all distinct useful senses without duplication.
- Use English only for definitions, examples, synonyms, antonyms, and part_of_speech. Use IPA for phonetic.
- Use examples (plural), never example. Do not add extra keys.`
}

export async function copyTextToClipboard(text: string): Promise<void> {
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(text)
    return
  }

  const area = document.createElement('textarea')
  area.value = text
  area.setAttribute('readonly', '')
  area.style.position = 'fixed'
  area.style.left = '-9999px'
  document.body.appendChild(area)
  area.select()
  document.execCommand('copy')
  document.body.removeChild(area)
}

function stripMarkdownFences(text: string): string {
  const fenced = text.match(/^```(?:json)?\s*([\s\S]*?)\s*```$/i)
  const body = fenced?.[1]
  if (typeof body === 'string') {
    return body.trim()
  }
  return text
}

function stringList(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return []
  }
  const out: string[] = []
  for (const item of value) {
    if (typeof item !== 'string') {
      continue
    }
    const trimmed = item.trim()
    if (trimmed !== '') {
      out.push(trimmed)
    }
  }
  return out
}
