import { useEffect, useState, type FormEvent } from 'react'
import type { Category, Transaction, TransactionType, Wallet } from '../types'

interface TransactionFormModalProps {
  categories: Category[]
  wallets?: Wallet[]
  initial?: Transaction | null
  onClose: () => void
  onSubmit: (payload: {
    category_id: number
    wallet_id?: number
    amount: number
    type: TransactionType
    description: string
    transaction_date: string
  }) => Promise<void>
}

export function TransactionFormModal({ categories, wallets = [], initial, onClose, onSubmit }: TransactionFormModalProps) {
  const [type, setType] = useState<TransactionType>(initial?.type ?? 'expense')
  const [categoryId, setCategoryId] = useState<number>(
    initial?.category_id ?? categories.find((category) => category.type === (initial?.type ?? 'expense'))?.id ?? 0,
  )
  const [walletId, setWalletId] = useState<number | ''>(
    initial?.wallet_id ?? wallets.find((w) => w.is_default)?.id ?? '',
  )
  const [amount, setAmount] = useState<string>(initial ? String(initial.amount) : '')
  const [description, setDescription] = useState(initial?.description ?? '')
  const [date, setDate] = useState(initial?.transaction_date.slice(0, 10) ?? new Date().toISOString().slice(0, 10))
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const filteredCategories = categories.filter((category) => category.type === type)

  // Kategori terpilih harus selalu konsisten dengan tipe aktif — kalau tidak
  // di-reset saat tipe berganti, submit bisa menyimpan category_id dari tipe lain.
  useEffect(() => {
    if (!filteredCategories.some((category) => category.id === categoryId)) {
      setCategoryId(filteredCategories[0]?.id ?? 0)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [type, categories])

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)

    const parsedAmount = Number(amount)
    if (!parsedAmount || parsedAmount <= 0) {
      setError('Nominalnya belum kebaca. Isi angka lebih dari 0, mis. 30000.')
      return
    }
    if (!description.trim()) {
      setError('Deskripsi belum diisi.')
      return
    }
    if (!categoryId) {
      setError('Pilih kategori dulu.')
      return
    }

    setSubmitting(true)
    try {
      await onSubmit({
        category_id: categoryId,
        wallet_id: walletId || undefined,
        amount: parsedAmount,
        type,
        description: description.trim(),
        transaction_date: date,
      })
    } catch {
      setError('Gagal menyimpan transaksi. Coba lagi sebentar lagi.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-md rounded-md border border-border bg-paper-elevated p-6"
      >
        <h2 className="font-display text-xl font-semibold text-ink">
          {initial ? 'Edit Transaksi' : 'Tambah Transaksi'}
        </h2>

        <div className="mt-4 flex gap-2">
          {(['expense', 'income'] as TransactionType[]).map((option) => (
            <button
              key={option}
              type="button"
              onClick={() => setType(option)}
              className={`rounded-md border px-3 py-1.5 text-sm ${
                type === option
                  ? 'border-ledger-green bg-ledger-green text-paper'
                  : 'border-border text-ink-muted'
              }`}
            >
              {option === 'expense' ? 'Pengeluaran' : 'Pemasukan'}
            </button>
          ))}
        </div>

        <label className="mt-4 block text-sm text-ink-muted">
          Kategori
          <select
            value={categoryId}
            onChange={(e) => setCategoryId(Number(e.target.value))}
            className="mt-1 w-full rounded-md border border-border bg-paper px-3 py-2 font-body text-sm text-ink"
          >
            {filteredCategories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
        </label>

        {wallets.length > 1 && (
          <label className="mt-4 block text-sm text-ink-muted">
            Dompet
            <select
              value={walletId}
              onChange={(e) => setWalletId(e.target.value ? Number(e.target.value) : '')}
              className="mt-1 w-full rounded-md border border-border bg-paper px-3 py-2 font-body text-sm text-ink"
            >
              {wallets.map((wallet) => (
                <option key={wallet.id} value={wallet.id}>
                  {wallet.name}
                </option>
              ))}
            </select>
          </label>
        )}

        <label className="mt-4 block text-sm text-ink-muted">
          Nominal (Rp)
          <input
            type="number"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            className="mt-1 w-full rounded-md border border-border bg-paper px-3 py-2 font-data text-sm text-ink"
            placeholder="30000"
          />
        </label>

        <label className="mt-4 block text-sm text-ink-muted">
          Deskripsi
          <input
            type="text"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            className="mt-1 w-full rounded-md border border-border bg-paper px-3 py-2 font-body text-sm text-ink"
            placeholder="Makan malam"
          />
        </label>

        <label className="mt-4 block text-sm text-ink-muted">
          Tanggal
          <input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            className="mt-1 w-full rounded-md border border-border bg-paper px-3 py-2 font-data text-sm text-ink"
          />
        </label>

        {error && <p className="mt-3 text-sm text-ledger-red">{error}</p>}

        <div className="mt-6 flex justify-end gap-3">
          <button type="button" onClick={onClose} className="rounded-md border border-border px-4 py-2 text-sm text-ink">
            Batal
          </button>
          <button
            type="submit"
            disabled={submitting}
            className="rounded-md bg-ledger-green px-4 py-2 text-sm text-paper disabled:opacity-60"
          >
            {submitting ? 'Menyimpan…' : 'Simpan Transaksi'}
          </button>
        </div>
      </form>
    </div>
  )
}
