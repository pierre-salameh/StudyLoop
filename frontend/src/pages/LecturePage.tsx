import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, ApiError } from '../lib/api'
import type { LectureDetail } from '../lib/types'

export default function LecturePage() {
  const { lectureId } = useParams()
  const [lecture, setLecture] = useState<LectureDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [notFound, setNotFound] = useState(false)
  const [error, setError] = useState('')
  const [reprocessing, setReprocessing] = useState(false)

  const load = useCallback(async () => {
    try {
      const data = await api<LectureDetail>(`/lectures/${lectureId}`)
      setLecture(data)
      setError('')
    } catch (err) {
      if (err instanceof ApiError && err.status === 404) setNotFound(true)
      else setError('تعذّر تحميل المحاضرة.')
    } finally {
      setLoading(false)
    }
  }, [lectureId])

  useEffect(() => {
    load()
  }, [load])

  // أثناء الانتظار أو المعالجة نسأل الخادم كل 5 ثوانٍ
  const status = lecture?.status
  useEffect(() => {
    if (status !== 'pending' && status !== 'processing') return

    const timer = setInterval(load, 5000)
    return () => clearInterval(timer)
  }, [status, load])

  const onReprocess = async () => {
    setReprocessing(true)
    setError('')
    try {
      await api(`/lectures/${lectureId}/reprocess`, { method: 'POST' })
      await load()
    } catch (err) {
      setError(
        err instanceof ApiError && err.status === 409
          ? 'المحاضرة قيد المعالجة بالفعل.'
          : 'تعذّرت إعادة المعالجة.',
      )
    } finally {
      setReprocessing(false)
    }
  }

  if (loading) return <p className="p-8 text-slate-500">جارٍ التحميل...</p>

  if (notFound || !lecture) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-3">
        <p className="text-lg">المحاضرة غير موجودة.</p>
        <Link to="/dashboard" className="underline">
          العودة إلى المقررات
        </Link>
      </div>
    )
  }

  const analyzing = lecture.status === 'pending' || lecture.status === 'processing'

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="bg-white px-6 py-4 shadow-sm">
        <Link
          to={`/courses/${lecture.course_id}`}
          className="text-sm text-slate-600 underline"
        >
          العودة إلى المقرر
        </Link>
      </header>

      <main className="mx-auto max-w-4xl space-y-6 p-6">
        <h1 className="text-2xl font-bold">{lecture.title}</h1>

        {error && (
          <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
            {error}
          </p>
        )}

        {analyzing && (
          <div className="flex items-center gap-4 rounded-2xl bg-white p-6 shadow">
            <div className="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-slate-800" />
            <div>
              <p className="font-semibold">
                {lecture.status === 'pending'
                  ? 'المحاضرة في الانتظار...'
                  : 'جارٍ تحليل المحاضرة...'}
              </p>
              <p className="mt-1 text-sm text-slate-600">
                قد يستغرق عدة دقائق. يمكنك ترك هذه الصفحة وسيستمر العمل في الخلفية.
              </p>
            </div>
          </div>
        )}

        {lecture.status === 'failed' && (
          <div className="space-y-3 rounded-2xl bg-white p-6 shadow">
            <p className="font-semibold text-red-700">
              تعذّر استخراج مفاهيم من هذه المحاضرة.
            </p>
            <button
              onClick={onReprocess}
              disabled={reprocessing}
              className="rounded-lg bg-slate-800 px-4 py-2 text-white disabled:opacity-60"
            >
              {reprocessing ? 'جارٍ الإرسال...' : 'إعادة المحاولة'}
            </button>
          </div>
        )}

        {lecture.status === 'ready' && (
          <>
            <p className="text-sm text-slate-600">
              استُخرجت هذه المفاهيم بالذكاء الاصطناعي. مع كل مفهوم اقتباس حرفي
              من محاضرتك لتتحقق منه بنفسك.
            </p>

            <ol className="space-y-4">
              {lecture.concepts.map((concept) => (
                <li
                  key={concept.id}
                  className="rounded-2xl bg-white p-5 shadow"
                >
                  <h2 className="text-lg font-semibold">
                    {concept.position}. {concept.title}
                  </h2>
                  <p className="mt-2 text-slate-700">{concept.summary}</p>

                  <details className="mt-3">
                    <summary className="cursor-pointer text-sm text-slate-500">
                      من نص المحاضرة
                    </summary>
                    <blockquote
                      dir="auto"
                      className="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-600"
                    >
                      {concept.source_quote}
                    </blockquote>
                  </details>
                </li>
              ))}
            </ol>
          </>
        )}
      </main>
    </div>
  )
}