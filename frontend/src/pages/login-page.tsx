import { Loader2 } from 'lucide-react'
import { useRef, useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { DEMO_ACCOUNTS } from '@/api/mock-adapter'
import { getErrorMessage, GOOGLE_OAUTH_URL, isMockApi } from '@/api/client'
import { ROUTES } from '@/app/routes'
import { AuthShell } from '@/components/auth/auth-shell'
import { FormField } from '@/components/auth/form-field'
import { GoogleButton, OrDivider } from '@/components/auth/google-button'
import { Button } from '@/components/ui/button'
import { useTouchedFields } from '@/hooks/use-touched-fields'
import { fullName, loginDestination } from '@/lib/roles'
import { hasErrors, validateEmail, type FieldErrors } from '@/lib/validation'
import { useAuthStore } from '@/store/auth-store'

interface LoginValues {
  email: string
  password: string
}

function validate({ email, password }: LoginValues): FieldErrors<LoginValues> {
  return {
    email: validateEmail(email),
    password: password ? undefined : 'Password is required.',
  }
}

export function LoginPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const user = useAuthStore((state) => state.user)
  const sessionExpired = useAuthStore((state) => state.sessionExpired)
  const login = useAuthStore((state) => state.login)
  const [values, setValues] = useState<LoginValues>({ email: '', password: '' })
  const [apiError, setApiError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const pending = useRef(false)
  const { touch, touchAll, isTouched } = useTouchedFields()

  const oauthError = new URLSearchParams(location.search).get('oauth_error')
  const oauthMessage = oauthError ? ({
    oauth_canceled: 'Google sign-in was canceled. You can try again.',
    oauth_invalid_state: 'Google sign-in expired or could not be verified. Please start again.',
    account_inactive: 'Your account is not active.',
    oauth_failed: 'Could not sign in with Google. Please try again.',
  } as Record<string, string>)[oauthError] ?? 'Could not sign in with Google. Please try again.' : null

  const errors = validate(values)
  const from = (location.state as { from?: string } | null)?.from

  if (user) return <Navigate to={loginDestination(user.role, from)} replace />

  function update(field: keyof LoginValues, value: string) {
    setValues((current) => ({ ...current, [field]: value }))
    setApiError(null)
  }

  async function submit(credentials: LoginValues) {
    if (pending.current) return
    pending.current = true
    setSubmitting(true)
    setApiError(null)
    try {
      const signedIn = await login(credentials)
      toast.success(`Welcome back, ${fullName(signedIn).split(' ')[0]}!`)
      navigate(loginDestination(signedIn.role, from), { replace: true })
    } catch (caught) {
      setApiError(getErrorMessage(caught))
    } finally {
      pending.current = false
      setSubmitting(false)
    }
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    touchAll()
    if (hasErrors(errors)) return
    void submit({ email: values.email.trim(), password: values.password })
  }

  return (
    <AuthShell
      title="Welcome back"
      description="Sign in to manage your rentals."
      footer={
        <>
          New to Rentify?{' '}
          <Link to={ROUTES.register} className="font-semibold text-primary hover:underline">
            Create an account
          </Link>
        </>
      }
    >
      {sessionExpired ? (
        <p className="mb-4 rounded-xl bg-secondary px-4 py-3 text-sm font-medium text-secondary-foreground" role="status">
          Your session has expired. Please sign in again.
        </p>
      ) : null}

      {oauthMessage ? <p role="alert" className="mb-4 rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive">{oauthMessage}</p> : null}
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        <FormField
          id="email"
          label="Email"
          type="email"
          autoComplete="email"
          value={values.email}
          onChange={(event) => update('email', event.target.value)}
          onBlur={() => touch('email')}
          error={isTouched('email') ? errors.email : undefined}
        />
        <FormField
          id="password"
          label="Password"
          type="password"
          autoComplete="current-password"
          value={values.password}
          onChange={(event) => update('password', event.target.value)}
          onBlur={() => touch('password')}
          error={isTouched('password') ? errors.password : undefined}
        />
        {apiError ? (
          <p className="rounded-xl bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive" role="alert">
            {apiError}
          </p>
        ) : null}
        <Button type="submit" size="lg" className="h-11 w-full rounded-full" disabled={submitting}>
          {submitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
          {submitting ? 'Signing in...' : 'Login'}
        </Button>
      </form>

      <OrDivider />
      <GoogleButton label="Login with Google" disabled={submitting} />

      {isMockApi ? (
        <div className="mt-6 rounded-2xl bg-secondary p-4">
          <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Demo accounts</p>
          <div className="mt-3 flex flex-wrap gap-2">
            {DEMO_ACCOUNTS.map((account) => (
              <Button
                key={account.email}
                type="button"
                variant="outline"
                size="sm"
                className="rounded-full"
                disabled={submitting}
                onClick={() => {
                  if (account.isOAuth) {
                    window.location.href = GOOGLE_OAUTH_URL
                  } else {
                    void submit({ email: account.email, password: account.password ?? '' })
                  }
                }}
              >
                {account.label}
              </Button>
            ))}
          </div>
        </div>
      ) : null}
    </AuthShell>
  )
}
