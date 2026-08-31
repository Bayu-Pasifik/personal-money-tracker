import type { TransactionType } from '../types'
import { formatRupiah } from '../lib/format'

interface SummaryCardProps {
  label: string
  amount: number
  type?: TransactionType | 'balance'
}

export function SummaryCard({ label, amount, type }: SummaryCardProps) {
  const colorClass =
    type === 'income' ? 'text-ledger-green' : type === 'expense' ? 'text-ledger-red' : 'text-ink'

  return (
    <div className="rounded-md border border-border bg-paper-elevated p-6">
      <p className="font-body text-[0.8125rem] text-ink-muted">{label}</p>
      <p className={`mt-2 font-data text-[2.25rem] font-medium tabular-nums ${colorClass}`}>
        {formatRupiah(amount)}
      </p>
    </div>
  )
}
