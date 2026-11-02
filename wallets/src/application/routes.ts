const paths: Record<string, string> = {
  dashboard: '/',
  login: '/login',
  logout: '/logout',
  'auth.sso': '/login',
  'holidays.import.template': '/holiday-template.csv',
  'wallets.sort': '/wallets/sort',
  'wallets.update-sort': '/wallets/sort',
  'loans.preview': '/loans/preview',
  'loans.settle': '/loans/:id/settle',
  'payments.store': '/payments',
  'transactions.bulk': '/transactions/bulk',
  'transactions.bulk.store': '/transactions/bulk',
  'holidays.import': '/holidays/import',
  'debt-goals.show': '/debt-goals/detail',
}
for (const root of [
  'wallets',
  'transactions',
  'loans',
  'recurring-items',
  'transaction-templates',
  'settings',
  'holidays',
  'debt-goals',
  'fixed-expenses',
]) {
  for (const action of ['index', 'store', 'update'])
    paths[`${root}.${action}`] ??=
      `/${root}${action === 'update' && ['wallets', 'loans', 'recurring-items'].includes(root) ? '/:id' : ''}`
  paths[`${root}.create`] = `/${root}/create`
  paths[`${root}.edit`] = `/${root}/:id/edit`
  paths[`${root}.show`] ??= `/${root}/:id`
  paths[`${root}.destroy`] = `/${root}/:id`
}
export function route(
  name: string,
  params?: Record<string, unknown> | number | string,
): string {
  let path = paths[name]
  if (!path) throw new Error(`Unknown route: ${name}`)
  const values: Record<string, unknown> =
    typeof params === 'object'
      ? { ...params }
      : params === undefined
        ? {}
        : { id: params }
  if (path.includes(':id')) {
    const key =
      [
        'id',
        'wallet',
        'loan',
        'transaction',
        'holiday',
        'recurringItem',
        'transactionTemplate',
      ].find((k) => values[k] !== undefined) ?? Object.keys(values)[0]
    if (!key) throw new Error(`Missing route parameter: ${name}`)
    path = path.replace(':id', encodeURIComponent(String(values[key])))
    delete values[key]
  }
  const query = new URLSearchParams()
  for (const [key, value] of Object.entries(values))
    if (value !== null && value !== undefined && value !== '')
      query.set(key, String(value))
  return path + (query.size ? `?${query}` : '')
}
