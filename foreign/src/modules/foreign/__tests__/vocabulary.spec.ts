import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import WordActions from '../presentation/components/WordActions.vue'
import { saveWord } from '../../../app/vocabulary'
import { copyPrompt } from '../../../app/clipboard'
import { CopyPrompt } from '../application/use-cases/copy-prompt'
import { SaveWord } from '../application/use-cases/vocabulary'
vi.mock('../../../app/vocabulary', () => ({
  saveWord: { execute: vi.fn() },
  listMyWords: { execute: vi.fn() },
}))
vi.mock('../../../app/clipboard', () => ({ copyPrompt: { execute: vi.fn() } }))
const entry = {
  word: 'hello',
  phonetic: 'ipa',
  phonetics: [],
  meanings: [
    {
      partOfSpeech: 'noun',
      synonyms: [],
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
}
async function setup(
  component: typeof WordActions,
  props: { entry: typeof entry },
) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/vocabulary', component: { render: () => null } }],
  })
  await router.push('/vocabulary')
  await router.isReady()
  const wrapper = mount(component, { props, global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}
describe('Saved vocabulary and AI prompt', () => {
  beforeEach(() => vi.resetAllMocks())
  it('saves a word and exposes a link to my words', async () => {
    vi.mocked(saveWord.execute).mockResolvedValue({ id: 1, word: 'hello' })
    const wrapper = await setup(WordActions, { entry })
    await wrapper.findAll('button')[1]!.trigger('click')
    await flushPromises()
    expect(saveWord.execute).toHaveBeenCalledWith(entry)
    expect(wrapper.text()).toContain('Saved')
    expect(wrapper.get('a').attributes('href')).toBe('/vocabulary')
    await wrapper.findAll('button')[0]!.trigger('click')
    await flushPromises()
    expect(copyPrompt.execute).toHaveBeenCalledWith(entry)
    expect(wrapper.text()).toContain('Copied!')
    wrapper.unmount()
  })
  it('builds the legacy prompt locally through a clipboard port', async () => {
    const clipboard = { write: vi.fn().mockResolvedValue(undefined) }
    await new CopyPrompt(clipboard).execute(entry)
    const text = clipboard.write.mock.calls[0]![0] as string
    expect(text).toContain('Word / phrase: hello')
    expect(text).toContain('A greeting.')
    expect(text).toContain('English only')
    const gateway = { save: vi.fn(), list: vi.fn() }
    expect(() =>
      new SaveWord(gateway).execute({ ...entry, meanings: [] }),
    ).toThrow()
    expect(gateway.save).not.toHaveBeenCalled()
  })
})
