import type { ReactNode } from 'react'
import { NavLink } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'

const NAV_ITEMS = [
  { to: '/', label: 'Dashboard' },
  { to: '/transactions', label: 'Transaksi' },
  { to: '/advisory', label: 'Advisory' },
  { to: '/settings', label: 'Pengaturan' },
]

export function Layout({ children }: { children: ReactNode }) {
  const { logout } = useAuth()

  return (
    <div className="min-h-screen bg-paper font-body text-ink">
      <header className="border-b border-border bg-paper-elevated">
        <div className="mx-auto flex max-w-[1200px] items-center justify-between px-6 py-4">
          <span className="font-display text-xl font-semibold text-ink">FinTrack AI</span>
          <nav className="flex items-center gap-6">
            {NAV_ITEMS.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                className={({ isActive }) =>
                  `text-sm ${isActive ? 'font-medium text-ledger-green' : 'text-ink-muted hover:text-ink'}`
                }
              >
                {item.label}
              </NavLink>
            ))}
            <button type="button" onClick={() => logout()} className="text-sm text-ink-muted hover:text-ledger-red">
              Keluar
            </button>
          </nav>
        </div>
      </header>
      <main className="mx-auto max-w-[1200px] px-6 py-8 md:px-12">{children}</main>
    </div>
  )
}
