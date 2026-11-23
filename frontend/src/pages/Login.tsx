import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/auth-context'
import { ApiError } from '../lib/api'

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [submitting, setSubmitting] = useState(false)

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setErrors({})
    setSubmitting(true)
    try {
      await login({ email, password })
      navigate('/dashboard')
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        setErrors(err.errors)
      } else {
        setErrors({ general: ['تعذّر الاتصال بالخادم، حاول مرة أخرى.'] })
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 p-4">
      <form
        onSubmit={onSubmit}
        className="w-full max-w-md space-y-4 rounded-2xl bg-white p-8 shadow"
      >
        <h1 className="text-2xl font-bold">تسجيل الدخول</h1>

        {errors.general && (
          <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
            {errors.general[0]}
          </p>
        )}

        <div>
          <label className="mb-1 block text-sm font-medium">
            البريد الإلكتروني
          </label>
          <input
            type="email"
            dir="ltr"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            className="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-800"
          />
          {errors.email && (
            <p className="mt-1 text-sm text-red-600">{errors.email[0]}</p>
          )}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium">كلمة السر</label>
          <input
            type="password"
            dir="ltr"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            className="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-800"
          />
          {errors.password && (
            <p className="mt-1 text-sm text-red-600">{errors.password[0]}</p>
          )}
        </div>

        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-lg bg-slate-800 py-2 text-white disabled:opacity-60"
        >
          {submitting ? 'جارٍ الدخول...' : 'دخول'}
        </button>

        <p className="text-center text-sm text-slate-600">
          ليس لديك حساب؟{' '}
          <Link to="/register" className="font-medium text-slate-900 underline">
            إنشاء حساب
          </Link>
        </p>
      </form>
    </div>
  )
}