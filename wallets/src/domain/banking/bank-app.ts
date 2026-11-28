// Deeplinks that open a bank app on its transfer screen, via VietQR's
// https://dl.vietqr.io/pay router (https://www.vietqr.io/danh-sach-api/deeplink-app-ngan-hang).

import type { Bank } from './banks'

export interface TransferTarget {
  /** Recipient bank (its code goes into `ba=<account>@<code>`). */
  bank: Bank | undefined
  accountNumber: string
  amount?: number | null
  message?: string
}

export type Platform = 'ios' | 'android' | 'other'

export function bankAppId(bank: Bank, platform: Platform): string | undefined {
  if (!bank.app) return undefined
  return platform === 'ios' && bank.iosApp ? bank.iosApp : bank.app
}

/**
 * Link that opens `sourceBank`'s app. With a recipient (from a scanned QR) the
 * app's transfer form is prefilled where the app supports it.
 */
export function bankAppLink(
  sourceBank: Bank,
  platform: Platform,
  target?: TransferTarget,
): string | undefined {
  const app = bankAppId(sourceBank, platform)
  if (!app) return undefined
  const params = new URLSearchParams({ app })
  if (target?.accountNumber && target.bank) {
    params.set(
      'ba',
      `${target.accountNumber}@${target.bank.code.toLowerCase()}`,
    )
    if (target.amount && target.amount > 0)
      params.set('am', String(Math.round(target.amount)))
    // Bank apps reject long or accented transfer notes.
    const note = transferNote(target.message ?? '')
    if (note) params.set('tn', note)
  }
  return `https://dl.vietqr.io/pay?${params}`
}

/** Letters, digits and spaces only, max 50 chars: the safe subset for NAPAS transfer messages. */
export function transferNote(message: string): string {
  return message
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .replace(/[^0-9A-Za-z ]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 50)
    .trim()
}
