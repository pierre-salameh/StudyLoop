import { Fragment } from 'react'

// يعرض **غامق** و`شيفرة` كما يكتبها النموذج بدل أن تظهر الرموز نفسها
// لا نستخدم innerHTML أبداً، فتبقى كل النصوص مُهرَّبة آلياً من React
export default function RichText({ text }: { text: string }) {
  const parts = text.split(/(\*\*[^*]+\*\*|`[^`]+`)/g)

  return (
    <>
      {parts.map((part, i) => {
        if (part.length > 4 && part.startsWith('**') && part.endsWith('**')) {
          return <strong key={i}>{part.slice(2, -2)}</strong>
        }

        if (part.length > 2 && part.startsWith('`') && part.endsWith('`')) {
          return (
            <code
              key={i}
              dir="ltr"
              className="rounded bg-slate-100 px-1 text-[0.9em]"
            >
              {part.slice(1, -1)}
            </code>
          )
        }

        return <Fragment key={i}>{part}</Fragment>
      })}
    </>
  )
}