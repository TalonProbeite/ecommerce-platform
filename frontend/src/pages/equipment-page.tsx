import { ArrowLeft, BadgeCheck, Check, MapPin, PackageSearch, ShieldCheck } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { ROUTES } from '@/app/routes'
import { EmptyState } from '@/components/common/empty-state'
import { LinkButton } from '@/components/common/link-button'
import { Rating } from '@/components/common/rating'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { getCategoryLabel, getEquipmentById } from '@/data/equipment'
import { addDaysISO, calculateTotals, formatCurrency, rentalDays, todayISO } from '@/lib/pricing'
import { useCartStore } from '@/store/cart-store'
import { isMockApi } from '@/api/client'

export function EquipmentPage() {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const addItem = useCartStore((state) => state.addItem)
  const item = getEquipmentById(id)

  const today = todayISO()
  const [startDate, setStartDate] = useState(addDaysISO(today, 1))
  const [endDate, setEndDate] = useState(addDaysISO(today, 3))

  if (!item) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16">
        <EmptyState
          icon={PackageSearch}
          title="Equipment not found"
          description="This listing may have been removed."
          action={<LinkButton to={ROUTES.catalog}>Browse equipment</LinkButton>}
        />
      </div>
    )
  }

  const days = rentalDays(startDate, endDate)
  const datesValid = days > 0 && startDate >= today
  const { subtotal, serviceFee, total } = calculateTotals(days * item.pricePerDay)

  function handleStartChange(value: string) {
    setStartDate(value)
    if (endDate < value) setEndDate(value)
  }

  function handleBook() {
    if (!isMockApi || !item || !datesValid) return
    addItem({ equipmentId: item.id, startDate, endDate })
    toast.success(`${item.name} added to your cart.`)
    navigate(ROUTES.checkout)
  }

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
      <Link to={ROUTES.catalog} className="inline-flex items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" /> Back to catalog
      </Link>

      <div className="mt-6 grid gap-8 lg:grid-cols-[1fr_400px] lg:gap-12">
        <div>
          <div className="aspect-[4/3] overflow-hidden rounded-3xl bg-secondary">
            <img src={item.image} alt={item.name} className="size-full object-cover" />
          </div>

          <div className="mt-8">
            <p className="text-sm font-bold text-primary">{getCategoryLabel(item.category)}</p>
            <h1 className="mt-1 text-balance text-3xl font-extrabold tracking-tight sm:text-4xl">{item.name}</h1>
            <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-muted-foreground">
              <Rating value={item.rating} count={item.reviewCount} />
              <span className="inline-flex items-center gap-1">
                <MapPin className="size-4" aria-hidden="true" /> {item.location}
              </span>
              <span className="inline-flex items-center gap-1">
                {item.providerVerified ? <BadgeCheck className="size-4 text-primary" aria-label="Verified provider" /> : null}
                {item.provider}
              </span>
            </div>
            <p className="mt-6 text-pretty leading-relaxed text-muted-foreground">{item.description}</p>
          </div>

          <div className="mt-8 grid gap-4 sm:grid-cols-2">
            <section className="rounded-3xl border bg-card p-6">
              <h2 className="font-bold">Key features</h2>
              <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                {item.features.map((feature) => (
                  <li key={feature} className="flex gap-2">
                    <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" /> {feature}
                  </li>
                ))}
              </ul>
            </section>
            <section className="rounded-3xl border bg-card p-6">
              <h2 className="font-bold">What&apos;s included</h2>
              <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                {item.includes.map((entry) => (
                  <li key={entry} className="flex gap-2">
                    <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" /> {entry}
                  </li>
                ))}
              </ul>
            </section>
          </div>
        </div>

        <aside className="lg:sticky lg:top-24 lg:self-start">
          <div className="rounded-3xl border bg-card p-6 shadow-xl shadow-primary/5">
            <p>
              <span className="text-3xl font-extrabold">{formatCurrency(item.pricePerDay)}</span>
              <span className="text-muted-foreground"> / day</span>
            </p>

            <div className="mt-5 grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="start-date">Start</Label>
                <Input id="start-date" type="date" min={today} value={startDate} onChange={(event) => handleStartChange(event.target.value)} className="h-10" />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="end-date">End</Label>
                <Input id="end-date" type="date" min={startDate || today} value={endDate} onChange={(event) => setEndDate(event.target.value)} className="h-10" />
              </div>
            </div>

            {!datesValid ? (
              <p className="mt-3 text-sm text-destructive" role="alert">
                Choose a start date from today and an end date on or after it.
              </p>
            ) : (
              <dl className="mt-5 space-y-2 text-sm">
                <div className="flex justify-between">
                  <dt className="text-muted-foreground">
                    {formatCurrency(item.pricePerDay)} &times; {days} {days === 1 ? 'day' : 'days'}
                  </dt>
                  <dd>{formatCurrency(subtotal)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-muted-foreground">Service fee</dt>
                  <dd>{formatCurrency(serviceFee)}</dd>
                </div>
                <div className="flex justify-between border-t pt-3 text-base font-bold">
                  <dt>Total</dt>
                  <dd>{formatCurrency(total)}</dd>
                </div>
              </dl>
            )}

            <Button size="lg" className="mt-5 h-12 w-full rounded-full text-base" disabled={!isMockApi || !datesValid} onClick={handleBook}>
              Reserve now
            </Button>
            <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
              <ShieldCheck className="size-4" aria-hidden="true" />
              {formatCurrency(item.deposit)} refundable deposit held until return
            </p>
          </div>
        </aside>
      </div>
    </div>
  )
}
