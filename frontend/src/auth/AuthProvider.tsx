import { useEffect, useState, type ReactNode } from 'react'
import { api, clearToken, getToken, setToken } from '../lib/api'
import {
  AuthContext,
  type AuthContextValue,
  type User,
} from './auth-context'

interface AuthResponse {
  user: User
  token: string
}

export default function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  // نبدأ بحالة "تحميل" فقط إن كان هناك توكن محفوظ نحتاج التحقق منه
  const [loading, setLoading] = useState(() => getToken() !== null)

  useEffect(() => {
    if (!getToken()) return

    api<User>('/user')
      .then(setUser)
      .catch(() => clearToken())
      .finally(() => setLoading(false))
  }, [])

  const register: AuthContextValue['register'] = async (data) => {
    const res = await api<AuthResponse>('/register', {
      method: 'POST',
      body: JSON.stringify(data),
    })
    setToken(res.token)
    setUser(res.user)
  }

  const login: AuthContextValue['login'] = async (data) => {
    const res = await api<AuthResponse>('/login', {
      method: 'POST',
      body: JSON.stringify(data),
    })
    setToken(res.token)
    setUser(res.user)
  }

  const logout: AuthContextValue['logout'] = async () => {
    try {
      await api('/logout', { method: 'POST' })
    } catch {
      // قد يكون التوكن منتهياً أصلاً، ونريد الخروج محلياً في كل الأحوال
    }
    clearToken()
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, loading, register, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}