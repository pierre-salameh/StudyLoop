import { useState } from 'react'
import type { Concept } from '../lib/types'

const STYLES = [
  { key: 'simple', label: 'مبسّط' },
  { key: 'example', label: 'بمثال' },
  { key: 'analogy', label: 'بتشبيه' },
  { key: 'steps', label: 'خطوة بخطوة' },
]

interface Props {
  concept: Concept
  onRequest: (conceptId: number, style: string) => Promise<void>
}

export default function ConceptCard({ concept, onRequest }: Props) {
  const [selected, setSelected] = useState<string | null>(null)
  const [requesting, setRequesting] = useState(false)

  const find = (style: string) =>
    concept.explanations.find((e) => e.style === style)

  const active = selected ?? concept.explanations[0]?.style ?? null
  const current = active ? find(active) : undefined
  const nextStyle = STYLES.find((s) => !find(s.key))
  const waiting =
    current?.status === 'pending' || current?.status === 'processing'

  const request = async (style: string) => {
    setSelected(style)
    setRequesting(true)
    try {
      await onRequest(concept.id, style)
    } finally {
      setRequesting(false)
    }
  }

  const labelOf = (style: string) =>
    STYLES.find((s) => s.key === style)?.label ?? style

  return (
    <li className="rounded-2xl bg-white p-5 shadow">
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

      <div className="mt-4 border-t border-slate-100 pt-4">
        {concept.explanations.length === 0 ? (
          <button
            onClick={() => request('simple')}
            disabled={requesting}
            className="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white disabled:opacity-60"
          >
            {requesting ? 'جارٍ الإرسال...' : 'اشرح لي هذا المفهوم'}
          </button>
        ) : (
          <div className="space-y-3">
            <div className="flex flex-wrap gap-2">
              {concept.explanations.map((e) => (
                <button
                  key={e.style}
                  onClick={() => setSelected(e.style)}
                  className={`rounded-full px-3 py-1 text-sm ${
                    e.style === active
                      ? 'bg-slate-800 text-white'
                      : 'bg-slate-100 text-slate-700'
                  }`}
                >
                  {labelOf(e.style)}
                </button>
              ))}
            </div>

            {waiting && (
              <div className="flex items-center gap-3 text-sm text-slate-600">
                <div className="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-800" />
                جارٍ تحضير الشرح والتحقق منه، وقد يستغرق من دقيقتين إلى خمس دقائق.
              </div>
            )}

            {current?.status === 'failed' && (
              <div className="space-y-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                <p>
                  لم نستطع إنتاج شرح موثوق بهذا النمط. راجع الاقتباس أعلاه، أو جرّب
                  نمطاً آخر.
                </p>
                <button
                  onClick={() => request(current.style)}
                  disabled={requesting}
                  className="underline disabled:opacity-60"
                >
                  إعادة المحاولة
                </button>
              </div>
            )}

            {current?.status === 'ready' && (
              <div className="space-y-3">
                <p dir="auto" className="whitespace-pre-line text-slate-800">
                  {current.explanation}
                </p>

                {current.example && (
                  <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3">
                    <p className="mb-1 text-xs font-semibold text-slate-500">
                      ليس من المحاضرة: مثال من الذكاء الاصطناعي قد يحتوي أخطاء
                    </p>
                    <p dir="auto" className="whitespace-pre-line text-sm text-slate-700">
                      {current.example}
                    </p>
                  </div>
                )}

                <p className="text-xs text-slate-500">
                  شرح بالذكاء الاصطناعي من فقرات محاضرتك، وقد يحتوي أخطاء. قارنه
                  دائماً بالاقتباس الأصلي.
                </p>
              </div>
            )}

            {nextStyle && !waiting && !requesting && (
              <button
                onClick={() => request(nextStyle.key)}
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
              >
                لم أفهم، اشرح بطريقة أخرى ({nextStyle.label})
              </button>
            )}
          </div>
        )}
      </div>
    </li>
  )
}