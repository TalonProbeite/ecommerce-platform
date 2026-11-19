import { Loader2 } from 'lucide-react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { SessionFeedback } from './session-feedback'
import { can, type AccessFeature } from '@/lib/access'
import { useAuthStore } from '@/store/auth-store'
import type { Role } from '@/types'

interface ProtectedRouteProps {
  roles?: Role[]
  feature?: AccessFeature
}

export function ProtectedRoute({ roles, feature }: ProtectedRouteProps) {
  const location = useLocation()
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

  if (!user) {
    return <Navigate to={ROUTES.login} replace state={{ from: location.pathname + location.search }} />
  }

  const roleAllowed = feature ? can(user.role, feature) : !roles || (user.role !== undefined && roles.includes(user.role))

  if (user.isBanned || !roleAllowed) {
    return <div className="mx-auto max-w-3xl px-4 py-16" role="alert"><h1 className="text-3xl font-bold">Forbidden</h1><p className="mt-3 text-muted-foreground">Your account does not have permission to access this page.</p></div>
  }

  return <Outlet />
}
