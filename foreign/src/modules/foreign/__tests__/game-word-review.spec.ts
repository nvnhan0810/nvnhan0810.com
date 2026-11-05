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

it('shows only the used meaning and its examples with the saved memory level', () => {
  const wrapper = mount(GameWordReview, { props: { reveal, mode: 'quiz' } })
  expect(wrapper.text()).toContain('bank')
  expect(wrapper.text()).toContain('/bæŋk/')
  expect(wrapper.text()).toContain('Land beside a river')
  expect(wrapper.text()).not.toContain('A financial institution')
  expect(wrapper.text()).not.toContain('She visited the bank.')
  expect(wrapper.findAll('blockquote')).toHaveLength(2)
  expect(wrapper.get('[aria-pressed="true"]').text()).toBe('Nhớ mang máng')
  wrapper.unmount()
})

it.each(['quiz', 'scramble', 'wordle', 'hangman', 'word-search'] as const)(
  'saves the rating in %s and only selects it after a successful response',
  async (mode) => {
    const wrapper = mount(GameWordReview, { props: { reveal, mode } })
    await wrapper.findAll('button')[3]!.trigger('click')
    const prefix = mode === 'quiz' ? '/home/quiz' : `/home/puzzle/${mode}`
    expect(post).toHaveBeenCalledWith(
      expect.stringContaining(`${prefix}/rate`),
      expect.objectContaining({ stayOnPage: true }),
    )
    expect(post.mock.contexts[0]).toEqual(
      expect.objectContaining({ vocabulary_id: 7, level: 4 }),
    )
    expect(wrapper.get('[aria-pressed="true"]').text()).toBe('Nhớ mang máng')
    post.mock.calls[0]![1].onSuccess()
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[aria-pressed="true"]').text()).toBe('Nhớ rõ')
    expect(wrapper.get('[role="status"]').text()).toBe('Đã lưu mức ghi nhớ.')
    await wrapper.findAll('button')[0]!.trigger('click')
    post.mock.calls[1]![1].onError()
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[aria-pressed="true"]').text()).toBe('Nhớ rõ')
    expect(wrapper.get('[role="status"]').text()).toContain('Chưa lưu được')
    wrapper.unmount()
  },
)
