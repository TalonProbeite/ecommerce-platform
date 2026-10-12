import { Loader2 } from 'lucide-react'
import { Navigate } from 'react-router-dom'
import { roleHome } from '@/lib/roles'
import { useAuthStore } from '@/store/auth-store'
import { HomePage } from './home-page'
import { SessionFeedback } from '@/components/auth/session-feedback'

export function RootPage() {
  const user = useAuthStore((state) => state.user)
  const status = useAuthStore((state) => state.status)

  if (status === 'idle' || status === 'loading') {
    return (
      <div className="grid min-h-[50vh] place-items-center" role="status">
        <Loader2 className="size-6 animate-spin text-primary" aria-hidden="true" />
        <span className="sr-only">Loading</span>
      </div>
    )
  }

  if (status === 'error') return <SessionFeedback />
  if (user) return <Navigate to={roleHome(user.role)} replace />

  return <HomePage />
}
