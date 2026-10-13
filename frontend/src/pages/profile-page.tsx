import { useRef, useState, type FormEvent } from 'react'
import { CheckCircle2 } from 'lucide-react'
import { toast } from 'sonner'
import { getErrorMessage } from '@/api/client'
import { FormField } from '@/components/auth/form-field'
import { PasswordSecurity } from '@/components/auth/password-security'
import { RecentRentals } from '@/components/profile/recent-rentals'
import { EmailVerificationPrompt } from '@/components/profile/email-verification-prompt'
import { PageHeader } from '@/components/common/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useTouchedFields } from '@/hooks/use-touched-fields'
import { hasErrors, validateEmail, validateFirstName, validateLastName, validatePhone } from '@/lib/validation'
import { useAuthStore } from '@/store/auth-store'

export function ProfilePage() {
  const user = useAuthStore((state) => state.user)
  const updateProfile = useAuthStore((state) => state.updateProfile)
  const [firstName, setFirstName] = useState(user?.firstName ?? '')
  const [lastName, setLastName] = useState(user?.lastName ?? '')
  const [email, setEmail] = useState(user?.email ?? '')
  const [phone, setPhone] = useState(user?.phone ?? '')
  const [saving, setSaving] = useState(false)
  const pending = useRef(false)
  const { touch, touchAll, isTouched } = useTouchedFields()

  if (!user) return null

  const isOAuth = Boolean(user.isOAuth)
  const isVerified = user.emailVerified === true

  const errors = {
    firstName: validateFirstName(firstName),
    lastName: user.lastName && !lastName.trim() ? 'Changed fields cannot be empty.' : validateLastName(lastName),
    email: isOAuth ? undefined : validateEmail(email),
    phone: phone === (user.phone ?? '') ? undefined : validatePhone(phone),
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (pending.current) return
    touchAll()
    if (hasErrors(errors)) return
    pending.current = true
    setSaving(true)
    try {
      await updateProfile({
        firstName: firstName.trim(),
        lastName: lastName.trim() || undefined,
        phone: phone.trim(),
        email: isOAuth ? undefined : email.trim(),
      })
      toast.success('Profile updated.')
    } catch (error) {
      toast.error(getErrorMessage(error))
    } finally {
      pending.current = false
      setSaving(false)
    }
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6">
      <PageHeader title="Profile" description="Manage your personal details." />

      {user.emailVerified === false ? <EmailVerificationPrompt key={user.email} email={user.email} disabled={saving} /> : null}

      <form onSubmit={handleSubmit} className="mt-6 space-y-5 rounded-3xl border bg-card p-6 sm:p-8" noValidate>
        <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-4">
          <p className="text-sm font-medium text-muted-foreground">Account details</p>
          <div className="flex flex-wrap items-center gap-2">
            {user.role === 'admin' || user.role === 'analyst' ? <Badge className="rounded-full bg-secondary px-3 capitalize text-secondary-foreground">{user.role}</Badge> : null}
            {isVerified ? (
              <Badge className="flex items-center gap-1 rounded-full bg-success/15 px-3 font-medium text-success">
                <CheckCircle2 className="size-3.5" aria-hidden="true" /> Email verified
              </Badge>
            ) : user.emailVerified === false ? (
              <Badge variant="outline" className="rounded-full border-amber-500/40 bg-amber-500/10 px-3 font-medium text-amber-600 dark:text-amber-400">
                Email unverified
              </Badge>
            ) : null}
            {isOAuth ? (
              <Badge variant="outline" className="rounded-full border-primary/30 bg-primary/10 px-3 font-medium text-primary">
                Google OAuth
              </Badge>
            ) : null}
          </div>
        </div>

        <div className="grid gap-5 sm:grid-cols-2">
          <FormField
            id="profile-first-name"
            label="First name"
            autoComplete="given-name"
            maxLength={100}
            value={firstName}
            onChange={(event) => setFirstName(event.target.value)}
            onBlur={() => touch('firstName')}
            error={isTouched('firstName') ? errors.firstName : undefined}
          />
          <FormField
            id="profile-last-name"
            label="Last name"
            autoComplete="family-name"
            maxLength={100}
            value={lastName}
            onChange={(event) => setLastName(event.target.value)}
            onBlur={() => touch('lastName')}
            error={isTouched('lastName') ? errors.lastName : undefined}
          />
        </div>

        <FormField
          id="profile-email"
          label="Email"
          type="email"
          autoComplete="email"
          value={isOAuth ? user.email : email}
          disabled={isOAuth}
          readOnly={isOAuth}
          onChange={(event) => setEmail(event.target.value)}
          onBlur={() => touch('email')}
          error={isTouched('email') ? errors.email : undefined}
          hint={isOAuth ? 'Email is managed by your Google account and cannot be changed.' : undefined}
        />

        <FormField
          id="profile-phone"
          label="Phone"
          type="tel"
          autoComplete="tel"
          maxLength={30}
          value={phone}
          onChange={(event) => setPhone(event.target.value)}
          onBlur={() => touch('phone')}
          error={isTouched('phone') ? errors.phone : undefined}
        />

        <Button type="submit" size="lg" className="h-11 rounded-full px-8" disabled={saving}>
          {saving ? 'Saving...' : 'Save changes'}
        </Button>
      </form>
      <PasswordSecurity isOAuth={isOAuth} />
      <RecentRentals userId={user.id} showOrdersLink={user.role === 'customer'} />
    </div>
  )
}

