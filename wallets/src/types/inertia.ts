export type AuthUser = {
  id: string
  name: string
  email: string
}

export type FlashMessages = {
  success?: string | null
  error?: string | null
  import_errors?: string[]
}

export type DebtGoalProgress = {
  name: string
  target_amount: number
  collected: number
  percent: number
  remaining: number
  wallet_id: number
  wallet_name: string
  reached: boolean
  loan_id?: number | null
  loan_name?: string | null
  principal_remaining?: number
  settlement_interest?: number
  settlement_fee?: number
  current_year?: number
  has_loan?: boolean
}

export type SharedPageProps = {
  auth: {
    user: AuthUser | null
  }
  flash: FlashMessages
  debtGoal: DebtGoalProgress | null
  errors: Record<string, string | string[]>
}
