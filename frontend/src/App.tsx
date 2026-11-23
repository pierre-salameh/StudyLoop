import { Link, Route, Routes } from 'react-router-dom'
import { useAuth } from './auth/auth-context'
import { GuestRoute, ProtectedRoute } from './auth/ProtectedRoute'
import Dashboard from './pages/Dashboard'
import Login from './pages/Login'
import Register from './pages/Register'

function Home() {
  const { user, loading } = useAuth()

  if (loading) return <p className="p-8">جارٍ التحميل...</p>

  return (
    <div className="min-h-screen flex flex-col items-center justify-center gap-4 bg-slate-50">
      <h1 className="text-3xl font-bold">StudyLoop</h1>
      {user ? (
        <Link
          to="/dashboard"
          className="rounded-lg bg-slate-800 px-4 py-2 text-white"
        >
          الذهاب إلى لوحة التحكم
        </Link>
      ) : (
        <div className="flex gap-3">
          <Link
            to="/login"
            className="rounded-lg bg-slate-800 px-4 py-2 text-white"
          >
            تسجيل الدخول
          </Link>
          <Link
            to="/register"
            className="rounded-lg border border-slate-800 px-4 py-2"
          >
            إنشاء حساب
          </Link>
        </div>
      )}
    </div>
  )
}

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Home />} />

      <Route element={<GuestRoute />}>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
      </Route>

      <Route element={<ProtectedRoute />}>
        <Route path="/dashboard" element={<Dashboard />} />
      </Route>
    </Routes>
  )
}