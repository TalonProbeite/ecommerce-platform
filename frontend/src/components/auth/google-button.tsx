import { GOOGLE_OAUTH_URL } from '@/api/client'
import { Button } from '@/components/ui/button'

interface GoogleButtonProps {
  label: string
  disabled?: boolean
}

function GoogleMark() {
  return (
    <svg viewBox="0 0 24 24" className="size-5" aria-hidden="true">
      <path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.64h6.46a5.52 5.52 0 0 1-2.4 3.62v3h3.88c2.27-2.09 3.58-5.17 3.58-8.81z" />
      <path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.94-2.91l-3.88-3a7.2 7.2 0 0 1-10.7-3.78H1.4v3.1A12 12 0 0 0 12 24z" />
      <path fill="#FBBC05" d="M5.36 14.31a7.2 7.2 0 0 1 0-4.62v-3.1H1.4a12 12 0 0 0 0 10.82l3.96-3.1z" />
      <path fill="#EA4335" d="M12 4.75c1.76 0 3.34.6 4.58 1.8l3.44-3.44A11.5 11.5 0 0 0 12 0 12 12 0 0 0 1.4 6.59l3.96 3.1A7.2 7.2 0 0 1 12 4.75z" />
    </svg>
  )
}

export function GoogleButton({ label, disabled }: GoogleButtonProps) {
  function handleClick() {
    window.location.href = GOOGLE_OAUTH_URL
  }

  return (
    <Button type="button" variant="outline" size="lg" className="h-11 w-full gap-2 rounded-full" disabled={disabled} onClick={handleClick}>
      <GoogleMark />
      {label}
    </Button>
  )
}

export function OrDivider() {
  return (
    <div className="my-5 flex items-center gap-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground" role="separator">
      <span className="h-px flex-1 bg-border" />
      or
      <span className="h-px flex-1 bg-border" />
    </div>
  )
}
