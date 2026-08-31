import { useEffect, useState, type FormEvent } from 'react'
import { api } from '../lib/api'
import type { Category, TransactionType } from '../types'

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

  async function loadCategories() {
    const res = await api.get<Category[]>('/categories')
    setCategories(res.data)
  }

  useEffect(() => {
    loadCategories()
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
    </div>
  )
}
