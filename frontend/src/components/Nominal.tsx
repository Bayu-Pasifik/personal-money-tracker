import { formatRupiah } from '../lib/format'
import type { TransactionType } from '../types'

/**
 * StyleGuide.md §3: nominal SELALU IBM Plex Mono, rata kanan, disertai tanda +/-
 * (tidak mengandalkan warna saja untuk arah transaksi — aksesibilitas §8).
 */
export function Nominal({ amount, type, size = 'table' }: { amount: number; type: TransactionType; size?: 'table' | 'hero' }) {
  const sign = type === 'income' ? '+' : '-'
  const colorClass = type === 'income' ? 'text-ledger-green' : 'text-ledger-red'
  const sizeClass = size === 'hero' ? 'text-[2.25rem] font-medium' : 'text-[0.9375rem] font-medium'

  return (
    <span className={`font-data tabular-nums ${colorClass} ${sizeClass}`}>
      {sign}
      {formatRupiah(amount)}
    </span>
  )
}
