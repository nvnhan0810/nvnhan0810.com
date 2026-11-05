import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import GameResultModal from '@/Components/GameResultModal.vue'
import GameWordReview from '@/Components/GameWordReview.vue'

const post = vi.hoisted(() => vi.fn())
vi.mock('@/application/navigation', () => ({
  Link: { template: '<a><slot /></a>' },
  useForm: (data: Record<string, unknown>) => ({
    ...data,
    processing: false,
    post,
  }),
}))
const review = {
  vocabulary_id: 7,
  word: 'bank',
  definition: 'River edge',
  reviewed: false,
}
beforeEach(() => {
  post.mockClear()
  vi.useFakeTimers()
})
afterEach(() => {
  vi.useRealTimers()
  document.documentElement.style.overflow = ''
})
function modal(resolved = true, reviews = [review]) {
  return mount(GameResultModal, {
    props: {
      reviews,
      mode: resolved ? 'quiz' : 'word-search',
      resolved,
      wasCorrect: true,
      score: 3,
    },
    global: { stubs: { Teleport: true } },
  })
}
it('opens an accessible result popup without a next button and saves before advancing', async () => {
  const wrapper = modal()
  await flushPromises()
  expect(wrapper.get('dialog').attributes('aria-labelledby')).toBe(
    'game-result-title',
  )
  expect(wrapper.text()).toContain('Nicely done!')
  expect(wrapper.text()).not.toContain('Next question')
  expect(document.documentElement.style.overflow).toBe('hidden')
  expect(post).not.toHaveBeenCalled()
  wrapper.getComponent(GameWordReview).vm.$emit('rated', 7, 4)
  await wrapper.vm.$nextTick()
  expect(post).not.toHaveBeenCalled()
  await vi.advanceTimersByTimeAsync(420)
  expect(post).toHaveBeenCalledWith(
    expect.stringContaining('/home/quiz/next'),
    expect.any(Object),
  )
  post.mock.calls[0]![1].onError()
  await wrapper.vm.$nextTick()
  expect(wrapper.text()).toContain(
    "Your memory level is saved. We couldn't load the next challenge.",
  )
  await wrapper.get('.result-retry').trigger('click')
  expect(post).toHaveBeenCalledTimes(2)
  wrapper.unmount()
  expect(document.documentElement.style.overflow).toBe('')
})
it('returns to the same Word Search board after rating a found word', async () => {
  const wrapper = modal(false)
  await flushPromises()
  expect(wrapper.text()).toContain('Word found!')
  wrapper.getComponent(GameWordReview).vm.$emit('rated', 7, 2)
  await vi.advanceTimersByTimeAsync(420)
  expect(wrapper.find('dialog').exists()).toBe(false)
  expect(post).not.toHaveBeenCalled()
  expect(document.documentElement.style.overflow).toBe('')
  wrapper.unmount()
})
it('reviews every pending word before advancing a finished grid', async () => {
  const wrapper = modal(true, [
    review,
    { ...review, vocabulary_id: 8, word: 'river' },
  ])
  await flushPromises()
  wrapper.getComponent(GameWordReview).vm.$emit('rated', 7, 1)
  await vi.advanceTimersByTimeAsync(420)
  expect(wrapper.getComponent(GameWordReview).props('reveal').word).toBe(
    'river',
  )
  expect(post).not.toHaveBeenCalled()
  wrapper.getComponent(GameWordReview).vm.$emit('rated', 8, 3)
  await vi.advanceTimersByTimeAsync(420)
  expect(post).toHaveBeenCalledOnce()
  wrapper.unmount()
})
it('does not reopen already reviewed Word Search finds or auto-skip an old memory level', async () => {
  const wrapper = modal(false, [{ ...review, reviewed: true }])
  await flushPromises()
  expect(wrapper.find('dialog').exists()).toBe(false)
  expect(post).not.toHaveBeenCalled()
  wrapper.unmount()
})
