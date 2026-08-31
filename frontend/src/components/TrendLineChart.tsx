import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { formatRupiah } from '../lib/format'
import type { Transaction } from '../types'
import { EmptyState } from './EmptyState'

interface DailyPoint {
  date: string
  income: number
  expense: number
}

function buildDailySeries(transactions: Transaction[]): DailyPoint[] {
  const byDate = new Map<string, DailyPoint>()

  for (const transaction of transactions) {
    const key = transaction.transaction_date.slice(0, 10)
    const point = byDate.get(key) ?? { date: key, income: 0, expense: 0 }
    point[transaction.type] += transaction.amount
    byDate.set(key, point)
  }

  return Array.from(byDate.values()).sort((a, b) => a.date.localeCompare(b.date))
}

/**
 * StyleGuide.md §5.5 — garis pemasukan pakai --ledger-green, pengeluaran
 * --ledger-red. Grid tipis warna --border, tanpa drop shadow.
 */
export function TrendLineChart({ transactions }: { transactions: Transaction[] }) {
  const data = buildDailySeries(transactions)

  if (data.length === 0) {
    return <EmptyState message="Belum ada tren untuk ditampilkan bulan ini." />
  }

  return (
    <ResponsiveContainer width="100%" height={260}>
      <LineChart data={data}>
        <CartesianGrid stroke="#D6D9CC" vertical={false} />
        <XAxis
          dataKey="date"
          tickFormatter={(value: string) => new Date(value).getDate().toString()}
          tick={{ fontSize: 12, fill: '#5B6358' }}
          axisLine={{ stroke: '#D6D9CC' }}
          tickLine={false}
        />
        <YAxis
          tickFormatter={(value: number) => formatRupiah(value)}
          tick={{ fontSize: 11, fill: '#5B6358' }}
          axisLine={false}
          tickLine={false}
          width={80}
        />
        <Tooltip formatter={(value) => formatRupiah(Number(value))} />
        <Line type="monotone" dataKey="income" stroke="#2F6F5E" strokeWidth={2} dot={false} />
        <Line type="monotone" dataKey="expense" stroke="#A63D2F" strokeWidth={2} dot={false} />
      </LineChart>
    </ResponsiveContainer>
  )
}
