import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { render } from '../../entry-server'
import { createPortfolioApp } from '../../main'
import type { PortfolioState } from '../../modules/portfolio/presentation/portfolio-context'

let cleanup: (() => void) | undefined
afterEach(() => {
  cleanup?.()
  document.body.innerHTML = ''
  localStorage.clear()
  history.replaceState(null, '', '/')
  vi.restoreAllMocks()
})

it('hydrates server HTML without mismatch, then switches language interactively', async () => {
  // A stale browser preference must not change the first hydration render.
  localStorage.setItem('portfolio-locale', 'en')
  history.replaceState(null, '', '/?lang=vi')
  const result = await render('/', 'vi')
  if (result.status !== 200) throw new Error('Unexpected redirect')
  document.body.innerHTML = `<div id="app">${result.html}</div>${result.state}`
  const serialized = document.getElementById('portfolio-state')?.textContent
  if (!serialized) throw new Error('Missing SSR state')
  const state = JSON.parse(serialized) as PortfolioState
  const warn = vi.spyOn(console, 'warn')
  const error = vi.spyOn(console, 'error')
  const { app, router } = createPortfolioApp(state)
  await router.isReady()
  app.mount('#app')
  cleanup = () => {
    app.unmount()
  }
  await flushPromises()
  expect(document.querySelector('#about')?.textContent).toContain('Lập trình viên')
  expect(warn).not.toHaveBeenCalled()
  expect(error).not.toHaveBeenCalled()

  const english = [...document.querySelectorAll('button')].find(
    (button) => button.textContent === 'EN',
  )
  if (!english) throw new Error('Missing language control')
  english.click()
  await flushPromises()
  expect(document.querySelector('#about')?.textContent).toContain('Developer')
  expect(document.documentElement.lang).toBe('en')
  expect(document.cookie).toContain('portfolio-locale=en')
  expect(location.search).toBe('?lang=en')
})
