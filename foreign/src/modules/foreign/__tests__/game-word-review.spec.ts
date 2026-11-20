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
      example_translations: {
        'We sat on the river bank.': 'Chúng tôi ngồi bên bờ sông.',
      },
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
  expect(wrapper.find('blockquote small[lang="vi"]').exists()).toBe(false)
  expect(wrapper.get('blockquote [lang="en"]').text()).toContain(
    'We sat on the river bank.',
  )
  expect(wrapper.find('.memory-card[aria-pressed="true"]').exists()).toBe(false)
  expect(
    wrapper
      .findAll('.memory-card')
      .map((button) => button.get('strong').text()),
  ).toEqual(['Forgot it', 'Still learning', 'Almost there', 'Know it'])
  wrapper.unmount()
})
it.each(['quiz', 'scramble', 'wordle', 'hangman', 'word-search'] as const)(
  'continues %s only after the memory rating is saved',
  async (mode) => {
    const wrapper = mount(GameWordReview, { props: { reveal, mode } })
    await wrapper.findAll('.memory-card')[3]!.trigger('click')
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
    expect(wrapper.find('.memory-card[aria-pressed="true"]').exists()).toBe(
      false,
    )
    await wrapper.findAll('.memory-card')[0]!.trigger('click')
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
  await wrapper.findAll('.memory-card')[0]!.trigger('click')
  expect(post).not.toHaveBeenCalled()
  wrapper.unmount()
})

it.each(['quiz', 'scramble', 'wordle', 'hangman', 'word-search'] as const)(
  'switches the selected sense explanation in %s without submitting a rating',
  async (mode) => {
    const wrapper = mount(GameWordReview, {
      props: {
        mode,
        reveal: {
          ...reveal,
          meanings: [
            {
              ...reveal.meanings[0]!,
              definition_vi: 'Bờ đất bên cạnh một dòng sông.',
            },
            {
              definition: 'A financial institution',
              definition_vi: 'Ngân hàng tài chính.',
            },
          ],
        },
      },
    })
    await wrapper
      .get('[aria-label="Definition language"] button:last-child')
      .trigger('click')
    expect(wrapper.get('[lang="vi"]').text()).toBe(
      'Bờ đất bên cạnh một dòng sông.',
    )
    expect(wrapper.get('blockquote small[lang="vi"]').text()).toBe(
      'Chúng tôi ngồi bên bờ sông.',
    )
    expect(wrapper.text()).not.toContain('Ngân hàng tài chính.')
    expect(wrapper.text()).toContain('We sat on the river bank.')
    expect(post).not.toHaveBeenCalled()
    await wrapper
      .get('[aria-label="Definition language"] button:first-child')
      .trigger('click')
    expect(wrapper.get('[lang="en"]').text()).toBe('Land beside a river')
    expect(wrapper.find('blockquote small[lang="vi"]').exists()).toBe(false)
    wrapper.unmount()
  },
)
it('falls back to English explicitly when the sense has no Vietnamese explanation', async () => {
  const wrapper = mount(GameWordReview, { props: { reveal, mode: 'quiz' } })
  await wrapper
    .get('[aria-label="Definition language"] button:last-child')
    .trigger('click')
  expect(wrapper.get('[lang="en"]').text()).toBe('Land beside a river')
  expect(wrapper.text()).toContain(
    'Chưa có giải thích tiếng Việt cho nghĩa này.',
  )
  wrapper.unmount()
})

it.each(['quiz', 'scramble', 'wordle', 'hangman'] as const)(
  'marks a missed %s word as Forgot it automatically and waits for Continue',
  async (mode) => {
    const wrapper = mount(GameWordReview, {
      props: { reveal, mode, missed: true },
    })
    expect(wrapper.findAll('.memory-card')).toHaveLength(0)
    const prefix = mode === 'quiz' ? '/home/quiz' : `/home/puzzle/${mode}`
    expect(post).toHaveBeenCalledOnce()
    expect(post).toHaveBeenCalledWith(
      expect.stringContaining(`${prefix}/rate`),
      expect.objectContaining({ stayOnPage: true, background: true }),
    )
    expect(post.mock.contexts[0]).toEqual(
      expect.objectContaining({ vocabulary_id: 7, level: 1 }),
    )
    const button = () => wrapper.get('.review-missed-button')
    expect(button().attributes('disabled')).toBeDefined()

    post.mock.calls[0]![1].onError()
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[role="alert"]').text()).toContain('Forgot it')
    expect(button().text()).toContain('Try again')
    await button().trigger('click')
    expect(post).toHaveBeenCalledTimes(2)

    post.mock.calls[1]![1].onSuccess()
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('rated')).toBeUndefined()
    expect(wrapper.text()).toContain('Marked as Forgot it')
    expect(button().text()).toContain('Continue')
    await button().trigger('click')
    expect(wrapper.emitted('rated')).toEqual([[7, 1]])
    wrapper.unmount()
  },
)
