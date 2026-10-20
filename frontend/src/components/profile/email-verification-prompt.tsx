import { useRef, useState } from 'react'
import { Mail, Loader2 } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { getErrorMessage } from '@/api/client'
import { ROUTES } from '@/app/routes'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/store/auth-store'

export function EmailVerificationPrompt({ email, disabled }: { email: string; disabled?: boolean }) {
  const navigate = useNavigate()
  const sendVerification = useAuthStore((state) => state.sendVerification)
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const pending = useRef(false)

  async function sendCode() {
    if (pending.current) return
    pending.current = true
    setSending(true)
    setError(null)
    try {
      await sendVerification(email)
      if (useAuthStore.getState().user?.email !== email) return
      navigate(ROUTES.verifyEmail, { state: { email, verificationSentAt: Date.now() } })
    } catch (caught) {
      setError(getErrorMessage(caught))
    } finally {
      pending.current = false
      setSending(false)
    }
  }

  return (
    <div className="mt-6 rounded-3xl border bg-card p-5">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex min-w-0 items-start gap-3">
          <Mail className="mt-0.5 size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
          <div className="min-w-0 break-words">
            <p className="font-semibold">Email not verified</p>
            <p className="text-sm text-muted-foreground">You can confirm {email} whenever you’re ready. We’ll send a code when you choose to verify.</p>
          </div>
        </div>
        <Button type="button" variant="outline" size="sm" className="shrink-0 rounded-full" disabled={sending || disabled} onClick={() => void sendCode()}>
          {sending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
          {sending ? 'Sending code...' : 'Verify email'}
        </Button>
      </div>
      {error ? <p className="mt-3 text-sm text-destructive" role="alert">{error}</p> : null}
    </div>
  )
}
