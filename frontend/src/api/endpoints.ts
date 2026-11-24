import { http } from './http'
import type {
  AccessToken,
  DashboardSummary,
  Import,
  ImportFailure,
  Paginated,
  Transaction,
  TransactionFilters,
  User,
} from '@/types/api'

interface Item<T> {
  data: T
}

export const api = {
  login: (email: string, password: string) =>
    http<Item<AccessToken>>('/auth/login', { method: 'POST', body: { email, password, device_name: 'web' } }).then(
      (response) => response.data,
    ),
  logout: () => http<void>('/auth/logout', { method: 'POST' }),
  me: () => http<Item<User>>('/auth/me').then((response) => response.data),

  summary: () => http<Item<DashboardSummary>>('/dashboard/summary').then((response) => response.data),

  transactions: (filters: TransactionFilters) =>
    http<Paginated<Transaction>>('/transactions', { query: { ...filters } }),

  uploadImport: (file: File) => {
    const form = new FormData()
    form.append('file', file)
    return http<Item<Import>>('/imports', { method: 'POST', body: form }).then((response) => response.data)
  },
  imports: (page = 1) => http<Paginated<Import>>('/imports', { query: { page } }),
  import: (id: number) => http<Item<Import>>(`/imports/${id}`).then((response) => response.data),
  importFailures: (id: number, page = 1) => http<Paginated<ImportFailure>>(`/imports/${id}/failures`, { query: { page } }),
  retryImport: (id: number) => http<Item<Import>>(`/imports/${id}/retry`, { method: 'POST' }).then((r) => r.data),
}
