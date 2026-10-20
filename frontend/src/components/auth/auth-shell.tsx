import type { ReactNode } from 'react'

interface AuthShellProps {
  title: string
  description: string
  children: ReactNode
  footer: ReactNode
}

export function AuthShell({ title, description, children, footer }: AuthShellProps) {
  return (
    <div className="mx-auto grid min-h-[70vh] max-w-md place-items-center px-4 py-12">
      <div className="min-w-0 w-full break-words rounded-3xl border bg-card p-7 shadow-xl shadow-primary/5 sm:p-8">
        <h1 className="text-2xl font-extrabold tracking-tight">{title}</h1>
        <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        <div className="mt-6">{children}</div>
        <p className="mt-6 text-center text-sm text-muted-foreground">{footer}</p>
      </div>
    </div>
  )
}
