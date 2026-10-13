import { Loader2 } from 'lucide-react'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { isAxiosError } from 'axios'
import { getErrorCode, getErrorMessage } from '@/api/client'
import { ROUTES } from '@/app/routes'
import { AuthShell } from '@/components/auth/auth-shell'
import { FormField } from '@/components/auth/form-field'
import { GoogleButton } from '@/components/auth/google-button'
import { Button } from '@/components/ui/button'
import { LinkButton } from '@/components/common/link-button'
import { useTouchedFields } from '@/hooks/use-touched-fields'
import { roleHome } from '@/lib/roles'
import { hasErrors, validateFirstName, validateLastName, validatePhone } from '@/lib/validation'
import { loadOAuthProfile, type PendingOAuthProfile } from '@/services/auth'
import { OAuthSessionRestoreError, useAuthStore } from '@/store/auth-store'

export function OAuthCompletePage() {
  const navigate = useNavigate()
  const complete = useAuthStore(state => state.completeOAuth)
  const [profile, setProfile] = useState<PendingOAuthProfile | null>(null)
  const [values, setValues] = useState({ firstName: '', lastName: '', phone: '' })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [expired, setExpired] = useState(false)
  const [conflict, setConflict] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [attempt, setAttempt] = useState(0)
  const pending = useRef(false)
  const { touch, touchAll, isTouched } = useTouchedFields()
  const errors = { firstName: validateFirstName(values.firstName), lastName: validateLastName(values.lastName), phone: validatePhone(values.phone) }

  useEffect(() => {
    let active = true
    void loadOAuthProfile().then(data => {
      if (!active) return
      setProfile(data)
      setValues({ firstName: data.firstName, lastName: data.lastName, phone: '' })
    }).catch(caught => {
      if (!active) return
      setExpired(isAxiosError(caught) && caught.response?.status === 401)
      setError(getErrorMessage(caught, 'Could not load your Google profile. Please try again.'))
    }).finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [attempt])

  function bind(field: keyof typeof values) {
    return { value: values[field], onChange: (event: { target: { value: string } }) => {
      setValues(current => ({ ...current, [field]: event.target.value }))
      setError(null)
    }, onBlur: () => touch(field), error: isTouched(field) ? errors[field] : undefined }
  }

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (pending.current || expired || conflict) return
    touchAll()
    if (hasErrors(errors)) return
    pending.current = true
    setSubmitting(true)
    setError(null)
    try {
      const user = await complete({ firstName: values.firstName.trim(), lastName: values.lastName.trim(), phone: values.phone.trim() })
      navigate(roleHome(user.role), { replace: true })
    } catch (caught) {
      const code = getErrorCode(caught)
      if (code === 'oauth_registration_expired') setExpired(true)
      if (code === 'email_already_exists') setConflict(true)
      // Completion may have succeeded while the follow-up profile GET failed.
      // Restore the session on a separate screen instead of submitting again.
      if (caught instanceof OAuthSessionRestoreError) {
        navigate(ROUTES.oauthSuccess, { replace: true })
        return
      }
      setError(getErrorMessage(caught))
    } finally { pending.current = false; setSubmitting(false) }
  }

  return <AuthShell title="Complete your profile" description="Finish setting up your Rentify account with Google."
    footer={<Link to={ROUTES.login} className="font-semibold text-primary hover:underline">Back to login</Link>}>
    {loading ? <p role="status" className="flex items-center gap-3 text-sm text-muted-foreground"><Loader2 aria-hidden="true" className="size-5 animate-spin" />Loading your Google profile...</p> : expired ? <div className="space-y-4">
      <p role="alert" className="text-sm text-muted-foreground">Your Google registration has expired. Continue with Google to start again.</p>
      <GoogleButton label="Continue with Google" />
    </div> : !profile ? <div className="space-y-4">
      <p role="alert" className="text-sm text-destructive">{error}</p>
      <Button className="w-full rounded-full" onClick={() => { setError(null); setLoading(true); setAttempt(value => value + 1) }}>Try again</Button>
    </div> : <form className="space-y-4" onSubmit={submit} noValidate>
      <FormField id="email" label="Google email" type="email" value={profile.email} readOnly autoComplete="email" hint="Email is managed by your Google account." />
      <div className="grid gap-4 sm:grid-cols-2">
        <FormField id="firstName" label="First name" autoComplete="given-name" maxLength={100} disabled={submitting || conflict} {...bind('firstName')} />
        <FormField id="lastName" label="Last name (optional)" autoComplete="family-name" maxLength={100} disabled={submitting || conflict} {...bind('lastName')} />
      </div>
      <FormField id="phone" label="Phone" type="tel" autoComplete="tel" maxLength={30} disabled={submitting || conflict} hint="10 to 30 characters." {...bind('phone')} />
      {error ? <p role="alert" className="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive">{error}</p> : null}
      {conflict ? <LinkButton to={ROUTES.login} className="w-full rounded-full">Sign in to your existing account</LinkButton> : <Button type="submit" size="lg" className="h-11 w-full rounded-full" disabled={submitting}>
        {submitting ? <Loader2 aria-hidden="true" className="size-4 animate-spin" /> : null}
        {submitting ? 'Creating account...' : 'Complete registration'}
      </Button>}
    </form>}
  </AuthShell>
}
