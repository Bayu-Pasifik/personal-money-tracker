import { useEffect, useState } from 'react'
import { LedgerTable } from '../components/LedgerTable'
import { TransactionFormModal } from '../components/TransactionFormModal'
import { api } from '../lib/api'
import type { Category, PaginatedResponse, Transaction, TransactionType } from '../types'

interface Filters {
  type: TransactionType | ''
  category_id: string
  search: string
}

export default function Transactions() {
  const [transactions, setTransactions] = useState<Transaction[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [filters, setFilters] = useState<Filters>({ type: '', category_id: '', search: '' })
  const [editing, setEditing] = useState<Transaction | null>(null)
  const [showForm, setShowForm] = useState(false)

  async function loadCategories() {
    const res = await api.get<Category[]>('/categories')
    setCategories(res.data)
  }

  async function loadTransactions() {
    setLoading(true)
    try {
      const res = await api.get<PaginatedResponse<Transaction>>('/transactions', {
        params: {
          type: filters.type || undefined,
          category_id: filters.category_id || undefined,
          search: filters.search || undefined,
          per_page: 50,
        },
      })
      setTransactions(res.data.data)
      setError(null)
    } catch {
      setError('Gagal memuat riwayat transaksi. Coba muat ulang halaman.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadCategories()
  }, [])

  useEffect(() => {
    loadTransactions()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters])

  async function handleCreateOrUpdate(payload: {
    category_id: number
    amount: number
    type: TransactionType
    description: string
    transaction_date: string
  }) {
    if (editing) {
      await api.put(`/transactions/${editing.id}`, payload)
    } else {
      await api.post('/transactions', payload)
    }
    setShowForm(false)
    setEditing(null)
    await loadTransactions()
  }

  async function handleDelete(transaction: Transaction) {
    if (!confirm(`Hapus transaksi "${transaction.description}"?`)) return
    await api.delete(`/transactions/${transaction.id}`)
    await loadTransactions()
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-semibold text-ink">Riwayat Transaksi</h1>
        <button
          type="button"
          onClick={() => {
            setEditing(null)
            setShowForm(true)
          }}
          className="rounded-md bg-ledger-green px-4 py-2 text-sm text-paper"
        >
          Tambah Transaksi
        </button>
      </div>

      <div className="flex flex-wrap gap-3">
        <select
          value={filters.type}
          onChange={(e) => setFilters((f) => ({ ...f, type: e.target.value as Filters['type'] }))}
          className="rounded-md border border-border bg-paper-elevated px-3 py-2 text-sm text-ink"
        >
          <option value="">Semua Tipe</option>
          <option value="expense">Pengeluaran</option>
          <option value="income">Pemasukan</option>
        </select>

        <select
          value={filters.category_id}
          onChange={(e) => setFilters((f) => ({ ...f, category_id: e.target.value }))}
          className="rounded-md border border-border bg-paper-elevated px-3 py-2 text-sm text-ink"
        >
          <option value="">Semua Kategori</option>
          {categories.map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>

        <input
          type="text"
          placeholder="Cari deskripsi…"
          value={filters.search}
          onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
          className="rounded-md border border-border bg-paper-elevated px-3 py-2 text-sm text-ink"
        />
      </div>

      {error && <p className="text-sm text-ledger-red">{error}</p>}

      {loading ? (
        <p className="text-sm text-ink-muted">Memuat…</p>
      ) : (
        <LedgerTable
          transactions={transactions}
          onEdit={(transaction) => {
            setEditing(transaction)
            setShowForm(true)
          }}
          onDelete={handleDelete}
        />
      )}

      {showForm && (
        <TransactionFormModal
          categories={categories}
          initial={editing}
          onClose={() => {
            setShowForm(false)
            setEditing(null)
          }}
          onSubmit={handleCreateOrUpdate}
        />
      )}
    </div>
  )
}
