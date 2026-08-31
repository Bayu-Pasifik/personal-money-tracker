import { useEffect, useRef, useState } from 'react'
import { CategoryPieChart } from '../components/CategoryPieChart'
import { LedgerTable } from '../components/LedgerTable'
import { SummaryCard } from '../components/SummaryCard'
import { TrendLineChart } from '../components/TrendLineChart'
import { api } from '../lib/api'
import type { MonthlySummary, PaginatedResponse, Transaction } from '../types'

const POLL_INTERVAL_MS = 15000

export default function Dashboard() {
  const [summary, setSummary] = useState<MonthlySummary | null>(null)
  const [transactions, setTransactions] = useState<Transaction[]>([])
  const [newTransactionId, setNewTransactionId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  const lastKnownIdRef = useRef<number | null>(null)

  async function load(isPoll = false) {
    try {
      const [summaryRes, transactionsRes] = await Promise.all([
        api.get<MonthlySummary>('/summary'),
        api.get<PaginatedResponse<Transaction>>('/transactions', { params: { per_page: 100 } }),
      ])

      setSummary(summaryRes.data)
      setTransactions(transactionsRes.data.data)

      const latestId = transactionsRes.data.data[0]?.id ?? null
      if (isPoll && latestId && lastKnownIdRef.current && latestId !== lastKnownIdRef.current) {
        setNewTransactionId(latestId)
        setTimeout(() => setNewTransactionId(null), 4000)
      }
      lastKnownIdRef.current = latestId
      setError(false)
    } catch {
      setError(true)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    const interval = setInterval(() => load(true), POLL_INTERVAL_MS)
    return () => clearInterval(interval)
  }, [])

  if (loading) {
    return <p className="text-sm text-ink-muted">Memuat…</p>
  }

  if (error || !summary) {
    return (
      <p className="text-sm text-ledger-red">
        Gagal memuat data dashboard. Coba muat ulang halaman.
      </p>
    )
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="font-display text-2xl font-semibold text-ink">Dashboard</h1>
        <p className="mt-1 text-sm text-ink-muted">Ringkasan {summary.month}</p>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <SummaryCard label="Saldo" amount={summary.balance} type="balance" />
        <SummaryCard label="Pemasukan" amount={summary.total_income} type="income" />
        <SummaryCard label="Pengeluaran" amount={summary.total_expense} type="expense" />
      </div>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div className="rounded-md border border-border bg-paper-elevated p-6">
          <h2 className="font-body text-lg font-semibold text-ink">Tren Harian</h2>
          <TrendLineChart transactions={transactions} />
        </div>
        <div className="rounded-md border border-border bg-paper-elevated p-6">
          <h2 className="font-body text-lg font-semibold text-ink">Breakdown Kategori</h2>
          <CategoryPieChart data={summary.by_category} />
        </div>
      </div>

      <div>
        <h2 className="mb-3 font-body text-lg font-semibold text-ink">Transaksi Terbaru</h2>
        <LedgerTable transactions={transactions.slice(0, 8)} newTransactionId={newTransactionId} />
      </div>
    </div>
  )
}
