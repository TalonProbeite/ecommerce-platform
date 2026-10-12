import { Loader2 } from 'lucide-react'
import { useRef, useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { getErrorMessage } from '@/api/client'
import { ROUTES } from '@/app/routes'
import { AuthShell } from '@/components/auth/auth-shell'
import { FormField } from '@/components/auth/form-field'
import { GoogleButton, OrDivider } from '@/components/auth/google-button'
import { Button } from '@/components/ui/button'
import { useTouchedFields } from '@/hooks/use-touched-fields'
import { roleHome } from '@/lib/roles'
import {
  hasErrors,
  validateEmail,
  validateFirstName,
  validateLastName,
  validatePassword,
  validatePhone,
  type FieldErrors,
} from '@/lib/validation'
import { useAuthStore } from '@/store/auth-store'

interface RegisterValues {
  email: string
  password: string
  repeatPassword: string
  firstName: string
  lastName: string
  phone: string
}

const INITIAL_VALUES: RegisterValues = {
  email: '',
  password: '',
  repeatPassword: '',
  firstName: '',
  lastName: '',
  phone: '',
}

function validate(values: RegisterValues): FieldErrors<RegisterValues> {
  return {
    email: validateEmail(values.email),
    password: validatePassword(values.password),
    repeatPassword:
      !values.repeatPassword ? 'Repeat your password.' : values.repeatPassword !== values.password ? 'Passwords do not match.' : undefined,
    firstName: validateFirstName(values.firstName),
    lastName: validateLastName(values.lastName),
    phone: validatePhone(values.phone),
  }
}

export function RegisterPage() {
  const navigate = useNavigate()
  const user = useAuthStore((state) => state.user)
  const register = useAuthStore((state) => state.register)
  const [values, setValues] = useState<RegisterValues>(INITIAL_VALUES)
  const [apiError, setApiError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const pending = useRef(false)
  const { touch, touchAll, isTouched } = useTouchedFields()

  const errors = validate(values)

  if (user && !submitting) return <Navigate to={roleHome(user.role)} replace />

  function bind(field: keyof RegisterValues) {
    return {
      value: values[field],
      onChange: (event: { target: { value: string } }) => {
        setValues((current) => ({ ...current, [field]: event.target.value }))
        setApiError(null)
      },
      onBlur: () => touch(field),
      error: isTouched(field) ? errors[field] : undefined,
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (pending.current) return
    touchAll()
    if (hasErrors(errors)) return
    pending.current = true
    setSubmitting(true)
    setApiError(null)
    try {
      const email = values.email.trim()
      await register({
        email,
        password: values.password,
        firstName: values.firstName.trim(),
        lastName: values.lastName.trim() || undefined,
        phone: values.phone.trim(),
      })
      toast.success('Account created. Check your email for a verification code.')
      navigate(ROUTES.verifyEmail, { state: { email } })
    } catch (caught) {
      setApiError(getErrorMessage(caught))
    } finally {
      pending.current = false
      setSubmitting(false)
    }
  }

  return (
    <AuthShell
      title="Create your account"
      description="Start renting gear in minutes."
      footer={
        <>
          Already have an account?{' '}
          <Link to={ROUTES.login} className="font-semibold text-primary hover:underline">
            Back to login
          </Link>
        </>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField id="firstName" label="First name" autoComplete="given-name" maxLength={100} {...bind('firstName')} />
          <FormField id="lastName" label="Last name (optional)" autoComplete="family-name" maxLength={100} {...bind('lastName')} />
        </div>
        <FormField id="email" label="Email" type="email" autoComplete="email" {...bind('email')} />
        <FormField id="phone" label="Phone" type="tel" autoComplete="tel" maxLength={30} hint="10 to 30 characters." {...bind('phone')} />
        <FormField id="password" label="Password" type="password" autoComplete="new-password" hint="At least 8 characters." {...bind('password')} />
        <FormField id="repeatPassword" label="Repeat password" type="password" autoComplete="new-password" {...bind('repeatPassword')} />
        {apiError ? (
          <p className="rounded-xl bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive" role="alert">
            {apiError}
          </p>
        ) : null}
        <Button type="submit" size="lg" className="h-11 w-full rounded-full" disabled={submitting}>
          {submitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
          {submitting ? 'Creating account...' : 'Create account'}
        </Button>
      </form>

      <OrDivider />
      <GoogleButton label="Continue with Google" disabled={submitting} />
    </AuthShell>
  )
}
