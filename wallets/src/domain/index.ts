export { formatMoney, parseMoney } from '@/domain/money/money'
export { formatDateVi, todayVi, greetingLabel } from '@/domain/time/date-vi'
export {
  WALLET_TYPES,
  WALLET_TYPE_LABELS,
  WalletType,
} from '@/domain/catalog/wallet-type'
export type { WalletTypeId } from '@/domain/catalog/wallet-type'
export {
  summarizeWallets,
  creditUsagePercent,
} from '@/domain/catalog/wallet-summary'
export type { WalletSummary } from '@/domain/catalog/wallet-summary'
export {
  TX_TYPES,
  TX_TYPE_LABELS,
  TxType,
} from '@/domain/catalog/transaction-type'
export type { TxTypeId } from '@/domain/catalog/transaction-type'
export {
  FixedExpenseGranularity,
  FixedExpenseSource,
  FIXED_EXPENSE_DEFAULT_DURATION,
  FIXED_EXPENSE_DEFAULT_CUSTOM_DAYS,
} from '@/domain/reporting/fixed-expense'
export type { FixedExpenseGranularityId } from '@/domain/reporting/fixed-expense'

export { toggleTheme } from '@/infrastructure/theme/theme-storage'
export {
  BANKS,
  findBankByBin,
  findBankByCode,
  walletBank,
  bankLabel,
  maskAccountNumber,
  normalizeAccountNumber,
} from '@/domain/banking/banks'
export type { Bank } from '@/domain/banking/banks'
export { parseVietQr, buildVietQr, VietQrError } from '@/domain/banking/vietqr'
export type { VietQrTransfer } from '@/domain/banking/vietqr'
export { bankAppLink, bankAppId, transferNote } from '@/domain/banking/bank-app'
export type { Platform, TransferTarget } from '@/domain/banking/bank-app'
