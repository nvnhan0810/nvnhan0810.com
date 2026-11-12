import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import Lookup from '../../../legacy/presentation/Pages/Learn/Index.vue'
import { LOOKUP_SESSION_KEY } from '../../../legacy/infrastructure/lookup-session'

const mocks = vi.hoisted(() => ({ post: vi.fn() }))
vi.mock('../../../legacy/application/navigation', async () => {
  const { defineComponent, h, reactive } = await import('vue')
  return {
    Head: defineComponent({ render: () => null }),
    Link: defineComponent({
      props: ['href'],
      setup:
        (props, { slots }) =>
        () =>
          h('a', { href: props.href }, slots.default?.()),
    }),
    useForm: (defaults: Record<string, unknown>) => {
      const data: Record<string, unknown> = reactive({
        ...defaults,
        processing: false,
      })
      return Object.assign(data, {
        transform: () => data,
        post: (path: string, options?: { onSuccess?: () => void }) => {
          const payload = Object.fromEntries(
            Object.keys(defaults).map((key) => [key, data[key]]),
          )
          mocks.post(path, JSON.parse(JSON.stringify(payload)), options)
          return Promise.resolve()
        },
      })
    },
  }
})
vi.mock('../../../legacy/presentation/Layouts/AppLayout.vue', async () => {
  const { defineComponent } = await import('vue')
  return { default: defineComponent({ template: '<main><slot /></main>' }) }
})
vi.mock(
  '../../../legacy/presentation/Components/DictionaryEntry.vue',
  async () => {
    const { defineComponent } = await import('vue')
    return { default: defineComponent({ render: () => null }) }
  },
)
const result = {
  word: 'hello',
  phonetic: '/hello/',
  audio_url: 'https://example.com/hello.mp3',
  examples: ['Say hello.'],
  synonyms: ['hi'],
  antonyms: ['goodbye'],
  meanings: [
    {
      part_of_speech: 'noun',
      definition: 'A greeting.',
      examples: ['Hello there.'],
      synonyms: [],
      antonyms: [],
    },
  ],
}
const wrappers: ReturnType<typeof mount>[] = []
it('opens a phrase draft with an AI prompt and saves its explanation as a phrase', async () => {
  const wrapper = mount(Lookup, {
    props: {
      word: 'what do you mean?',
      queryWord: 'what do you mean?',
      entityKind: 'phrase',
      draft: true,
      result: { word: 'what do you mean?', meanings: [] },
    },
  })
  wrappers.push(wrapper)
  expect(wrapper.text()).toContain('Phrase / idiom')
  expect(wrapper.text()).toContain('Copy prompt')
  expect(wrapper.text()).not.toContain('No dictionary entry')
  const clipboard = vi.fn().mockResolvedValue(undefined)
  Object.defineProperty(navigator, 'clipboard', {
    value: { writeText: clipboard },
    configurable: true,
  })
  await wrapper
    .findAll('button')
    .find((b) => b.text() === 'Copy prompt')!
    .trigger('click')
  expect(clipboard).toHaveBeenCalledWith(
    expect.stringContaining('Describe only this phrase'),
  )
  await wrapper
    .get('textarea')
    .setValue(JSON.stringify({ meanings: result.meanings }))
  await wrapper.get('.lookup-save-form').trigger('submit')
  expect(mocks.post.mock.calls[0]![1]).toMatchObject({
    word: 'what do you mean?',
    kind: 'phrase',
    meanings: result.meanings.map((m) => ({ ...m, example: m.examples[0] })),
  })
})
function page(saved = false, restore = false) {
  const wrapper = mount(Lookup, {
    props: restore
      ? {}
      : {
          result,
          word: 'hello',
          queryWord: 'hello',
          saved,
          savedVocabularyId: saved ? 12 : null,
        },
  })
  wrappers.push(wrapper)
  return wrapper
}
async function jsonView(wrapper: ReturnType<typeof page>) {
  await wrapper
    .findAll('button')
    .find((b) => b.text() === 'JSON')!
    .trigger('click')
  return wrapper.get('textarea')
}
beforeEach(() => {
  vi.useFakeTimers()
  vi.clearAllMocks()
  localStorage.clear()
})
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  vi.clearAllTimers()
  vi.useRealTimers()
})

it('shows Save for an unsaved word and posts the latest JSON fields', async () => {
  const wrapper = page()
  expect(wrapper.get('.lookup-save-form button').text()).toBe('Save')
  const input = await jsonView(wrapper)
  const edited = {
    ...JSON.parse(input.element.value),
    phonetic: '/edited/',
    audio_url: 'https://example.com/edited.mp3',
    examples: ['New word example.'],
    synonyms: ['greetings'],
    antonyms: [],
    meanings: [
      {
        part_of_speech: 'interjection',
        definition: 'An edited greeting.',
        examples: ['Edited example.'],
        synonyms: ['hey'],
        antonyms: ['bye'],
      },
    ],
  }
  await input.setValue(JSON.stringify(edited))
  await wrapper.get('.lookup-save-form').trigger('submit')
  expect(mocks.post).toHaveBeenCalledWith(
    '/home/lookup/save',
    {
      word: 'hello',
      kind: 'word',
      phrases: [],
      phonetic: '/edited/',
      audio_url: 'https://example.com/edited.mp3',
      examples: ['New word example.'],
      synonyms: ['greetings'],
      antonyms: [],
      embed: false,
      meanings: [
        {
          part_of_speech: 'interjection',
          definition: 'An edited greeting.',
          example: 'Edited example.',
          examples: ['Edited example.'],
          synonyms: ['hey'],
          antonyms: ['bye'],
        },
      ],
    },
    expect.any(Object),
  )
  mocks.post.mock.calls[0]![2].onSuccess()
  await wrapper.vm.$nextTick()
  expect(wrapper.find('.lookup-save-form').exists()).toBe(false)
})
it('hides Update until saved content changes and ignores formatting-only edits', async () => {
  const wrapper = page(true)
  expect(wrapper.find('.lookup-save-form').exists()).toBe(false)
  const input = await jsonView(wrapper)
  const original = JSON.parse(input.element.value)
  await input.setValue(JSON.stringify(original))
  expect(wrapper.find('.lookup-save-form').exists()).toBe(false)
  await input.setValue(JSON.stringify({ ...original, phonetic: '/changed/' }))
  expect(wrapper.get('.lookup-save-form button').text()).toBe('Update')
  await input.setValue(JSON.stringify(original))
  expect(wrapper.find('.lookup-save-form').exists()).toBe(false)
})
it('keeps edits after switching to UI mode and updates the saved snapshot after success', async () => {
  const wrapper = page(true)
  const input = await jsonView(wrapper)
  const edited = {
    ...JSON.parse(input.element.value),
    examples: ['Updated example.'],
  }
  await input.setValue(JSON.stringify(edited))
  await wrapper
    .findAll('button')
    .find((b) => b.text() === 'UI')!
    .trigger('click')
  expect(wrapper.get('.lookup-save-form button').text()).toBe('Update')
  await wrapper.get('.lookup-save-form').trigger('submit')
  expect(mocks.post.mock.calls[0]![1].examples).toEqual(['Updated example.'])
  mocks.post.mock.calls[0]![2].onSuccess()
  await wrapper.vm.$nextTick()
  expect(wrapper.find('.lookup-save-form').exists()).toBe(false)
})
it('rejects invalid JSON without posting and keeps Update visible for retry', async () => {
  const wrapper = page(true)
  const input = await jsonView(wrapper)
  await input.setValue('{broken')
  await wrapper.get('.lookup-save-form').trigger('submit')
  expect(mocks.post).not.toHaveBeenCalled()
  expect(wrapper.get('.lookup-json-error').text()).toContain('Invalid JSON')
  expect(wrapper.get('.lookup-save-form button').text()).toBe('Update')
})
it('restores unsaved edits separately from the saved baseline', async () => {
  const wrapper = page(true)
  const input = await jsonView(wrapper)
  const original = input.element.value
  await input.setValue(
    JSON.stringify({ ...JSON.parse(original), phonetic: '/draft/' }),
  )
  await vi.advanceTimersByTimeAsync(250)
  expect(
    JSON.parse(localStorage.getItem(LOOKUP_SESSION_KEY)!).savedEntryJson,
  ).toBeTruthy()
  wrapper.unmount()
  const restored = page(false, true)
  await restored.vm.$nextTick()
  expect(restored.get('.lookup-save-form button').text()).toBe('Update')
  await restored.get('textarea').setValue(original)
  expect(restored.find('.lookup-save-form').exists()).toBe(false)
})
it('resets saved/dirty state when lookup props change to a different word', async () => {
  const wrapper = page(true)
  const input = await jsonView(wrapper)
  await input.setValue(
    JSON.stringify({ ...JSON.parse(input.element.value), phonetic: '/draft/' }),
  )
  await wrapper.setProps({
    result: { ...result, word: 'world' },
    word: 'world',
    queryWord: 'world',
    saved: false,
    savedVocabularyId: null,
  })
  expect(wrapper.get('.lookup-save-form button').text()).toBe('Save')
})

it('posts related phrases with a word and renders their meanings', async () => {
  const wrapper = page(true)
  const phrase = { word: 'look forward to', meanings: result.meanings }
  const input = await jsonView(wrapper)
  await input.setValue(
    JSON.stringify({ ...JSON.parse(input.element.value), phrases: [phrase] }),
  )
  await wrapper.get('.lookup-save-form').trigger('submit')
  expect(mocks.post.mock.calls[0]![1].phrases).toEqual([phrase])
  await wrapper
    .findAll('button')
    .find((b) => b.text() === 'UI')!
    .trigger('click')
  expect(wrapper.get('[aria-label="Related phrases"]').text()).toContain(
    'look forward to',
  )
})

it('updates only the standalone phrase and preserves its kind in a restored session', async () => {
  const wrapper = page(true)
  await wrapper.setProps({ entityKind: 'phrase' })
  const input = await jsonView(wrapper)
  await input.setValue(
    JSON.stringify({ ...JSON.parse(input.element.value), phonetic: '/new/' }),
  )
  await wrapper.get('.lookup-save-form').trigger('submit')
  expect(mocks.post.mock.calls[0]![1].kind).toBe('phrase')
  vi.advanceTimersByTime(250)
  wrapper.unmount()
  mocks.post.mockClear()
  const restored = page(false, true)
  await restored.vm.$nextTick()
  await restored.get('textarea').setValue(
    JSON.stringify({
      ...JSON.parse(restored.get('textarea').element.value),
      phonetic: '/restored/',
    }),
  )
  await restored.get('.lookup-save-form').trigger('submit')
  expect(mocks.post.mock.calls[0]![1].kind).toBe('phrase')
})

it('shows choices instead of word details for a partial match', async () => {
  const wrapper = page()
  await wrapper.setProps({
    result: null,
    suggestions: [
      { word: 'look', kind: 'word' },
      { word: 'look forward to', kind: 'phrase' },
    ],
  })
  expect(wrapper.find('.lookup-result-panel').exists()).toBe(false)
  expect(wrapper.get('[aria-label="Search suggestions"]').text()).toContain(
    'look forward to',
  )
  expect(wrapper.get('a[href*="kind=phrase"]').attributes('href')).toContain(
    'look%20forward%20to',
  )
})
