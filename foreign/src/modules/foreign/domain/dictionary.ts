export interface DictionaryEntry {
  word: string
  phonetic: string
  phonetics: { text: string; audio: string }[]
  meanings: {
    partOfSpeech: string
    synonyms: string[]
    antonyms: string[]
    definitions: {
      definition: string
      example: string
      synonyms: string[]
      antonyms: string[]
    }[]
  }[]
}

export function audioUrl(value: string): string | undefined {
  try {
    const url = new URL(value.startsWith('//') ? `https:${value}` : value)
    if (url.protocol === 'https:' && !url.username && !url.password)
      return url.href
  } catch {
    /* Missing or invalid pronunciation URL. */
  }
  return undefined
}
