import { describe, expect, it } from 'vitest'
import {
  buildMeaningsAiPrompt,
  entryToPrettyJson,
  parseEntryJson,
} from '../../../legacy/domain/meanings-json'

const meanings = [
  {
    definition: 'A greeting.',
    examples: ['Hello there.'],
    synonyms: [],
    antonyms: [],
    part_of_speech: 'noun',
  },
]
describe('dictionary entry JSON', () => {
  it('round trips word fields separately from sense fields', () => {
    const entry = {
      word: 'hello',
      phonetic: '/həˈləʊ/',
      audio_url: 'https://example.com/hello.mp3',
      examples: ['Say hello.'],
      synonyms: ['hi'],
      antonyms: ['goodbye'],
    }
    expect(parseEntryJson(entryToPrettyJson(entry, meanings))).toEqual({
      ...entry,
      word: undefined,
      meanings,
    })
  })
  it('accepts legacy arrays without clearing entry fields', () => {
    expect(parseEntryJson(JSON.stringify(meanings))).toEqual({ meanings })
    expect(
      parseEntryJson(
        JSON.stringify({ phonetic: '', audio_url: '', synonyms: [], meanings }),
      ),
    ).toEqual({ phonetic: '', audio_url: '', synonyms: [], meanings })
  })
  it('rejects malformed metadata and unsafe audio URLs', () => {
    for (const fields of [
      { audio_url: 'javascript:alert(1)' },
      { examples: 'text' },
      { phonetic: 42 },
    ]) {
      expect(() =>
        parseEntryJson(JSON.stringify({ ...fields, meanings })),
      ).toThrow()
    }
  })
  it('includes word metadata and sense data in the copied prompt', () => {
    const prompt = buildMeaningsAiPrompt('hello', meanings, {
      phonetic: '/hello/',
      examples: ['Say hello.'],
      synonyms: ['hi'],
    })
    expect(prompt).toContain('"phonetic": "/hello/"')
    expect(prompt).toContain('Say hello.')
    expect(prompt).toContain('A greeting.')
    expect(prompt).toContain('never invent a URL')
  })
})
