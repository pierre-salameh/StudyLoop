import { useAuth } from '../auth/auth-context'

export default function Dashboard() {
  const { user, logout } = useAuth()

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

      <main className="mx-auto max-w-4xl p-6">
        <h1 className="mb-4 text-2xl font-bold">محاضراتي</h1>
        <div className="rounded-2xl border-2 border-dashed border-slate-300 p-12 text-center text-slate-500">
          لا توجد محاضرات بعد. سنضيف رفع المحاضرات في المرحلة القادمة.
        </div>
      </main>
    </div>
  )
}