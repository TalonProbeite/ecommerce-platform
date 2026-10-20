import type { ComponentProps } from 'react'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface FormFieldProps extends ComponentProps<typeof Input> {
  id: string
  label: string
  error?: string
  hint?: string
}

export function FormField({ id, label, error, hint, ...inputProps }: FormFieldProps) {
  const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined

  return (
    <div className="min-w-0 space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      <Input id={id} name={id} spellCheck={inputProps.type === 'email' ? false : undefined} className="h-11" aria-invalid={Boolean(error)} aria-describedby={describedBy} {...inputProps} />
      {error ? (
        <p id={`${id}-error`} role="alert" className="break-words text-xs font-medium text-destructive">
          {error}
        </p>
      ) : hint ? (
        <p id={`${id}-hint`} className="text-xs text-muted-foreground">
          {hint}
        </p>
      ) : null}
    </div>
  )
}
