import { categoryColor } from '../lib/categoryColors'
import { formatDateShort } from '../lib/format'
import type { Transaction } from '../types'
import { CapBadge } from './CapBadge'
import { EmptyState } from './EmptyState'
import { Nominal } from './Nominal'

interface LedgerTableProps {
  transactions: Transaction[]
  newTransactionId?: number | null
  onEdit?: (transaction: Transaction) => void
  onDelete?: (transaction: Transaction) => void
}

/**
 * StyleGuide.md §4 — baris dengan garis bawah tipis ala buku kas, bukan kartu
 * per-baris. Nominal selalu rata kanan, font mono.
 */
export function LedgerTable({ transactions, newTransactionId, onEdit, onDelete }: LedgerTableProps) {
  if (transactions.length === 0) {
    return (
      <EmptyState message="Belum ada catatan hari ini — ketik pengeluaranmu di Telegram, langsung muncul di sini." />
    )
  }

  return (
    <div className="overflow-x-auto rounded-md border border-border bg-paper-elevated">
      <table className="w-full min-w-[640px] border-collapse">
        <thead>
          <tr className="border-b border-border text-left font-body text-[0.8125rem] text-ink-muted">
            <th className="px-4 py-3 font-normal">Tanggal</th>
            <th className="px-4 py-3 font-normal">Deskripsi</th>
            <th className="px-4 py-3 font-normal">Kategori</th>
            <th className="px-4 py-3 text-right font-normal">Nominal</th>
            {(onEdit || onDelete) && <th className="px-4 py-3 font-normal" />}
          </tr>
        </thead>
        <tbody>
          {transactions.map((transaction) => (
            <tr key={transaction.id} className="border-b border-border last:border-b-0">
              <td className="whitespace-nowrap px-4 py-3 font-data text-sm text-ink-muted">
                {formatDateShort(transaction.transaction_date)}
              </td>
              <td
                className="px-4 py-3 text-sm text-ink"
                style={{ borderLeft: `3px solid ${categoryColor(transaction.category.color_key)}` }}
              >
                <div className="flex items-center gap-2 pl-2">
                  {transaction.description}
                  {newTransactionId === transaction.id && <CapBadge />}
                </div>
              </td>
              <td className="px-4 py-3 text-sm text-ink-muted">{transaction.category.name}</td>
              <td className="px-4 py-3 text-right">
                <Nominal amount={transaction.amount} type={transaction.type} />
              </td>
              {(onEdit || onDelete) && (
                <td className="px-4 py-3 text-right text-sm whitespace-nowrap">
                  {onEdit && (
                    <button
                      type="button"
                      onClick={() => onEdit(transaction)}
                      className="mr-3 text-ink-muted hover:text-ink"
                    >
                      Edit
                    </button>
                  )}
                  {onDelete && (
                    <button
                      type="button"
                      onClick={() => onDelete(transaction)}
                      className="text-ledger-red hover:underline"
                    >
                      Hapus
                    </button>
                  )}
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
