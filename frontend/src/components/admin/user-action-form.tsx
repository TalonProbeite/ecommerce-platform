import { useRef, useState, type FormEvent } from 'react'
import { Loader2 } from 'lucide-react'
import { getErrorMessage } from '@/api/client'
import { FormField } from '@/components/auth/form-field'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { updateUserBan, updateUserRole } from '@/services/admin'
import type { Role } from '@/types'

const ROLE_OPTIONS = [
  { value: 'customer', label: 'Customer' },
  { value: 'analyst', label: 'Analyst' },
  { value: 'store_manager', label: 'Store Manager' },
  { value: 'admin', label: 'Admin' },
]
const BAN_OPTIONS = [{ value: 'true', label: 'Ban' }, { value: 'false', label: 'Unban' }]

export function UserActionForm({ action }: { action: 'role' | 'ban' }) {
  const isRole = action === 'role'
  const options = isRole ? ROLE_OPTIONS : BAN_OPTIONS
  const [target, setTarget] = useState('')
  const [parameter, setParameter] = useState(isRole ? 'customer' : 'true')
  const [pending, setPending] = useState(false)
  const pendingRef = useRef(false)
  const [touched, setTouched] = useState(false)
  const [feedback, setFeedback] = useState<{ error: boolean; message: string } | null>(null)
  const prefix = `admin-${action}`

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (pendingRef.current) return
    setTouched(true)
    setFeedback(null)
    const id = target.trim()
    if (!id) return
    pendingRef.current = true
    setPending(true)
    try {
      const message = isRole
        ? await updateUserRole(id, parameter as Role)
        : await updateUserBan(id, parameter === 'true')
      setFeedback({ error: false, message: `${id}: ${message} (${isRole ? parameter : parameter === 'true' ? 'banned' : 'unbanned'}).` })
    } catch (error) {
      setFeedback({ error: true, message: getErrorMessage(error) })
    } finally {
      pendingRef.current = false
      setPending(false)
    }
  }

  return (
    <form onSubmit={submit} noValidate className="space-y-5 rounded-3xl border bg-card p-6 sm:p-8" aria-labelledby={`${prefix}-title`} aria-busy={pending}>
      <div>
        <h2 id={`${prefix}-title`} className="text-xl font-bold">{isRole ? 'Change user role' : 'Ban or unban user'}</h2>
        <p className="mt-2 text-sm text-muted-foreground">
          {isRole ? 'Set the access level for one user.' : 'Block account access or restore it.'}
        </p>
      </div>
      <fieldset disabled={pending} className="space-y-5">
        <FormField id={`${prefix}-target`} label="User ID" value={target} required
          placeholder="e.g. u-user" autoComplete="off"
          onChange={(event) => { setTarget(event.target.value); setFeedback(null) }}
          onBlur={() => setTouched(true)} error={touched && !target.trim() ? 'Enter a user ID.' : undefined} />
        <div className="space-y-1.5">
          <Label htmlFor={`${prefix}-parameter`}>{isRole ? 'New role' : 'Account action'}</Label>
          <Select value={parameter} items={options} onValueChange={(value) => {
            if (value) { setParameter(value); setFeedback(null) }
          }} disabled={pending}>
            <SelectTrigger id={`${prefix}-parameter`} className="h-11 w-full"><SelectValue /></SelectTrigger>
            <SelectContent>{options.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent>
          </Select>
        </div>
        <Button type="submit" className="h-11 rounded-full px-6" disabled={pending}>
          {pending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
          {pending ? 'Applying...' : isRole ? 'Update role' : parameter === 'true' ? 'Ban user' : 'Unban user'}
        </Button>
      </fieldset>
      {feedback ? <p role={feedback.error ? 'alert' : 'status'} className={`break-words rounded-xl p-4 text-sm ${feedback.error ? 'bg-destructive/10 text-destructive' : 'bg-success/15 text-foreground'}`}>{feedback.message}</p> : null}
    </form>
  )
}
