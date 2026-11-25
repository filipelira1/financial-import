/** Contracts of the Laravel API (backend/app/Http/Resources). Money is always integer cents. */

export type TransactionType = 'income' | 'expense'

export type ImportStatus = 'pending' | 'processing' | 'completed' | 'completed_with_errors' | 'failed'

export interface User {
  id: number
  name: string
  email: string
}

export interface AccessToken {
  token: string
  token_type: 'Bearer'
  expires_at: string | null
  user: User
}

export interface DashboardSummary {
  currency: 'BRL'
  income_cents: number
  expense_cents: number
  balance_cents: number
  transaction_count: number
}

export interface MonthlyTotals {
  /** YYYY-MM */
  month: string
  income_cents: number
  expense_cents: number
  balance_cents: number
  transaction_count: number
}

export interface Transaction {
  id: number
  occurred_on: string
  description: string
  amount_cents: number
  type: TransactionType
  import_id: number | null
}

export interface Import {
  id: number
  original_name: string
  status: ImportStatus
  is_finished: boolean
  total_rows: number | null
  processed_rows: number
  imported_rows: number
  failed_rows: number
  progress: number
  failure_reason: string | null
  created_at: string | null
  started_at: string | null
  finished_at: string | null
}

export interface ImportFailure {
  line_number: number
  raw: string
  errors: string[]
}

export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}

export interface TransactionFilters {
  type?: TransactionType
  from?: string
  to?: string
  /** Part of the description, case-insensitive */
  q?: string
  page?: number
  per_page?: number
}
