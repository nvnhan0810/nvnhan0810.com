import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LookupPage from '../presentation/pages/LookupPage.vue'
import { lookupWord } from '../infrastructure/dictionary-api'
import { LookupFailure } from '../domain/lookup-error'
import { audioUrl } from '../domain/dictionary'
vi.mock('../infrastructure/dictionary-api', () => ({ lookupWord: vi.fn() }))
const entry = (word: string) => [
  {
    word,
    phonetic: '/test/',
    phonetics: [],
    meanings: [
      {
        partOfSpeech: 'noun',
        synonyms: ['greeting'],
        antonyms: [],
        definitions: [
          {
            definition: 'A greeting.',
            example: 'Hello there.',
            synonyms: [],
            antonyms: [],
          },
        ],
      },
    ],
  },
]
async function page(path = '/lookup') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/lookup', component: LookupPage }],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(LookupPage, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}
describe('Lookup', () => {
  beforeEach(() => vi.resetAllMocks())
  it('starts empty without calling the provider', async () => {
    const { wrapper } = await page()
    expect(wrapper.get('button[type=submit]').text()).toBe('Look up')
    expect(lookupWord).not.toHaveBeenCalled()
    wrapper.unmount()
  })
  it('searches, renders definitions/examples and supports related words', async () => {
    vi.mocked(lookupWord).mockResolvedValue(entry('hello'))
    const { wrapper, router } = await page()
    await wrapper.get('input').setValue(' Hello ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(router.currentRoute.value.query.q).toBe('hello')
    expect(wrapper.text()).toContain('A greeting.')
    expect(wrapper.text()).toContain('Hello there.')
    expect(
      wrapper.findAll('button').some((b) => /Chỉnh sửa/.test(b.text())),
    ).toBe(false)
    await wrapper.get('.related-words button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.q).toBe('greeting')
    wrapper.unmount()
  })
  it('distinguishes not found from provider failure and supports retry', async () => {
    vi.mocked(lookupWord).mockRejectedValueOnce(new LookupFailure('not_found'))
    const { wrapper } = await page('/lookup?q=hello')
    expect(wrapper.text()).toContain('No dictionary entry found')
    expect(wrapper.text()).toContain('Copy prompt AI')
    expect(wrapper.text()).not.toContain('Save word')
    vi.mocked(lookupWord).mockRejectedValueOnce(
      new LookupFailure('unavailable'),
    )
    await wrapper.get('.lookup-retry').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Dictionary unavailable')
    wrapper.unmount()
  })
  it('ignores stale responses', async () => {
    let finish: ((value: ReturnType<typeof entry>) => void) | undefined
    vi.mocked(lookupWord).mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          finish = resolve
        }),
    )
    const { wrapper, router } = await page('/lookup?q=hello')
    vi.mocked(lookupWord).mockResolvedValueOnce(entry('world'))
    await router.push('/lookup?q=world')
    await flushPromises()
    finish?.(entry('hello'))
    await flushPromises()
    expect(wrapper.get('h2').text()).toBe('world')
    wrapper.unmount()
  })
  it('rejects invalid input and unsafe audio schemes', async () => {
    const { wrapper } = await page('/lookup?q=..%2Fhello')
    expect(lookupWord).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Enter an English word or phrase')
    expect(audioUrl('javascript:alert(1)')).toBeUndefined()
    expect(audioUrl('http://audio.example/test.mp3')).toBeUndefined()
    expect(audioUrl('//audio.example/test.mp3')).toBe(
      'https://audio.example/test.mp3',
    )
    wrapper.unmount()
  })
})
