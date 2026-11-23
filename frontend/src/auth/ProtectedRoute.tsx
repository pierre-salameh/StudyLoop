import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from './auth-context'

export function ProtectedRoute() {
  const { user, loading } = useAuth()

  if (loading) return <p className="p-8">جارٍ التحميل...</p>

  return user ? <Outlet /> : <Navigate to="/login" replace />
}

export function GuestRoute() {
  const { user, loading } = useAuth()

  if (loading) return <p className="p-8">جارٍ التحميل...</p>

  return user ? <Navigate to="/dashboard" replace /> : <Outlet />
}