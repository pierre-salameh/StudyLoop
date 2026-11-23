import { useEffect, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import type { Course, Lecture, LectureSummary } from '../lib/types'

const statusLabels: Record<string, string> = {
  pending: 'قيد المعالجة',
  ready: 'جاهزة',
}

export default function CoursePage() {
  const { courseId } = useParams()
  const [course, setCourse] = useState<Course | null>(null)
  const [lectures, setLectures] = useState<LectureSummary[]>([])
  const [loading, setLoading] = useState(true)
  const [notFound, setNotFound] = useState(false)
  const [title, setTitle] = useState('')
  const [content, setContent] = useState('')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    Promise.all([
      api<Course>(`/courses/${courseId}`),
      api<LectureSummary[]>(`/courses/${courseId}/lectures`),
    ])
      .then(([c, l]) => {
        setCourse(c)
        setLectures(l)
      })
      .catch((err) => {
        if (err instanceof ApiError && err.status === 404) setNotFound(true)
        else setError('تعذّر تحميل البيانات.')
      })
      .finally(() => setLoading(false))
  }, [courseId])

  const onAdd = async (e: FormEvent) => {
    e.preventDefault()
    setError('')
    setSaving(true)
    try {
      const lecture = await api<Lecture>(`/courses/${courseId}/lectures`, {
        method: 'POST',
        body: JSON.stringify({ title, content }),
      })
      setLectures([lecture, ...lectures])
      setTitle('')
      setContent('')
    } catch (err) {
      setError(
        err instanceof ApiError && err.status === 422
          ? Object.values(err.errors)[0][0]
          : 'تعذّر إضافة المحاضرة.',
      )
    } finally {
      setSaving(false)
    }
  }

  const onDelete = async (id: number) => {
    if (!confirm('هل تريد حذف هذه المحاضرة؟')) return
    setError('')
    try {
      await api(`/lectures/${id}`, { method: 'DELETE' })
      setLectures(lectures.filter((l) => l.id !== id))
    } catch {
      setError('تعذّر حذف المحاضرة.')
    }
  }

  if (loading) return <p className="p-8 text-slate-500">جارٍ التحميل...</p>

  if (notFound) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-3">
        <p className="text-lg">المقرر غير موجود.</p>
        <Link to="/dashboard" className="underline">
          العودة إلى المقررات
        </Link>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="bg-white px-6 py-4 shadow-sm">
        <Link to="/dashboard" className="text-sm text-slate-600 underline">
          العودة إلى المقررات
        </Link>
      </header>

      <main className="mx-auto max-w-4xl space-y-6 p-6">
        <div>
          <h1 className="text-2xl font-bold">{course?.title}</h1>
          {course?.description && (
            <p className="mt-1 text-slate-600">{course.description}</p>
          )}
        </div>

        {error && (
          <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
            {error}
          </p>
        )}

        <form
          onSubmit={onAdd}
          className="space-y-3 rounded-2xl bg-white p-5 shadow"
        >
          <h2 className="font-semibold">إضافة محاضرة</h2>
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="عنوان المحاضرة"
            required
            className="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-800"
          />
          <textarea
            value={content}
            onChange={(e) => setContent(e.target.value)}
            placeholder="الصق نص المحاضرة هنا (20 حرفاً على الأقل)"
            rows={8}
            required
            dir="auto"
            className="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-800"
          />
          <button
            type="submit"
            disabled={saving}
            className="rounded-lg bg-slate-800 px-4 py-2 text-white disabled:opacity-60"
          >
            {saving ? 'جارٍ الحفظ...' : 'حفظ المحاضرة'}
          </button>
        </form>

        {lectures.length === 0 ? (
          <div className="rounded-2xl border-2 border-dashed border-slate-300 p-12 text-center text-slate-500">
            لا توجد محاضرات في هذا المقرر بعد.
          </div>
        ) : (
          <ul className="space-y-3">
            {lectures.map((lecture) => (
              <li
                key={lecture.id}
                className="flex items-center justify-between rounded-2xl bg-white p-4 shadow"
              >
                <div>
                  <p className="font-semibold">{lecture.title}</p>
                  <p className="mt-1 text-xs text-slate-500">
                    {statusLabels[lecture.status] ?? lecture.status} ·{' '}
                    {new Date(lecture.created_at).toLocaleDateString('ar')}
                  </p>
                </div>
                <button
                  onClick={() => onDelete(lecture.id)}
                  className="text-sm text-red-600 hover:underline"
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