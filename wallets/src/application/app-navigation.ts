// Navigation model shared by the desktop sidebar, the mobile bottom bar and
// the mobile section tabs. Kept free of Vue so it can be unit tested.

export type NavIconName =
  | 'home'
  | 'swap'
  | 'wallet'
  | 'loan'
  | 'repeat'
  | 'chart'
  | 'target'
  | 'template'
  | 'calendar'
  | 'settings'
  | 'planning'
  | 'profile'

export interface NavItem {
  label: string
  /** Shorter label for compact places (mobile tabs). */
  short?: string
  route: string
  /** Path prefix this item owns. `/` only matches the root path. */
  path: string
  icon: NavIconName
}

export interface NavSection {
  id: 'overview' | 'planning' | 'personal'
  label: string
  items: NavItem[]
}

export const NAV_SECTIONS: NavSection[] = [
  {
    id: 'overview',
    label: 'Tài chính',
    items: [
      { label: 'Tổng quan', route: 'dashboard', path: '/', icon: 'home' },
      {
        label: 'Giao dịch',
        route: 'transactions.index',
        path: '/transactions',
        icon: 'swap',
      },
      { label: 'Ví', route: 'wallets.index', path: '/wallets', icon: 'wallet' },
    ],
  },
  {
    id: 'planning',
    label: 'Kế hoạch',
    items: [
      {
        label: 'Khoản vay',
        route: 'loans.index',
        path: '/loans',
        icon: 'loan',
      },
      {
        label: 'Thu chi cố định',
        short: 'Cố định',
        route: 'recurring-items.index',
        path: '/recurring-items',
        icon: 'repeat',
      },
      {
        label: 'Tổng hợp chi cố định',
        short: 'Tổng hợp',
        route: 'fixed-expenses.index',
        path: '/fixed-expenses',
        icon: 'chart',
      },
      {
        label: 'Mục tiêu trả nợ',
        short: 'Mục tiêu nợ',
        route: 'debt-goals.index',
        path: '/debt-goals',
        icon: 'target',
      },
    ],
  },
  {
    id: 'personal',
    label: 'Cá nhân',
    items: [
      {
        label: 'Cài đặt',
        route: 'settings.index',
        path: '/settings',
        icon: 'settings',
      },
      {
        label: 'Mẫu giao dịch',
        short: 'Mẫu',
        route: 'transaction-templates.index',
        path: '/transaction-templates',
        icon: 'template',
      },
      {
        label: 'Ngày lễ',
        route: 'holidays.index',
        path: '/holidays',
        icon: 'calendar',
      },
    ],
  },
]

export function pathOf(url: string): string {
  const path = url.split(/[?#]/)[0] || '/'
  return path.length > 1 ? path.replace(/\/+$/, '') : path
}

export function matchesPath(url: string, prefix: string): boolean {
  const path = pathOf(url)
  if (prefix === '/') return path === '/'
  return path === prefix || path.startsWith(`${prefix}/`)
}

export function isItemActive(url: string, item: NavItem): boolean {
  return matchesPath(url, item.path)
}

export function sectionFor(url: string): NavSection | undefined {
  return NAV_SECTIONS.find((s) => s.items.some((i) => isItemActive(url, i)))
}

/** Mobile bottom tabs, either side of the central "add transaction" button. */
export interface BottomTab {
  label: string
  route: string
  icon: NavIconName
  match: (url: string) => boolean
}

export const BOTTOM_TABS_START: BottomTab[] = [
  {
    label: 'Tổng quan',
    route: 'dashboard',
    icon: 'home',
    match: (url) => matchesPath(url, '/'),
  },
  {
    label: 'Giao dịch',
    route: 'transactions.index',
    icon: 'swap',
    match: (url) =>
      matchesPath(url, '/transactions') &&
      !matchesPath(url, '/transactions/create'),
  },
]

export const BOTTOM_TABS_END: BottomTab[] = [
  {
    label: 'Ví',
    route: 'wallets.index',
    icon: 'wallet',
    match: (url) => matchesPath(url, '/wallets'),
  },
]

/** The "Menu" tab covers every screen not owned by another bottom tab. */
export function isMenuActive(url: string): boolean {
  const id = sectionFor(url)?.id
  return id === 'planning' || id === 'personal'
}

/** Top-level screens: on mobile these show the greeting instead of a back button. */
const ROOT_PATHS = new Set(
  NAV_SECTIONS.flatMap((s) => s.items.map((i) => i.path)),
)

export function isRootScreen(url: string): boolean {
  return ROOT_PATHS.has(pathOf(url))
}

/** Where the mobile back button goes when there is no in-app history. */
export function parentPath(url: string): string {
  const path = pathOf(url)
  if (path === '/') return '/'
  const owner = NAV_SECTIONS.flatMap((s) => s.items)
    .filter((i) => i.path !== '/' && matchesPath(path, i.path))
    .sort((a, b) => b.path.length - a.path.length)[0]
  return owner && owner.path !== path ? owner.path : '/'
}
