import { BANKS, type Bank } from './bank-list'

export type { Bank }
export { BANKS }

const byBin = new Map(BANKS.map((b) => [b.bin, b]))
const byCode = new Map(BANKS.map((b) => [b.code.toUpperCase(), b]))

export function findBankByBin(
  bin: string | null | undefined,
): Bank | undefined {
  return bin ? byBin.get(bin.trim()) : undefined
}

export function findBankByCode(
  code: string | null | undefined,
): Bank | undefined {
  return code ? byCode.get(code.trim().toUpperCase()) : undefined
}

/** Bank for a wallet: BIN first (what VietQR uses), code as a fallback. */
export function walletBank(wallet: {
  bank_bin?: string | null
  bank_code?: string | null
}): Bank | undefined {
  return findBankByBin(wallet.bank_bin) ?? findBankByCode(wallet.bank_code)
}

export function bankLabel(bank: Bank): string {
  return `${bank.shortName} (${bank.code})`
}

/** "0011001932418" → "•••• 2418" for compact lists. */
export function maskAccountNumber(account: string | null | undefined): string {
  const value = (account ?? '').trim()
  if (value.length <= 4) return value
  return `•••• ${value.slice(-4)}`
}

/** Digits/letters only, as banks and VietQR expect. */
export function normalizeAccountNumber(value: string): string {
  return value.replace(/[^0-9A-Za-z]/g, '')
}
