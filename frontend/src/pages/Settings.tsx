import { useEffect, useState, type FormEvent } from 'react'
import { api } from '../lib/api'
import { formatRupiah } from '../lib/format'
import type { Budget, Category, TransactionType, Wallet } from '../types'

export default function Settings() {
  const [code, setCode] = useState<string | null>(null)
  const [expiresIn, setExpiresIn] = useState<number | null>(null)
  const [connectError, setConnectError] = useState<string | null>(null)
  const [connectLoading, setConnectLoading] = useState(false)

  const [categories, setCategories] = useState<Category[]>([])
  const [newName, setNewName] = useState('')
  const [newType, setNewType] = useState<TransactionType>('expense')
  const [categoryError, setCategoryError] = useState<string | null>(null)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [editingName, setEditingName] = useState('')

  const currentMonth = new Date().toISOString().slice(0, 7)
  const [budgets, setBudgets] = useState<Budget[]>([])
  const [budgetCategoryId, setBudgetCategoryId] = useState<number | ''>('')
  const [budgetLimit, setBudgetLimit] = useState('')
  const [budgetError, setBudgetError] = useState<string | null>(null)

  const [wallets, setWallets] = useState<Wallet[]>([])
  const [newWalletName, setNewWalletName] = useState('')
  const [walletError, setWalletError] = useState<string | null>(null)
  const [editingWalletId, setEditingWalletId] = useState<number | null>(null)
  const [editingWalletName, setEditingWalletName] = useState('')

  async function loadCategories() {
    const res = await api.get<Category[]>('/categories')
    setCategories(res.data)
  }

  async function loadBudgets() {
    const res = await api.get<Budget[]>('/budgets', { params: { month: currentMonth } })
    setBudgets(res.data)
  }

  async function loadWallets() {
    const res = await api.get<Wallet[]>('/wallets')
    setWallets(res.data)
  }

  useEffect(() => {
    loadCategories()
    loadBudgets()
    loadWallets()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function handleGenerateCode() {
    setConnectLoading(true)
    setConnectError(null)
    try {
      const res = await api.post('/telegram/connect-code')
      setCode(res.data.code)
      setExpiresIn(res.data.expires_in_minutes)
    } catch {
      setConnectError('Gagal membuat kode koneksi. Coba lagi.')
    } finally {
      setConnectLoading(false)
    }
  }

  async function handleAddCategory(event: FormEvent) {
    event.preventDefault()
    setCategoryError(null)
    const name = newName.trim()
    if (!name) {
      setCategoryError('Nama kategori belum diisi.')
      return
    }
    try {
      await api.post('/categories', { name, type: newType })
      setNewName('')
      await loadCategories()
    } catch {
      setCategoryError('Gagal menambah kategori. Coba lagi.')
    }
  }

  async function handleRename(category: Category) {
    const name = editingName.trim()
    if (!name) return
    try {
      await api.put(`/categories/${category.id}`, { name })
      setEditingId(null)
      await loadCategories()
    } catch {
      setCategoryError('Gagal mengubah nama kategori.')
    }
  }

  async function handleDelete(category: Category) {
    if (!confirm(`Hapus kategori "${category.name}"?`)) return
    try {
      await api.delete(`/categories/${category.id}`)
      await loadCategories()
    } catch {
      setCategoryError('Kategori ini masih dipakai di transaksi, tidak bisa dihapus.')
    }
  }

  const expenseCategories = categories.filter((c) => c.type === 'expense')

  async function handleSetBudget(event: FormEvent) {
    event.preventDefault()
    setBudgetError(null)
    const limit = Number(budgetLimit)
    if (!budgetCategoryId) {
      setBudgetError('Pilih kategori dulu.')
      return
    }
    if (!limit || limit <= 0) {
      setBudgetError('Isi limit lebih dari 0.')
      return
    }
    try {
      await api.post('/budgets', { category_id: budgetCategoryId, month: currentMonth, limit_amount: limit })
      setBudgetLimit('')
      await loadBudgets()
    } catch {
      setBudgetError('Gagal menyimpan budget. Coba lagi.')
    }
  }

  async function handleDeleteBudget(budget: Budget) {
    if (!confirm(`Hapus budget untuk "${budget.category_name}"?`)) return
    await api.delete(`/budgets/${budget.id}`)
    await loadBudgets()
  }

  async function handleAddWallet(event: FormEvent) {
    event.preventDefault()
    setWalletError(null)
    const name = newWalletName.trim()
    if (!name) {
      setWalletError('Nama dompet belum diisi.')
      return
    }
    try {
      await api.post('/wallets', { name })
      setNewWalletName('')
      await loadWallets()
    } catch {
      setWalletError('Gagal menambah dompet. Coba lagi.')
    }
  }

  async function handleRenameWallet(wallet: Wallet) {
    const name = editingWalletName.trim()
    if (!name) return
    try {
      await api.put(`/wallets/${wallet.id}`, { name })
      setEditingWalletId(null)
      await loadWallets()
    } catch {
      setWalletError('Gagal mengubah nama dompet.')
    }
  }

  async function handleDeleteWallet(wallet: Wallet) {
    if (!confirm(`Hapus dompet "${wallet.name}"?`)) return
    try {
      await api.delete(`/wallets/${wallet.id}`)
      await loadWallets()
    } catch {
      setWalletError('Gagal menghapus dompet.')
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="font-display text-2xl font-semibold text-ink">Pengaturan</h1>

      <div className="max-w-lg rounded-md border border-border bg-paper-elevated p-6">
        <h2 className="font-body text-lg font-semibold text-ink">Hubungkan Telegram</h2>
        <p className="mt-1 text-sm text-ink-muted">
          Ambil kode koneksi, lalu kirim <code className="font-data">/start KODE</code> ke bot FinTrack AI di Telegram.
        </p>

        <button
          type="button"
          onClick={handleGenerateCode}
          disabled={connectLoading}
          className="mt-4 rounded-md bg-ledger-green px-4 py-2 text-sm text-paper disabled:opacity-60"
        >
          {connectLoading ? 'Membuat kode…' : 'Buat Kode Koneksi'}
        </button>

        {code && (
          <div className="mt-4 rounded-md border border-stamp-gold bg-paper px-4 py-3">
            <p className="font-data text-2xl font-medium tracking-widest text-ink">{code}</p>
            <p className="mt-1 text-xs text-ink-muted">Berlaku {expiresIn} menit.</p>
          </div>
        )}

        {connectError && <p className="mt-3 text-sm text-ledger-red">{connectError}</p>}
      </div>

      <div className="max-w-lg rounded-md border border-border bg-paper-elevated p-6">
        <h2 className="font-body text-lg font-semibold text-ink">Kategori</h2>
        <p className="mt-1 text-sm text-ink-muted">
          Kategori bawaan tidak bisa diubah. Tambah kategori sendiri sesuai kebutuhanmu.
        </p>

        <ul className="mt-4 divide-y divide-border">
          {categories.map((category) => (
            <li key={category.id} className="flex items-center justify-between py-2 text-sm">
              {editingId === category.id ? (
                <div className="flex flex-1 items-center gap-2">
                  <input
                    type="text"
                    value={editingName}
                    onChange={(e) => setEditingName(e.target.value)}
                    className="flex-1 rounded-md border border-border bg-paper px-2 py-1 text-sm text-ink"
                  />
                  <button type="button" onClick={() => handleRename(category)} className="text-ledger-green">
                    Simpan
                  </button>
                  <button type="button" onClick={() => setEditingId(null)} className="text-ink-muted">
                    Batal
                  </button>
                </div>
              ) : (
                <>
                  <span className="text-ink">
                    {category.name}{' '}
                    <span className="text-ink-muted">({category.type === 'income' ? 'Pemasukan' : 'Pengeluaran'})</span>
                  </span>
                  {!category.is_default && (
                    <span className="flex gap-3">
                      <button
                        type="button"
                        onClick={() => {
                          setEditingId(category.id)
                          setEditingName(category.name)
                        }}
                        className="text-ink-muted hover:text-ink"
                      >
                        Edit
                      </button>
                      <button type="button" onClick={() => handleDelete(category)} className="text-ledger-red hover:underline">
                        Hapus
                      </button>
                    </span>
                  )}
                </>
              )}
            </li>
          ))}
        </ul>

        <form onSubmit={handleAddCategory} className="mt-4 flex gap-2">
          <input
            type="text"
            value={newName}
            onChange={(e) => setNewName(e.target.value)}
            placeholder="Nama kategori baru"
            className="flex-1 rounded-md border border-border bg-paper px-3 py-2 text-sm text-ink"
          />
          <select
            value={newType}
            onChange={(e) => setNewType(e.target.value as TransactionType)}
            className="rounded-md border border-border bg-paper px-2 py-2 text-sm text-ink"
          >
            <option value="expense">Pengeluaran</option>
            <option value="income">Pemasukan</option>
          </select>
          <button type="submit" className="rounded-md bg-ledger-green px-4 py-2 text-sm text-paper">
            Tambah
          </button>
        </form>

        {categoryError && <p className="mt-3 text-sm text-ledger-red">{categoryError}</p>}
      </div>

      <div className="max-w-lg rounded-md border border-border bg-paper-elevated p-6">
        <h2 className="font-body text-lg font-semibold text-ink">Budget Bulan Ini</h2>
        <p className="mt-1 text-sm text-ink-muted">
          Atur limit pengeluaran per kategori. Kamu dapat notifikasi di Telegram kalau sudah mendekati atau melewati limit.
        </p>

        {budgets.length === 0 ? (
          <p className="mt-4 text-sm text-ink-muted">Belum ada budget yang diatur bulan ini.</p>
        ) : (
          <ul className="mt-4 space-y-3">
            {budgets.map((budget) => {
              const ratio = budget.limit_amount > 0 ? budget.spent / budget.limit_amount : 0
              const over = ratio >= 1
              return (
                <li key={budget.id}>
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-ink">{budget.category_name}</span>
                    <span className="flex items-center gap-2">
                      <span className={`font-data ${over ? 'text-ledger-red' : 'text-ink-muted'}`}>
                        {formatRupiah(budget.spent)} / {formatRupiah(budget.limit_amount)}
                      </span>
                      <button type="button" onClick={() => handleDeleteBudget(budget)} className="text-ledger-red hover:underline">
                        Hapus
                      </button>
                    </span>
                  </div>
                  <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-paper">
                    <div
                      className={`h-full ${over ? 'bg-ledger-red' : 'bg-ledger-green'}`}
                      style={{ width: `${Math.min(ratio * 100, 100)}%` }}
                    />
                  </div>
                </li>
              )
            })}
          </ul>
        )}

        <form onSubmit={handleSetBudget} className="mt-4 flex gap-2">
          <select
            value={budgetCategoryId}
            onChange={(e) => setBudgetCategoryId(e.target.value ? Number(e.target.value) : '')}
            className="flex-1 rounded-md border border-border bg-paper px-2 py-2 text-sm text-ink"
          >
            <option value="">Pilih kategori…</option>
            {expenseCategories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
          <input
            type="number"
            value={budgetLimit}
            onChange={(e) => setBudgetLimit(e.target.value)}
            placeholder="Limit (Rp)"
            className="w-32 rounded-md border border-border bg-paper px-3 py-2 font-data text-sm text-ink"
          />
          <button type="submit" className="rounded-md bg-ledger-green px-4 py-2 text-sm text-paper">
            Simpan
          </button>
        </form>

        {budgetError && <p className="mt-3 text-sm text-ledger-red">{budgetError}</p>}
      </div>

      <div className="max-w-lg rounded-md border border-border bg-paper-elevated p-6">
        <h2 className="font-body text-lg font-semibold text-ink">Dompet</h2>
        <p className="mt-1 text-sm text-ink-muted">
          Pisahkan pencatatan per rekening/tunai. Dompet default tidak bisa dihapus.
        </p>

        <ul className="mt-4 divide-y divide-border">
          {wallets.map((wallet) => (
            <li key={wallet.id} className="flex items-center justify-between py-2 text-sm">
              {editingWalletId === wallet.id ? (
                <div className="flex flex-1 items-center gap-2">
                  <input
                    type="text"
                    value={editingWalletName}
                    onChange={(e) => setEditingWalletName(e.target.value)}
                    className="flex-1 rounded-md border border-border bg-paper px-2 py-1 text-sm text-ink"
                  />
                  <button type="button" onClick={() => handleRenameWallet(wallet)} className="text-ledger-green">
                    Simpan
                  </button>
                  <button type="button" onClick={() => setEditingWalletId(null)} className="text-ink-muted">
                    Batal
                  </button>
                </div>
              ) : (
                <>
                  <span className="text-ink">
                    {wallet.name} {wallet.is_default && <span className="text-ink-muted">(default)</span>}
                  </span>
                  <span className="flex gap-3">
                    <button
                      type="button"
                      onClick={() => {
                        setEditingWalletId(wallet.id)
                        setEditingWalletName(wallet.name)
                      }}
                      className="text-ink-muted hover:text-ink"
                    >
                      Edit
                    </button>
                    {!wallet.is_default && (
                      <button type="button" onClick={() => handleDeleteWallet(wallet)} className="text-ledger-red hover:underline">
                        Hapus
                      </button>
                    )}
                  </span>
                </>
              )}
            </li>
          ))}
        </ul>

        <form onSubmit={handleAddWallet} className="mt-4 flex gap-2">
          <input
            type="text"
            value={newWalletName}
            onChange={(e) => setNewWalletName(e.target.value)}
            placeholder="Nama dompet baru"
            className="flex-1 rounded-md border border-border bg-paper px-3 py-2 text-sm text-ink"
          />
          <button type="submit" className="rounded-md bg-ledger-green px-4 py-2 text-sm text-paper">
            Tambah
          </button>
        </form>

        {walletError && <p className="mt-3 text-sm text-ledger-red">{walletError}</p>}
      </div>
    </div>
  )
}
