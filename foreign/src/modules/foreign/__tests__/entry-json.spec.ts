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
      phrases: [],
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

it('round trips independently stored phrases and explicitly empty links', () => {
  const phrases = [{ word: 'look forward to', meanings }]
  const raw = entryToPrettyJson({ word: 'look', phrases }, meanings)
  expect(parseEntryJson(raw).phrases).toEqual(phrases)
  expect(
    parseEntryJson(JSON.stringify({ meanings, phrases: [] })).phrases,
  ).toEqual([])
  expect(parseEntryJson(JSON.stringify({ meanings })).phrases).toBeUndefined()
})

it('rejects invalid, duplicate and nested phrases before saving any entry', () => {
  for (const phrases of [
    'bad',
    [{ word: '', meanings }],
    [{ word: 'look forward to', meanings: [] }],
    [
      { word: 'Look Forward To', meanings },
      { word: 'look forward to', meanings },
    ],
    [{ word: 'look forward to', meanings, phrases: [] }],
  ]) {
    expect(() =>
      parseEntryJson(JSON.stringify({ meanings, phrases })),
    ).toThrow()
  }
})

it('asks AI for related phrases on words and limits standalone phrase updates', () => {
  expect(buildMeaningsAiPrompt('look', meanings)).toContain(
    'Suggest useful related phrases',
  )
  expect(
    buildMeaningsAiPrompt('look forward to', meanings, {}, 'phrase'),
  ).toContain('Describe only this phrase')
})
