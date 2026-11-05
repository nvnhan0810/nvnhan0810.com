import { mount } from '@vue/test-utils'
import { beforeEach, expect, it, vi } from 'vitest'
import GameWordReview from '@/Components/GameWordReview.vue'

const post = vi.hoisted(() => vi.fn())
vi.mock('@/application/navigation', () => ({
  useForm: (data: Record<string, unknown>) => ({
    ...data,
    processing: false,
    post,
  }),
}))
beforeEach(() => post.mockClear())
const reveal = {
  vocabulary_id: 7,
  word: 'bank',
  phonetic: '/bæŋk/',
  memory_level: 2,
  meanings: [
    {
      part_of_speech: 'noun',
      definition: 'Land beside a river',
      example: 'We sat on the river bank.',
      examples: ['We sat on the river bank.', 'The bank was steep.'],
    },
    { definition: 'A financial institution', example: 'She visited the bank.' },
  ],
}
it('shows the used meaning and its examples, and asks for a fresh memory choice', () => {
  const wrapper = mount(GameWordReview, { props: { reveal, mode: 'quiz' } })
  expect(wrapper.text()).toContain('/bæŋk/')
  expect(wrapper.text()).toContain('Land beside a river')
  expect(wrapper.text()).not.toContain('A financial institution')
  expect(wrapper.text()).not.toContain('She visited the bank.')
  expect(wrapper.findAll('blockquote')).toHaveLength(1)
  expect(wrapper.find('[aria-pressed="true"]').exists()).toBe(false)
  expect(
    wrapper.findAll('button').map((button) => button.get('strong').text()),
  ).toEqual(['Forgot it', 'Still learning', 'Almost there', 'Know it'])
  wrapper.unmount()
})
it.each(['quiz', 'scramble', 'wordle', 'hangman', 'word-search'] as const)(
  'continues %s only after the memory rating is saved',
  async (mode) => {
    const wrapper = mount(GameWordReview, { props: { reveal, mode } })
    await wrapper.findAll('button')[3]!.trigger('click')
    expect(wrapper.emitted('rated')).toBeUndefined()
    const prefix = mode === 'quiz' ? '/home/quiz' : `/home/puzzle/${mode}`
    expect(post).toHaveBeenCalledWith(
      expect.stringContaining(`${prefix}/rate`),
      expect.objectContaining({ stayOnPage: true, background: true }),
    )
    expect(post.mock.contexts[0]).toEqual(
      expect.objectContaining({ vocabulary_id: 7, level: 4 }),
    )
    post.mock.calls[0]![1].onError()
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[role="alert"]').text()).toContain('Could not save')
    expect(wrapper.emitted('rated')).toBeUndefined()
    expect(wrapper.find('[aria-pressed="true"]').exists()).toBe(false)
    await wrapper.findAll('button')[0]!.trigger('click')
    post.mock.calls[1]![1].onSuccess()
    expect(wrapper.emitted('rated')).toEqual([[7, 1]])
    wrapper.unmount()
  },
)
it('shows an explicit empty example state and disables cards while advancing', async () => {
  const wrapper = mount(GameWordReview, {
    props: {
      reveal: {
        vocabulary_id: 1,
        word: 'hello',
        meanings: [{ definition: 'A greeting' }],
      },
      mode: 'quiz',
      locked: true,
    },
  })
  expect(wrapper.text()).toContain('No example available.')
  await wrapper.findAll('button')[0]!.trigger('click')
  expect(post).not.toHaveBeenCalled()
  wrapper.unmount()
})
