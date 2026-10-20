import { Loader2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { getErrorMessage } from '@/api/client'
import { ROUTES } from '@/app/routes'
import { FormField } from '@/components/auth/form-field'
import { Button } from '@/components/ui/button'
import { useTouchedFields } from '@/hooks/use-touched-fields'
import { hasErrors, validatePassword } from '@/lib/validation'
import { useAuthStore } from '@/store/auth-store'
import type { ChangePasswordPayload } from '@/types'

export function PasswordSecurity({ isOAuth }: { isOAuth: boolean }) {
  const navigate = useNavigate()
  const changePassword = useAuthStore((state) => state.changePassword)
  const [values, setValues] = useState<ChangePasswordPayload>({ old_pass: '', new_pass: '' })
  const [submitting, setSubmitting] = useState(false)
  const [apiError, setApiError] = useState<string | null>(null)
  const { touch, touchAll, isTouched } = useTouchedFields()
  const errors = { old_pass: validatePassword(values.old_pass), new_pass: validatePassword(values.new_pass) }

  function update(field: keyof ChangePasswordPayload, value: string) {
    setValues((current) => ({ ...current, [field]: value }))
    setApiError(null)
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (submitting || isOAuth) return
    touchAll()
    setApiError(null)
    if (hasErrors(errors)) return
    setSubmitting(true)
    try {
      await changePassword(values)
      setValues({ old_pass: '', new_pass: '' })
      toast.success('Password changed. Please sign in with your new password.')
      navigate(ROUTES.login, { replace: true })
    } catch (error) {
      setApiError(getErrorMessage(error, 'Unable to change your password. Please try again.'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <section aria-labelledby="password-security-title" className="mt-6 space-y-5 rounded-3xl border bg-card p-6 sm:p-8">
      <h2 id="password-security-title" className="border-b pb-4 text-sm font-medium text-muted-foreground">Password &amp; Security</h2>
      {isOAuth ? (
        <p className="text-sm text-muted-foreground">You sign in with Google. Manage your password and security in your Google account.</p>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-5" noValidate aria-busy={submitting}>
          <p className="text-sm text-muted-foreground">Changing your password will end your current session. You’ll need to sign in again.</p>
          <FormField
            id="profile-old-password"
            name="old_pass"
            label="Current password"
            type="password"
            autoComplete="current-password"
            required
            minLength={8}
            value={values.old_pass}
            disabled={submitting}
            onChange={(event) => update('old_pass', event.target.value)}
            onBlur={() => touch('old_pass')}
            error={isTouched('old_pass') ? errors.old_pass : undefined}
          />
          <FormField
            id="profile-new-password"
            name="new_pass"
            label="New password"
            type="password"
            autoComplete="new-password"
            required
            minLength={8}
            hint="At least 8 characters."
            value={values.new_pass}
            disabled={submitting}
            onChange={(event) => update('new_pass', event.target.value)}
            onBlur={() => touch('new_pass')}
            error={isTouched('new_pass') ? errors.new_pass : undefined}
          />
          {apiError ? (
            <p className="rounded-xl bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive" role="alert">{apiError}</p>
          ) : null}
          <Button type="submit" size="lg" className="h-11 rounded-full px-8" disabled={submitting}>
            {submitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
            {submitting ? 'Changing password...' : 'Change password'}
          </Button>
        </form>
      )}
    </section>
  )
}
