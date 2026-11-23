import { useState, type ChangeEvent, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/auth-context'
import { ApiError } from '../lib/api'

const fields = [
  { name: 'name', label: 'الاسم', type: 'text' },
  { name: 'email', label: 'البريد الإلكتروني', type: 'email' },
  { name: 'password', label: 'كلمة السر', type: 'password' },
  { name: 'password_confirmation', label: 'تأكيد كلمة السر', type: 'password' },
] as const

export default function Register() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [submitting, setSubmitting] = useState(false)

  const update =
    (field: keyof typeof form) => (e: ChangeEvent<HTMLInputElement>) =>
      setForm({ ...form, [field]: e.target.value })

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setErrors({})
    setSubmitting(true)
    try {
      await register(form)
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
        <h1 className="text-2xl font-bold">إنشاء حساب</h1>

        {errors.general && (
          <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
            {errors.general[0]}
          </p>
        )}

        {fields.map((f) => (
          <div key={f.name}>
            <label className="mb-1 block text-sm font-medium">{f.label}</label>
            <input
              type={f.type}
              dir={f.type === 'text' ? undefined : 'ltr'}
              value={form[f.name]}
              onChange={update(f.name)}
              required
              className="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-800"
            />
            {errors[f.name] && (
              <p className="mt-1 text-sm text-red-600">{errors[f.name][0]}</p>
            )}
          </div>
        ))}

        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-lg bg-slate-800 py-2 text-white disabled:opacity-60"
        >
          {submitting ? 'جارٍ الإنشاء...' : 'إنشاء الحساب'}
        </button>

        <p className="text-center text-sm text-slate-600">
          لديك حساب؟{' '}
          <Link to="/login" className="font-medium text-slate-900 underline">
            تسجيل الدخول
          </Link>
        </p>
      </form>
    </div>
  )
}