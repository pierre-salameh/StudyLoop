import { createContext, useContext } from 'react'

export interface User {
  id: number
  name: string
  email: string
}

export interface AuthContextValue {
  user: User | null
  loading: boolean
  register: (data: {
    name: string
    email: string
    password: string
    password_confirmation: string
  }) => Promise<void>
  login: (data: { email: string; password: string }) => Promise<void>
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth must be used inside AuthProvider')
  }
  return ctx
}