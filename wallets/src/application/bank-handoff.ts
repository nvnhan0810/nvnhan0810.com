// After saving a transaction from a bank wallet on a phone, hand off to the
// wallet's bank app (transfer screen) — prefilled when it came from a VietQR.

import { walletBank, type Bank } from '@/domain/banking/banks'
import {
  bankAppLink,
  type Platform,
  type TransferTarget,
} from '@/domain/banking/bank-app'
import { reactive } from 'vue'
import {
  canOpenBankApp,
  currentPlatform,
  hasUserActivation,
  onPageHidden,
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

/**
 * The handoff waiting for the user to open the bank app. Shown as a prompt
 * with a real link: browsers block switching to another app once the tap
 * that saved the transaction is too old (Chrome on Android silently drops
 * the `intent://` redirect), so a fresh tap is the reliable path.
 */
export const pendingHandoff = reactive<{
  handoff: BankHandoff | null
  /** Recipient account copied to the clipboard for apps without autofill. */
  copiedAccount: string
}>({ handoff: null, copiedAccount: '' })

let stopWatchingPage: (() => void) | undefined

export function dismissBankHandoff(): void {
  stopWatchingPage?.()
  stopWatchingPage = undefined
  pendingHandoff.handoff = null
  pendingHandoff.copiedAccount = ''
}

/**
 * Right after the transaction is saved: open the bank app if the browser
 * still allows it, and keep a prompt to open it by tap otherwise. The prompt
 * closes by itself once the page goes to the background (app opened).
 */
export function startBankHandoff(
  handoff: BankHandoff,
  copiedAccount = '',
): void {
  dismissBankHandoff()
  pendingHandoff.handoff = handoff
  pendingHandoff.copiedAccount = copiedAccount
  stopWatchingPage = onPageHidden(dismissBankHandoff)
  if (hasUserActivation()) openExternal(handoff.link)
}
