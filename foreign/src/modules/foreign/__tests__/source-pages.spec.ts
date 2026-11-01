import { mount } from '@vue/test-utils'
import { beforeEach, expect, it, vi } from 'vitest'
import Vocab from '../../../legacy/presentation/Pages/Vocab/Index.vue'
import VocabShow from '../../../legacy/presentation/Pages/Vocab/Show.vue'
import Hub from '../../../legacy/presentation/Pages/Quiz/Hub.vue'
import Quiz from '../../../legacy/presentation/Pages/Quiz/Play.vue'
const mocks = vi.hoisted(() => ({ post: vi.fn(), remove: vi.fn() }))
vi.mock('@/application/navigation', async () => {
  const { defineComponent, h } = await import('vue')
  return {
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
      props: ['href'],
      setup:
        (props, { slots }) =>
        () =>
          h('a', { href: props.href }, slots.default?.()),
    }),
    usePage: () => ({ props: {}, url: '/home/vocab' }),
    useForm: () => ({ post: mocks.post, delete: mocks.remove }),
    router: { visit: vi.fn(), reload: vi.fn() },
  }
})
const global = { stubs: { AppLayout: { template: '<div><slot /></div>' } } }
const vocab = {
  id: 12,
  word: 'apple',
  phonetic: '/test/',
  meanings: [{ definition: 'Fruit.', examples: ['Eat an apple.'] }],
  examples: [],
}
beforeEach(() => vi.clearAllMocks())
it('preserves a vocabulary link to a separate detail page, without collapse controls', () => {
  const wrapper = mount(Vocab, { props: { items: [vocab] }, global })
  expect(wrapper.get('.vocab-card-link').attributes('href')).toBe(
    '/home/vocab/12',
  )
  expect(wrapper.find('[aria-expanded]').exists()).toBe(false)
  expect(wrapper.text()).toContain('View details')
  wrapper.unmount()
})
it('renders vocabulary definitions/examples on the detail page', () => {
  const wrapper = mount(VocabShow, { props: { vocab }, global })
  expect(wrapper.text()).toContain('Fruit.')
  expect(wrapper.text()).toContain('Eat an apple.')
  expect(wrapper.find('.action-menu-danger').text()).toBe('Delete')
  wrapper.unmount()
})
it('shows Quiz and all four word games together', () => {
  const wrapper = mount(Hub, { global })
  expect(wrapper.findAll('.puzzle-mode-card')).toHaveLength(5)
  for (const name of ['Quiz', 'Scramble', 'Wordle', 'Hangman', 'Word Search'])
    expect(wrapper.text()).toContain(name)
  wrapper.unmount()
})
it('shows vocabulary review after quiz resolution and sends the chosen answer', async () => {
  const wrapper = mount(Quiz, {
    global,
    props: {
      question: {
        vocabulary_id: 12,
        question_type: 'word_to_definition',
        prompt: 'apple',
        correct_answer: '',
        options: ['Fruit.', 'Greeting.'],
      },
    },
  })
  expect(wrapper.find('.puzzle-reveal-card').exists()).toBe(false)
  await wrapper.get('.qz-option').trigger('click')
  expect(mocks.post).toHaveBeenCalledWith('/home/quiz/answer')
  await wrapper.setProps({
    feedback: 'Correct!',
    wasCorrect: true,
    reveal: vocab,
  })
  expect(wrapper.get('.puzzle-reveal-card').text()).toContain('Eat an apple.')
  wrapper.unmount()
})
