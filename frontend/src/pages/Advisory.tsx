import { useRef, useState, type FormEvent } from 'react'
import { AdvisoryMessageText } from '../components/AdvisoryMessageText'
import { api } from '../lib/api'

interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
}

export default function Advisory() {
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [question, setQuestion] = useState('')
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const bottomRef = useRef<HTMLDivElement>(null)

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const trimmed = question.trim()
    if (!trimmed || sending) return

    setMessages((prev) => [...prev, { role: 'user', content: trimmed }])
    setQuestion('')
    setSending(true)
    setError(null)

    try {
      const res = await api.post('/advisory/ask', { question: trimmed })
      setMessages((prev) => [...prev, { role: 'assistant', content: res.data.answer }])
      setTimeout(() => bottomRef.current?.scrollIntoView({ behavior: 'smooth' }), 50)
    } catch {
      setError('Gagal mendapat jawaban dari AI. Coba tanya lagi sebentar lagi.')
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="flex h-[calc(100vh-8rem)] flex-col">
      <h1 className="font-display text-2xl font-semibold text-ink">Advisory</h1>
      <p className="mt-1 text-sm text-ink-muted">
        Tanya apa saja soal kondisi keuanganmu — jawabannya berbasis data transaksi nyata.
      </p>

      <div className="mt-4 flex-1 space-y-3 overflow-y-auto rounded-md border border-border bg-paper-elevated p-4">
        {messages.length === 0 && (
          <p className="text-sm text-ink-muted">
            Belum ada percakapan. Coba tanya, mis. "aku mau upgrade PC harga 8 juta, gimana kondisiku?"
          </p>
        )}

        {messages.map((message, index) => (
          <div key={index} className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}>
            <div
              className={`max-w-[75%] rounded-md px-4 py-2 text-sm ${
                message.role === 'user' ? 'bg-ledger-green text-paper' : 'bg-paper text-ink'
              }`}
            >
              <AdvisoryMessageText text={message.content} />
            </div>
          </div>
        ))}

        {sending && <p className="text-sm text-ink-muted">AI sedang mikir…</p>}
        <div ref={bottomRef} />
      </div>

      {error && <p className="mt-2 text-sm text-ledger-red">{error}</p>}

      <form onSubmit={handleSubmit} className="mt-4 flex gap-3">
        <input
          type="text"
          value={question}
          onChange={(e) => setQuestion(e.target.value)}
          placeholder="Tanya soal keuanganmu…"
          className="flex-1 rounded-md border border-border bg-paper-elevated px-3 py-2 text-sm text-ink"
        />
        <button
          type="submit"
          disabled={sending || !question.trim()}
          className="rounded-md bg-ledger-green px-4 py-2 text-sm text-paper disabled:opacity-60"
        >
          Kirim
        </button>
      </form>
    </div>
  )
}
