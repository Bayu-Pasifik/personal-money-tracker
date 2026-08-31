import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await login(email, password)
      navigate('/', { replace: true })
    } catch {
      setError('Email atau kata sandi salah. Coba lagi.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-paper font-body">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-sm rounded-md border border-border bg-paper-elevated p-8"
      >
        <h1 className="font-display text-2xl font-semibold text-ink">FinTrack AI</h1>
        <p className="mt-1 text-sm text-ink-muted">Masuk untuk melihat catatan keuanganmu.</p>

        <label className="mt-6 block text-sm text-ink-muted">
          Email
          <input
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="mt-1 w-full rounded-md border border-border bg-paper px-3 py-2 text-sm text-ink"
          />
        </label>

        <label className="mt-4 block text-sm text-ink-muted">
          Kata Sandi
          <input
            type="password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="mt-1 w-full rounded-md border border-border bg-paper px-3 py-2 text-sm text-ink"
          />
        </label>

        {error && <p className="mt-3 text-sm text-ledger-red">{error}</p>}

        <button
          type="submit"
          disabled={submitting}
          className="mt-6 w-full rounded-md bg-ledger-green px-4 py-2 text-sm text-paper disabled:opacity-60"
        >
          {submitting ? 'Masuk…' : 'Masuk'}
        </button>
      </form>
    </div>
  )
}
