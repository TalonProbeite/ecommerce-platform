import { CheckCircle2, Loader2 } from 'lucide-react'
import { REGEXP_ONLY_DIGITS_AND_CHARS } from 'input-otp'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { isAxiosError } from 'axios'
import { Link, useLocation, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import { DEMO_VERIFICATION_CODE } from '@/api/mock-adapter'
import { getErrorCode, getErrorMessage, isMockApi } from '@/api/client'
import { ROUTES } from '@/app/routes'
import { AuthShell } from '@/components/auth/auth-shell'
import { FormField } from '@/components/auth/form-field'
import { LinkButton } from '@/components/common/link-button'
import { Button } from '@/components/ui/button'
import { InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot } from '@/components/ui/input-otp'
import { OTP_LENGTH, validateEmail } from '@/lib/validation'
import { useAuthStore } from '@/store/auth-store'

const RESEND_COOLDOWN_SECONDS = 30

type VerificationError = { kind: 'invalid' | 'expired' | 'invalid-or-expired' | 'other'; message: string }

export function VerifyEmailPage() {
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const verifyEmail = useAuthStore((state) => state.verifyEmail)
  const resendVerification = useAuthStore((state) => state.resendVerification)
  const userEmail = useAuthStore((state) => state.user?.email)

  const knownEmail = userEmail ?? (isMockApi ? (location.state as { email?: string } | null)?.email ?? searchParams.get('email') ?? '' : '')
  const [email, setEmail] = useState(knownEmail)
  const [emailTouched, setEmailTouched] = useState(false)
  const [code, setCode] = useState('')
  const [error, setError] = useState<VerificationError | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [resending, setResending] = useState(false)
  const [cooldown, setCooldown] = useState(0)
  const [verified, setVerified] = useState(false)
  const requestPending = useRef(false)

  useEffect(() => {
    if (cooldown <= 0) return
    const timer = setTimeout(() => setCooldown((seconds) => seconds - 1), 1000)
    return () => clearTimeout(timer)
  }, [cooldown])

  const emailError = isMockApi ? validateEmail(email) : undefined
  const codeComplete = code.length === OTP_LENGTH

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (requestPending.current) return
    setEmailTouched(true)
    if (emailError || !codeComplete) return
    requestPending.current = true
    setSubmitting(true)
    setError(null)
    try {
      await verifyEmail({ email: email.trim(), code })
      setVerified(true)
    } catch (caught) {
      const errorCode = getErrorCode(caught)
      const message = getErrorMessage(caught)
      if (errorCode === 'CODE_EXPIRED') setError({ kind: 'expired', message })
      else if (errorCode === 'INVALID_CODE') setError({ kind: 'invalid', message })
      else if (!isMockApi && isAxiosError(caught) && caught.response?.status === 422) setError({ kind: 'invalid-or-expired', message })
      else setError({ kind: 'other', message })
    } finally {
      requestPending.current = false
      setSubmitting(false)
    }
  }

  async function handleResend() {
    if (requestPending.current || cooldown > 0) return
    setEmailTouched(true)
    if (emailError) return
    requestPending.current = true
    setResending(true)
    setError(null)
    try {
      await resendVerification(email.trim())
      setCode('')
      setCooldown(RESEND_COOLDOWN_SECONDS)
      toast.success('A new verification code is on its way.')
    } catch (caught) {
      setError({ kind: 'other', message: getErrorMessage(caught) })
    } finally {
      requestPending.current = false
      setResending(false)
    }
  }

  if (verified) {
    return (
      <AuthShell
        title="Email verified"
        description="Your account is ready to use."
        footer={
          <Link to={ROUTES.login} className="font-semibold text-primary hover:underline">
            Back to login
          </Link>
        }
      >
        <div className="flex flex-col items-center gap-4 py-2 text-center" role="status">
          <span className="grid size-14 place-items-center rounded-full bg-secondary text-primary">
            <CheckCircle2 className="size-7" aria-hidden="true" />
          </span>
          <p className="text-sm text-muted-foreground">
            {email.trim() ? <>Thanks for confirming <span className="font-semibold text-foreground">{email.trim()}</span>.</> : 'Thanks for confirming your email.'} You can continue with your account.
          </p>
          <LinkButton to={ROUTES.login} size="lg" className="h-11 w-full rounded-full">
            Continue to login
          </LinkButton>
        </div>
      </AuthShell>
    )
  }

  return (
    <AuthShell
      title="Verify your email"
      description={
        knownEmail
          ? `Enter the ${OTP_LENGTH}-character code we sent to ${knownEmail}.`
          : isMockApi ? `Enter your email and the ${OTP_LENGTH}-character code we sent you.` : `Enter the ${OTP_LENGTH}-character code we sent to your account email.`
      }
      footer={
        <>
          Wrong account?{' '}
          <Link to={ROUTES.login} className="font-semibold text-primary hover:underline">
            Back to login
          </Link>
        </>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-5" noValidate>
        {knownEmail || !isMockApi ? null : (
          <FormField
            id="email"
            label="Email"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            onBlur={() => setEmailTouched(true)}
            error={emailTouched ? emailError : undefined}
          />
        )}

        <div className="space-y-2">
          <label htmlFor="otp" className="text-sm font-medium">
            Verification code
          </label>
          <InputOTP
            id="otp"
            maxLength={OTP_LENGTH}
            pattern={isMockApi ? REGEXP_ONLY_DIGITS_AND_CHARS : undefined}
            inputMode="text"
            value={code}
            onChange={(value) => {
              setCode(isMockApi ? value.toUpperCase() : value)
              setError(null)
            }}
            disabled={submitting || resending}
            aria-invalid={Boolean(error)}
            aria-describedby={error ? 'otp-error' : undefined}
            containerClassName="w-full min-w-0 justify-center gap-1"
          >
            <InputOTPGroup className="min-w-0 flex-1">
              {[0, 1, 2, 3, 4].map((index) => (
                <InputOTPSlot key={index} index={index} className="h-9 min-w-0 flex-1 sm:h-10" />
              ))}
            </InputOTPGroup>
            <InputOTPSeparator />
            <InputOTPGroup className="min-w-0 flex-1">
              {[5, 6, 7, 8, 9].map((index) => (
                <InputOTPSlot key={index} index={index} className="h-9 min-w-0 flex-1 sm:h-10" />
              ))}
            </InputOTPGroup>
          </InputOTP>
        </div>

        {error ? (
          <div id="otp-error" className="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert">
            <p className="font-semibold">
              {error.kind === 'expired' ? 'Code expired' : error.kind === 'invalid' ? 'Invalid code' : error.kind === 'invalid-or-expired' ? 'Invalid or expired code' : 'Verification failed'}
            </p>
            <p className="mt-0.5 font-medium">{error.message}</p>
          </div>
        ) : null}

        <Button type="submit" size="lg" className="h-11 w-full rounded-full" disabled={submitting || resending || !codeComplete}>
          {submitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
          {submitting ? 'Verifying...' : 'Verify email'}
        </Button>

        <div className="flex items-center justify-center gap-1 text-sm text-muted-foreground">
          <span>Didn&apos;t get a code?</span>
          <Button type="button" variant="link" className="h-auto p-0 font-semibold" onClick={() => void handleResend()} disabled={submitting || resending || cooldown > 0}>
            {resending ? 'Sending...' : cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend code'}
          </Button>
        </div>
      </form>

      {isMockApi ? (
        <p className="mt-6 rounded-2xl bg-secondary p-4 text-xs text-muted-foreground">
          Demo mode: use <span className="font-mono font-bold text-foreground">{DEMO_VERIFICATION_CODE}</span> to verify, or{' '}
          <span className="font-mono font-bold text-foreground">0000000000</span> to see the expired state.
        </p>
      ) : null}
    </AuthShell>
  )
}
