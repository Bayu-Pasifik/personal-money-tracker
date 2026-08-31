import { useState } from 'react'
import { api } from '../lib/api'

export default function Settings() {
  const [code, setCode] = useState<string | null>(null)
  const [expiresIn, setExpiresIn] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function handleGenerateCode() {
    setLoading(true)
    setError(null)
    try {
      const res = await api.post('/telegram/connect-code')
      setCode(res.data.code)
      setExpiresIn(res.data.expires_in_minutes)
    } catch {
      setError('Gagal membuat kode koneksi. Coba lagi.')
    } finally {
      setLoading(false)
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
          disabled={loading}
          className="mt-4 rounded-md bg-ledger-green px-4 py-2 text-sm text-paper disabled:opacity-60"
        >
          {loading ? 'Membuat kode…' : 'Buat Kode Koneksi'}
        </button>

        {code && (
          <div className="mt-4 rounded-md border border-stamp-gold bg-paper px-4 py-3">
            <p className="font-data text-2xl font-medium tracking-widest text-ink">{code}</p>
            <p className="mt-1 text-xs text-ink-muted">Berlaku {expiresIn} menit.</p>
          </div>
        )}

        {error && <p className="mt-3 text-sm text-ledger-red">{error}</p>}
      </div>
    </div>
  )
}
