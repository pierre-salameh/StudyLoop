import { useEffect, useState, type FormEvent } from 'react'
import { useAuth } from '../auth/auth-context'
import { api, ApiError } from '../lib/api'
import type { Course } from '../lib/types'

export default function Dashboard() {
  const { user, logout } = useAuth()
  const [courses, setCourses] = useState<Course[]>([])
  const [loading, setLoading] = useState(true)
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    api<Course[]>('/courses')
      .then(setCourses)
      .catch(() => setError('تعذّر تحميل المقررات.'))
      .finally(() => setLoading(false))
  }, [])

  const onAdd = async (e: FormEvent) => {
    e.preventDefault()
    setError('')
    setSaving(true)
    try {
      const course = await api<Course>('/courses', {
        method: 'POST',
        body: JSON.stringify({ title, description: description || null }),
      })
      setCourses([course, ...courses])
      setTitle('')
      setDescription('')
    } catch (err) {
      setError(
        err instanceof ApiError && err.status === 422
          ? Object.values(err.errors)[0][0]
          : 'تعذّر إضافة المقرر.',
      )
    } finally {
      setSaving(false)
    }
  }

  const onDelete = async (id: number) => {
    if (!confirm('هل تريد حذف هذا المقرر؟')) return
    setError('')
    try {
      await api(`/courses/${id}`, { method: 'DELETE' })
      setCourses(courses.filter((c) => c.id !== id))
    } catch {
      setError('تعذّر حذف المقرر.')
    }
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="flex items-center justify-between bg-white px-6 py-4 shadow-sm">
        <span className="text-xl font-bold">StudyLoop</span>
        <div className="flex items-center gap-4">
          <span className="text-sm text-slate-600">{user?.name}</span>
          <button
            onClick={logout}
            className="rounded-lg bg-slate-800 px-3 py-1.5 text-sm text-white"
          >
            تسجيل الخروج
          </button>
        </div>
      </header>

      <main className="mx-auto max-w-4xl space-y-6 p-6">
        <h1 className="text-2xl font-bold">مقرراتي</h1>

        {error && (
          <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
            {error}
          </p>
        )}

        <form
          onSubmit={onAdd}
          className="space-y-3 rounded-2xl bg-white p-5 shadow"
        >
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="اسم المقرر"
            required
            className="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-800"
          />
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="وصف اختياري"
            rows={2}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-800"
          />
          <button
            type="submit"
            disabled={saving}
            className="rounded-lg bg-slate-800 px-4 py-2 text-white disabled:opacity-60"
          >
            {saving ? 'جارٍ الإضافة...' : 'إضافة مقرر'}
          </button>
        </form>

        {loading ? (
          <p className="text-slate-500">جارٍ التحميل...</p>
        ) : courses.length === 0 ? (
          <div className="rounded-2xl border-2 border-dashed border-slate-300 p-12 text-center text-slate-500">
            لا توجد مقررات بعد. أضف أول مقرر من النموذج أعلاه.
          </div>
        ) : (
          <ul className="grid gap-4 sm:grid-cols-2">
            {courses.map((course) => (
              <li
                key={course.id}
                className="flex flex-col justify-between rounded-2xl bg-white p-5 shadow"
              >
                <div>
                  <h2 className="text-lg font-semibold">{course.title}</h2>
                  {course.description && (
                    <p className="mt-1 text-sm text-slate-600">
                      {course.description}
                    </p>
                  )}
                </div>
                <button
                  onClick={() => onDelete(course.id)}
                  className="mt-4 self-start text-sm text-red-600 hover:underline"
                >
                  حذف
                </button>
              </li>
            ))}
          </ul>
        )}
      </main>
    </div>
  )
}