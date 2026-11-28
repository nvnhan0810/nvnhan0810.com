export interface WalletLike {
  balance: number
  is_active: boolean
  is_credit_card: boolean
  credit_limit?: number | null
  outstanding_balance?: number | null
}

export interface WalletSummary {
  /** Sum of active, non credit card balances. */
  cash: number
  /** Outstanding debt on active credit cards. */
  creditDebt: number
  /** Unused limit on active credit cards. */
  creditAvailable: number
  activeCount: number
  inactiveCount: number
}

export function summarizeWallets(
  wallets: readonly WalletLike[],
): WalletSummary {
  const summary: WalletSummary = {
    cash: 0,
    creditDebt: 0,
    creditAvailable: 0,
    activeCount: 0,
    inactiveCount: 0,
  }
  for (const w of wallets) {
    if (!w.is_active) {
      summary.inactiveCount++
      continue
    }
    summary.activeCount++
    if (w.is_credit_card) {
      const debt = Number(w.outstanding_balance) || 0
      summary.creditDebt += debt
      summary.creditAvailable += Math.max(
        0,
        (Number(w.credit_limit) || 0) - debt,
      )
    } else {
      summary.cash += Number(w.balance) || 0
    }
  }
  return summary
}

/** Share of the credit limit in use, clamped to 0–100. */
export function creditUsagePercent(w: WalletLike): number {
  const limit = Number(w.credit_limit) || 0
  if (limit <= 0) return 0
  const pct = ((Number(w.outstanding_balance) || 0) / limit) * 100
  return Math.min(100, Math.max(0, Math.round(pct)))
}
