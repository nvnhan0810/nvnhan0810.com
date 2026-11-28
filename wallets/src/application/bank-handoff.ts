// After saving a transaction from a bank wallet on a phone, hand off to the
// wallet's bank app (transfer screen) — prefilled when it came from a VietQR.

import { walletBank, type Bank } from '@/domain/banking/banks'
import {
  bankAppLink,
  type Platform,
  type TransferTarget,
} from '@/domain/banking/bank-app'
import {
  canOpenBankApp,
  currentPlatform,
  openExternal,
} from '@/infrastructure/device'

export interface BankWalletLike {
  type: string
  bank_bin?: string | null
  bank_code?: string | null
}

export interface BankHandoff {
  bank: Bank
  appName: string
  link: string
  /** The app fills recipient/amount itself; otherwise the user pastes. */
  autofill: boolean
}

export function planBankHandoff(
  wallet: BankWalletLike | undefined,
  target?: TransferTarget,
  platform: Platform = currentPlatform(),
): BankHandoff | undefined {
  if (!wallet || wallet.type !== 'bank' || platform === 'other') return
  const bank = walletBank(wallet)
  if (!bank) return
  const link = bankAppLink(bank, platform, target)
  if (!link) return
  return {
    bank,
    appName: bank.appName ?? bank.shortName,
    link,
    autofill: !!bank.autofill,
  }
}

export function bankHandoffAvailable(): boolean {
  return canOpenBankApp()
}

export function startBankHandoff(handoff: BankHandoff): void {
  openExternal(handoff.link)
}
