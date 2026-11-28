import { describe, expect, it } from 'vitest'
import {
  BOTTOM_TABS_END,
  BOTTOM_TABS_START,
  NAV_SECTIONS,
  isMenuActive,
  isRootScreen,
  matchesPath,
  parentPath,
  sectionFor,
} from '../application/app-navigation'
import { route } from '../application/routes'

describe('app navigation', () => {
  it('only uses known routes', () => {
    for (const item of NAV_SECTIONS.flatMap((s) => s.items))
      expect(route(item.route)).toBe(item.path)
    for (const tab of [...BOTTOM_TABS_START, ...BOTTOM_TABS_END])
      expect(() => route(tab.route)).not.toThrow()
  })

  it('matches path prefixes without partial segments', () => {
    expect(matchesPath('/', '/')).toBe(true)
    expect(matchesPath('/wallets', '/')).toBe(false)
    expect(matchesPath('/wallets/3/edit?x=1', '/wallets')).toBe(true)
    expect(matchesPath('/wallets-archive', '/wallets')).toBe(false)
    expect(matchesPath('/loans/', '/loans')).toBe(true)
  })

  it('finds the section of a page', () => {
    expect(sectionFor('/')?.id).toBe('overview')
    expect(sectionFor('/debt-goals/detail')?.id).toBe('planning')
    expect(sectionFor('/holidays?year=2026')?.id).toBe('personal')
    expect(sectionFor('/unknown')).toBeUndefined()
  })

  it('lights the menu tab for planning and personal pages only', () => {
    expect(isMenuActive('/loans/4')).toBe(true)
    expect(isMenuActive('/settings')).toBe(true)
    expect(isMenuActive('/wallets')).toBe(false)
    expect(isMenuActive('/')).toBe(false)
  })

  it('does not mark the transaction list active on the create form', () => {
    const tx = BOTTOM_TABS_START.find((t) => t.route === 'transactions.index')!
    expect(tx.match('/transactions?page=2')).toBe(true)
    expect(tx.match('/transactions/create')).toBe(false)
  })

  it('tells root screens from sub-pages', () => {
    expect(isRootScreen('/')).toBe(true)
    expect(isRootScreen('/loans?status=active')).toBe(true)
    expect(isRootScreen('/loans/create')).toBe(false)
    expect(isRootScreen('/wallets/1/edit')).toBe(false)
  })

  it('falls back to the owning list for the back button', () => {
    expect(parentPath('/wallets/1/edit')).toBe('/wallets')
    expect(parentPath('/transactions/create?type=income')).toBe('/transactions')
    expect(parentPath('/debt-goals/detail')).toBe('/debt-goals')
    expect(parentPath('/loans')).toBe('/')
    expect(parentPath('/somewhere')).toBe('/')
  })
})
