import { createContext, useContext, useState, type ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { api, clearAuthToken, getAuthToken, setAuthToken } from '../lib/api'

interface AuthUser {
  id: number
  name: string
  email: string
}

interface AuthContextValue {
  user: AuthUser | null
  isAuthenticated: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [isAuthenticated, setIsAuthenticated] = useState(() => Boolean(getAuthToken()))

  async function login(email: string, password: string) {
    const response = await api.post('/login', { email, password })
    setAuthToken(response.data.token)
    setUser(response.data.user)
    setIsAuthenticated(true)
  }

  async function logout() {
    try {
      await api.post('/logout')
    } finally {
      clearAuthToken()
      setUser(null)
      setIsAuthenticated(false)
    }
  }

  return (
    <AuthContext.Provider value={{ user, isAuthenticated, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth harus dipakai di dalam AuthProvider')
  return ctx
}

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated } = useAuth()
  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }
  return <>{children}</>
}
