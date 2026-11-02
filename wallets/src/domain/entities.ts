export interface Wallet {
  id: number
  name: string
  type: string
  type_label: string
  balance: number
  is_active: boolean
  is_pinned: boolean
  order: number
  is_credit_card: boolean
  spendable_balance: number
  credit_limit: number
  outstanding_balance: number
  statement_day: number
  payment_day: number
  notes: string | null
  transactions_count: number
}
export interface Transaction {
  id: number
  wallet_id: number
  wallet: { id: number; name: string }
  wallet_name: string
  type: string
  type_label: string
  amount: number
  delta: number
  description: string
  category: string | null
  transacted_at: string
  note: string | null
  loan_id: number | null
  wallet_transfer_id: number | null
  is_transfer: boolean
  is_adjustment: boolean
  is_from_loan: boolean
  signed_amount: string
  display_color_class: string
  transacted_at_label: string
  adjustment_direction: string
  balance_before: number
  balance_after: number
  wallet_transfer: { from_wallet_name: string; to_wallet_name: string } | null
}
export interface Template {
  default_wallet_name: string | null
  from_wallet_name: string | null
  to_wallet_name: string | null
  id: number
  name: string
  type: string
  type_label: string
  amount: number
  fee: number
  description: string
  category: string
  default_wallet_id: number | null
  from_wallet_id: number | null
  to_wallet_id: number | null
  adjustment_direction: string
  default_wallet: Wallet | null
  from_wallet: Wallet | null
  to_wallet: Wallet | null
}
export interface Period {
  id: number
  month_index: number
  due_date: string
  due_date_label: string
  days: number
  principal: number
  interest: number
  fee: number
  payment: number
  remaining_principal: number
  is_paid: boolean
  status: string
}
export interface Payment {
  paid_at_label?: string
  id: number
  amount: number
  paid_at: string
  note: string | null
  kind: string
  kind_label: string
  is_early: boolean
  reduces_principal: boolean
  period_due_date?: string
  month_index?: number
  period?: { date: string }
}
export interface Loan {
  id: number
  name: string
  type: string
  wallet_id: number | null
  wallet: Wallet | null
  principal_amount: number
  interest_rate: number
  term_months: number
  started_at: string
  payment_day: number
  interest_calculation_method: string
  monthly_payment: number
  collection_fee: number
  months_paid: number
  is_settled: boolean
  remaining_principal: number
  remaining_amount: number
  payoff_remaining: number
  remaining_interest: number
  months_passed: number
  remaining_months: number
  schedule: Period[]
  payments: Payment[]
  next_payment: Period | null
  next_due_date: string | null
  next_payment_amount: number
  interest_label: string
  started_at_label: string
}
export interface Reminder {
  type_label: string
  type_badge_class: string
  id: number
  kind: string
  name: string
  amount: number
  due_date: string
  days_until: number
  insufficient_funds: boolean
  pay_url: string
  wallet_name: string
  wallet_balance: number
  loan_id: number
  period_id: number
  status: string
  type: string
  due_date_label: string
}
export interface Holiday {
  id: number
  date: string
  name: string
  type: string
}
export interface Pagination<T> {
  data: T[]
  links: { url: string | null; label: string; active: boolean }[]
  meta: { current_page: number; last_page: number; total: number }
}
export interface CashFlowStats {
  range_label: string
  period_label: string
  has_data: boolean
  current: { income: number; expense: number; net: number }
  series: {
    label: string
    label_full: string
    income: number
    expense: number
    net: number
  }[]
}
export interface Settings {
  recurring_alert_days: number
  telegram_enabled: boolean
  telegram_chat_id: string
}
export interface Prefill {
  wallet_id?: number
  type?: string
  amount?: number
  description?: string
  category?: string
  transacted_at?: string
  recurring_item_id?: number
  recurring_occurrence_id?: number
}
export interface BulkRow {
  key: number
  type: string
  wallet_id: number | string
  from_wallet_id: number | string
  to_wallet_id: number | string
  amount: number | string
  fee: number
  description: string
  category: string
  note: string
  transaction_template_id: number | string
}

export interface TimelineRow {
  type: string
  period: Period
  period_due_date: string
  is_paid: boolean
  period_payment: number | null
  payment: Payment | null
  note: string
}
