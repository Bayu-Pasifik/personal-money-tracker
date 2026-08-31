export type TransactionType = 'income' | 'expense'

export interface Category {
  id: number
  user_id: number
  name: string
  type: TransactionType
  is_default: boolean
  color_key: string | null
}

export interface Transaction {
  id: number
  user_id: number
  category_id: number
  category: Category
  amount: number
  type: TransactionType
  description: string
  raw_input_text: string | null
  source: 'telegram' | 'web'
  ai_comment: string | null
  transaction_date: string
  created_at: string
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
}

export interface CategoryBreakdown {
  category_id: number
  category_name: string
  color_key: string | null
  type: TransactionType
  total: number
}

export interface MonthlySummary {
  month: string
  total_income: number
  total_expense: number
  balance: number
  by_category: CategoryBreakdown[]
}

export interface Budget {
  id: number
  category_id: number
  category_name: string
  month: string
  limit_amount: number
  spent: number
}
