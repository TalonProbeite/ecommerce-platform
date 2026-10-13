import { Loader2 } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { getErrorMessage } from '@/api/client'
import { ROUTES } from '@/app/routes'
import { AuthShell } from '@/components/auth/auth-shell'
import { Button } from '@/components/ui/button'
import { roleHome } from '@/lib/roles'
import { useAuthStore } from '@/store/auth-store'

export function OAuthSuccessPage() {
  const navigate = useNavigate()
  const restore = useAuthStore(state => state.restoreOAuthSession)
  const pending = useRef<ReturnType<typeof restore> | null>(null)
  const [attempt, setAttempt] = useState(0)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let active = true
    // Reuse the request during React StrictMode's effect replay.
    pending.current ??= restore()
    void pending.current.then(user => {
      if (active) navigate(roleHome(user.role), { replace: true })
    }).catch(caught => {
      if (active) setError(getErrorMessage(caught, 'Could not load your account. Please try again.'))
    })
    return () => { active = false }
  }, [restore, navigate, attempt])

  return <AuthShell title="Signing you in" description="Your Google account is connected. We’re loading your Rentify account."
    footer={<Link to={ROUTES.login} className="font-semibold text-primary hover:underline">Back to login</Link>}>
    {error ? <div className="space-y-4">
      <p role="alert" className="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive">{error}</p>
      <Button className="w-full rounded-full" onClick={() => { pending.current = null; setError(null); setAttempt(value => value + 1) }}>Try again</Button>
    </div> : <p role="status" className="flex items-center gap-3 text-sm text-muted-foreground"><Loader2 aria-hidden="true" className="size-5 animate-spin" />Loading your account...</p>}
  </AuthShell>
}
