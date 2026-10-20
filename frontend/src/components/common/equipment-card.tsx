import { BadgeCheck, MapPin } from 'lucide-react'
import { Link } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { getCategoryLabel } from '@/data/equipment'
import { formatCurrency } from '@/lib/pricing'
import type { Equipment } from '@/types'
import { Rating } from './rating'

export function EquipmentCard({ item }: { item: Equipment }) {
  return (
    <Link
      to={ROUTES.equipment(item.id)}
      className="group flex flex-col overflow-hidden rounded-3xl border bg-card transition-all hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/10 focus-visible:outline-2 focus-visible:outline-ring"
    >
      <div className="relative aspect-square overflow-hidden bg-secondary">
        <img
          src={item.image}
          alt={item.name}
          loading="lazy"
          className="size-full object-cover transition-transform duration-500 group-hover:scale-105"
        />
        <span className="absolute left-3 top-3 rounded-full bg-background/90 px-3 py-1 text-xs font-semibold backdrop-blur">
          {getCategoryLabel(item.category)}
        </span>
      </div>
      <div className="flex flex-1 flex-col gap-2 p-5">
        <h3 className="text-balance font-bold leading-snug">{item.name}</h3>
        <div className="flex items-center gap-1 text-sm text-muted-foreground">
          <MapPin className="size-3.5" aria-hidden="true" />
          <span>{item.location}</span>
        </div>
        <div className="flex items-center gap-1 text-sm text-muted-foreground">
          {item.providerVerified ? <BadgeCheck className="size-3.5 text-primary" aria-label="Verified provider" /> : null}
          <span>{item.provider}</span>
        </div>
        <div className="mt-auto flex items-end justify-between pt-3">
          <Rating value={item.rating} count={item.reviewCount} />
          <p className="text-right">
            <span className="text-lg font-extrabold">{formatCurrency(item.pricePerDay)}</span>
            <span className="text-sm text-muted-foreground"> / day</span>
          </p>
        </div>
      </div>
    </Link>
  )
}
