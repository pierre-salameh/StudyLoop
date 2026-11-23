import { useEffect, useState } from 'react'

export default function App() {
  const [status, setStatus] = useState('جارٍ الاتصال...')

  useEffect(() => {
    fetch('/api/health')
      .then((res) => res.json())
      .then((data) => setStatus(data.status))
      .catch(() => setStatus('فشل الاتصال'))
  }, [])

  return (
    <div
      dir="rtl"
      className="min-h-screen flex items-center justify-center bg-slate-50"
    >
      <h1 className="text-2xl font-bold">حالة الخادم: {status}</h1>
    </div>
  )
}