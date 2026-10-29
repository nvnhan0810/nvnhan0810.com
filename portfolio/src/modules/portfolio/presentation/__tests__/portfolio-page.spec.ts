import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { GetPortfolio } from '../../application/use-cases/get-portfolio'
import type { Portfolio } from '../../domain/entities/portfolio'
import { portfolioKey } from '../portfolio-context'
import PortfolioPage from '../pages/PortfolioPage.vue'

// A small fixture keeps UI tests independent of the concrete repository adapter.
const cv = {
  name: 'Test Developer',
  title: 'Developer',
  phone: '0123',
  email: 'test@example.com',
  location: 'Ho Chi Minh City',
  summary: 'A profile summary.',
  education: { school: 'University', major: 'IT', period: '2013–2018', gpa: '7.68' },
  languages: 'English',
  interests: ['Reading'],
}
const english = {
  nav: {
    portfolio: 'Portfolio',
    blog: 'Blog',
    about: 'About',
    skills: 'Skills',
    contact: 'Contact',
    admin: 'Admin',
    apps: 'Apps',
    news: 'News',
    todosApp: 'Todo',
  },
  home: {
    portfolioLabel: 'Portfolio',
    contactMe: 'Contact me',
    about: 'About',
    location: 'Location',
    phone: 'Phone',
    education: 'Education',
    skills: 'Skills',
    contact: 'Contact',
    interests: 'Interests',
    readBlog: 'Read blog',
    latestPosts: 'Latest posts',
    viewAllPosts: 'View all posts',
    allPosts: 'All posts',
    appsTitle: 'Apps',
    viewAllApps: 'View all apps',
  },
  blog: { label: 'Blog', noPosts: 'No posts yet.' },
  apps: {
    label: 'Apps',
    description: 'My apps',
    items: { sample: { name: 'Sample app', summary: 'App description', features: [] } },
  },
  common: { language: 'Language' },
  cv,
}
const fixture: Portfolio = {
  messages: {
    en: english,
    vi: { ...english, common: { language: 'Ngôn ngữ' }, cv: { ...cv, title: 'Lập trình viên' } },
  },
  profile: {
    githubLink: 'https://github.com/example',
    linkedinLink: 'https://linkedin.com/in/example',
    email: cv.email,
    avatarPath: '',
    cvPdfPath: '',
  },
  techStack: [{ name: 'Vue.js', slug: 'vuedotjs', color: '4FC08D' }],
  apps: [
    {
      slug: 'sample',
      kind: 'project',
      label: 'Sample',
      githubUrl: 'https://github.com/example/sample',
      tags: ['Vue'],
    },
  ],
  posts: [],
}

let wrapper: VueWrapper | undefined
function render(execute = () => Promise.resolve(fixture)) {
  wrapper = mount(PortfolioPage, {
    global: { provide: { [portfolioKey as symbol]: new GetPortfolio({ getPortfolio: execute }) } },
  })
  return wrapper
}

beforeEach(() => {
  localStorage.clear()
  document.documentElement.lang = 'en'
})
afterEach(() => {
  wrapper?.unmount()
  vi.restoreAllMocks()
})

describe('Portfolio home', () => {
  it('renders the source home structure, contact details, skills and apps', async () => {
    const page = render()
    await flushPromises()
    expect(page.get('h1').text()).toBe(cv.name)
    for (const id of ['about', 'skills', 'apps', 'contact'])
      expect(page.find(`#${id}`).exists()).toBe(true)
    expect(page.get('a[href="mailto:test@example.com"]').text()).toBe('Contact me')
    expect(page.get('#skills img').attributes('src')).toBe(
      'https://cdn.simpleicons.org/vuedotjs/4FC08D',
    )
    expect(page.get('#apps a[href="https://nvnhan0810.com/apps/sample"]').text()).toContain(
      'Sample app',
    )
    expect(page.find('#blog').exists()).toBe(false)
    expect(page.find('a[href="#blog"]').exists()).toBe(false)
    expect(page.find('nav[aria-label="Mobile quick links"]').exists()).toBe(true)
  })

  it('switches language and updates page metadata and persisted preference', async () => {
    const page = render()
    await flushPromises()
    const vietnamese = page.findAll('button').find((button) => button.text() === 'VI')
    if (!vietnamese) throw new Error('VI language button is missing')
    await vietnamese.trigger('click')
    expect(page.get('#about').text()).toContain('Lập trình viên')
    expect(document.documentElement.lang).toBe('vi')
    expect(document.title).toBe('Test Developer — Lập trình viên')
    expect(localStorage.getItem('portfolio-locale')).toBe('vi')
    expect(vietnamese.attributes('aria-pressed')).toBe('true')
  })

  it('restores a saved language preference', async () => {
    localStorage.setItem('portfolio-locale', 'vi')
    const page = render()
    await flushPromises()
    expect(page.get('#about').text()).toContain('Lập trình viên')
  })

  it('shows a recoverable error when the repository fails', async () => {
    const repository = vi
      .fn<() => Promise<Portfolio>>()
      .mockRejectedValueOnce(new Error('Unavailable'))
      .mockResolvedValueOnce(fixture)
    const page = render(repository)
    await flushPromises()
    expect(page.get('[role="alert"]').text()).toContain('Unable to load')
    await page.get('button').trigger('click')
    await flushPromises()
    expect(page.get('h1').text()).toBe(cv.name)
    expect(repository).toHaveBeenCalledTimes(2)
  })
})
