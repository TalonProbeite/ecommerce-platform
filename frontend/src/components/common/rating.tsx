import { Star } from 'lucide-react'

export function Rating({ value, count }: { value: number; count: number }) {
  return (
    <span className="inline-flex items-center gap-1 text-sm">
      <Star className="size-4 fill-warning text-warning" aria-hidden="true" />
      <span className="font-semibold">{value.toFixed(1)}</span>
      <span className="text-muted-foreground">({count})</span>
      <span className="sr-only">rating out of 5 from {count} reviews</span>
    </span>
  )
}
