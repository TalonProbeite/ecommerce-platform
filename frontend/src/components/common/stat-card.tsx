interface StatCardProps {
  label: string
  value: string
  hint?: string
}

export function StatCard({ label, value, hint }: StatCardProps) {
  return (
    <div className="rounded-3xl border bg-card p-6">
      <p className="text-sm font-semibold text-muted-foreground">{label}</p>
      <p className="mt-2 text-3xl font-extrabold tracking-tight">{value}</p>
      {hint ? <p className="mt-1 text-xs text-muted-foreground">{hint}</p> : null}
    </div>
  )
}
