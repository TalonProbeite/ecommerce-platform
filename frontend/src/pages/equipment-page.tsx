import { AlertCircle, ArrowLeft, BadgeCheck, Check, MapPin, PackageSearch, ShieldCheck } from 'lucide-react'
import { useState } from 'react'
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { ROUTES } from '@/app/routes'
import { EmptyState } from '@/components/common/empty-state'
import { LinkButton } from '@/components/common/link-button'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { getCategoryLabel, getEquipmentById } from '@/data/equipment'
import { addDaysISO, calculateTotals, formatCurrency, rentalDays, todayISO } from '@/lib/pricing'
import { formatEurCents } from '@/lib/money'
import { billedHours, defaultRentalEnd, defaultRentalStart, isValidRentalRange, localInputToIso, toDateTimeInput } from '@/lib/rental'
import { storeFor, toEquipment } from '@/lib/catalog-mapping'
import { useCartStore } from '@/store/cart-store'
import { MAX_ITEM_QUANTITY, useRentalCartStore } from '@/store/rental-cart-store'
import { useAuthStore } from '@/store/auth-store'
import { LIVE_CATALOG, useCatalogProduct, useCatalogStores } from '@/hooks/use-catalog-data'
import type { Equipment } from '@/types'

interface LiveBooking {
  storeId: string
  storeName: string
  pricePerHourCents: number
  depositPerUnitCents: number
  available: number
  totalQuantity: number
}

function EquipmentDetail({ item, image, canBook, isGuest, booking }: { item: Equipment; image: string; canBook: boolean; isGuest: boolean; booking?: LiveBooking }) {
  const navigate = useNavigate()
  const location = useLocation()
  const addMockItem = useCartStore((state) => state.addItem)
  const addRentalItem = useRentalCartStore((state) => state.addItem)
  const rentalCartItems = useRentalCartStore((state) => state.items)
  const live = booking !== undefined

  const today = todayISO()
  const [startDate, setStartDate] = useState(addDaysISO(today, 1))
  const [endDate, setEndDate] = useState(addDaysISO(today, 3))
  const [startInput, setStartInput] = useState(() => defaultRentalStart())
  const [endInput, setEndInput] = useState(() => defaultRentalEnd(defaultRentalStart()))
  const [quantity, setQuantity] = useState(1)

  const days = rentalDays(startDate, endDate)
  const startIso = localInputToIso(startInput)
  const endIso = localInputToIso(endInput)
  const hours = startIso && endIso ? billedHours(startIso, endIso) : 0
  const datesValid = live
    ? Boolean(startIso && endIso && isValidRentalRange(startIso, endIso))
    : days > 0 && startDate >= today
  const unit = live ? 'hour' : (item.priceUnit ?? 'day')
  const available = live ? Math.max(0, Math.trunc(booking?.available ?? 0)) : MAX_ITEM_QUANTITY
  const outOfStock = live && available < 1
  const quantityLimit = Math.max(1, available)
  const clampedQuantity = Math.min(quantityLimit, Math.max(1, Number.isFinite(quantity) ? Math.trunc(quantity) : 1))
  const safeQuantity = outOfStock ? 0 : clampedQuantity
  const subtotalCents = live ? hours * (booking?.pricePerHourCents ?? 0) * safeQuantity : 0
  const depositCents = live ? (booking?.depositPerUnitCents ?? 0) * safeQuantity : 0
  const mockTotals = calculateTotals(days * item.pricePerDay)

  function handleStartChange(value: string) {
    setStartDate(value)
    if (endDate < value) setEndDate(value)
  }

  function handleLiveStartChange(value: string) {
    setStartInput(value)
    if (endInput <= value) setEndInput(defaultRentalEnd(value))
  }

  function handleQuantityChange(value: number) {
    if (!Number.isFinite(value)) {
      setQuantity(1)
      return
    }
    setQuantity(Math.min(quantityLimit, Math.max(1, Math.trunc(value))))
  }

  function handleBook() {
    if (!canBook || !datesValid) return
    if (live && booking) {
      if (!startIso || !endIso) return
      if (outOfStock) {
        toast.error('This item is currently out of stock.')
        return
      }
      if (safeQuantity < 1 || safeQuantity > available) {
        toast.error(`Only ${available} ${available === 1 ? 'unit is' : 'units are'} available for this rental.`)
        return
      }
      if (rentalCartItems.length > 0 && rentalCartItems[0].storeId !== booking.storeId) {
        const clear = window.confirm('Your cart already contains equipment from another store. Clear the cart and add this item?')
        if (!clear) return
      }
      const result = addRentalItem({
        productId: item.id,
        name: item.name,
        image,
        storeId: booking.storeId,
        storeName: booking.storeName,
        location: item.location,
        pricePerHourCents: booking.pricePerHourCents,
        depositPerUnitCents: booking.depositPerUnitCents,
        quantity: safeQuantity,
        available,
        rentalStartAt: startIso,
        rentalEndAt: endIso,
      })
      if (result === 'replaced') toast.message('Your cart was cleared because it contained equipment from another store.')
      else toast.success(`${item.name} added to your cart.`)
    } else {
      addMockItem({ equipmentId: item.id, startDate, endDate })
      toast.success(`${item.name} added to your cart.`)
    }
    navigate(ROUTES.checkout)
  }

  function handleSignIn() {
    navigate(ROUTES.login, { state: { from: `${location.pathname}${location.search}` } })
  }

  const pricePerHour = live ? (booking?.pricePerHourCents ?? 0) : item.pricePerDay
  const totalDisplay = live ? formatEurCents(subtotalCents) : formatCurrency(mockTotals.subtotal)

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
      <Link to={ROUTES.catalog} className="inline-flex items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" /> Back to catalog
      </Link>

      <div className="mt-6 grid gap-8 lg:grid-cols-[1fr_400px] lg:gap-12">
        <div>
          <div className="aspect-[4/3] overflow-hidden rounded-3xl bg-secondary">
            <img src={image} alt={item.name} className="size-full object-cover" />
          </div>

          <div className="mt-8">
            <p className="text-sm font-bold text-primary">{item.categoryLabel ?? getCategoryLabel(item.category)}</p>
            <h1 className="mt-1 text-balance text-3xl font-extrabold tracking-tight sm:text-4xl">{item.name}</h1>
            <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-muted-foreground">
              {item.location ? (
                <span className="inline-flex items-center gap-1">
                  <MapPin className="size-4" aria-hidden="true" /> {item.location}
                </span>
              ) : null}
              {item.provider ? (
                <span className="inline-flex items-center gap-1">
                  {item.providerVerified ? <BadgeCheck className="size-4 text-primary" aria-label="Verified provider" /> : null}
                  {item.provider}
                </span>
              ) : null}
            </div>
            {item.description ? <p className="mt-6 text-pretty leading-relaxed text-muted-foreground">{item.description}</p> : null}
          </div>

          <div className="mt-8 grid gap-4 sm:grid-cols-2">
            {item.features.length > 0 ? (
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
            ) : null}
            {item.includes.length > 0 ? (
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
            ) : null}
          </div>
        </div>

        <aside className="lg:sticky lg:top-24 lg:self-start">
          <div className="rounded-3xl border bg-card p-6 shadow-xl shadow-primary/5">
            <p>
              <span className="text-3xl font-extrabold">{live ? formatEurCents(pricePerHour) : formatCurrency(pricePerHour)}</span>
              <span className="text-muted-foreground"> / {unit}</span>
            </p>

            {live ? (
              <>
                <div className="mt-5 space-y-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="rental-start">Start</Label>
                    <Input id="rental-start" type="datetime-local" min={toDateTimeInput(new Date())} value={startInput} onChange={(event) => handleLiveStartChange(event.target.value)} className="h-10" />
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="rental-end">End</Label>
                    <Input id="rental-end" type="datetime-local" min={startInput} value={endInput} onChange={(event) => setEndInput(event.target.value)} className="h-10" />
                  </div>
                  <div className="space-y-1.5">
                    <div className="flex items-center justify-between gap-3">
                      <Label htmlFor="rental-quantity">Quantity</Label>
                      <span className={outOfStock ? 'text-xs font-semibold text-destructive' : 'text-xs font-semibold text-muted-foreground'}>
                        {outOfStock
                          ? 'Out of stock'
                          : `${available} available${booking && booking.totalQuantity > available ? ` of ${booking.totalQuantity}` : ''}`}
                      </span>
                    </div>
                    <Input id="rental-quantity" type="number" inputMode="numeric" min={1} max={quantityLimit} value={clampedQuantity} disabled={outOfStock} onChange={(event) => handleQuantityChange(Number(event.target.value))} className="h-10" />
                  </div>
                </div>

                {!datesValid ? (
                  <p className="mt-3 text-sm text-destructive" role="alert">Choose a future start and an end after the start.</p>
                ) : (
                  <dl className="mt-5 space-y-2 text-sm">
                    <div className="flex justify-between">
                      <dt className="text-muted-foreground">{formatEurCents(pricePerHour)} × {hours} {hours === 1 ? 'hour' : 'hours'} × {safeQuantity}</dt>
                      <dd>{formatEurCents(subtotalCents)}</dd>
                    </div>
                    <div className="flex justify-between">
                      <dt className="text-muted-foreground">Refundable deposit</dt>
                      <dd>{formatEurCents(depositCents)}</dd>
                    </div>
                    <div className="flex justify-between border-t pt-3 text-base font-bold">
                      <dt>Total</dt>
                      <dd>{formatEurCents(subtotalCents + depositCents)}</dd>
                    </div>
                  </dl>
                )}
              </>
            ) : (
              <>
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
                        {formatCurrency(item.pricePerDay)} &times; {days} {days === 1 ? unit : `${unit}s`}
                      </dt>
                      <dd>{formatCurrency(mockTotals.subtotal)}</dd>
                    </div>
                    <div className="flex justify-between">
                      <dt className="text-muted-foreground">Service fee</dt>
                      <dd>{formatCurrency(mockTotals.serviceFee)}</dd>
                    </div>
                    <div className="flex justify-between border-t pt-3 text-base font-bold">
                      <dt>Total</dt>
                      <dd>{formatCurrency(mockTotals.total)}</dd>
                    </div>
                  </dl>
                )}
              </>
            )}

            <Button
              size="lg"
              className="mt-5 h-12 w-full rounded-full text-base"
              disabled={!isGuest && (!canBook || !datesValid || outOfStock)}
              onClick={isGuest ? handleSignIn : handleBook}
            >
              {isGuest ? 'Sign in to reserve' : outOfStock ? 'Out of stock' : canBook ? 'Reserve now' : 'Customer accounts only'}
            </Button>
            <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
              <ShieldCheck className="size-4" aria-hidden="true" />
              {live
                ? `${formatEurCents(depositCents)} refundable deposit for this rental`
                : `${formatCurrency(item.deposit)} refundable deposit held until return`}
            </p>
          </div>
        </aside>
      </div>
    </div>
  )
}

function MockEquipmentPage() {
  const { id = '' } = useParams()
  const user = useAuthStore((state) => state.user)
  const item = getEquipmentById(id)

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

  return <EquipmentDetail item={item} image={item.image} canBook={Boolean(user)} isGuest={!user} />
}

function LiveEquipmentPage() {
  const { id = '' } = useParams()
  const user = useAuthStore((state) => state.user)
  const product = useCatalogProduct(id)
  const stores = useCatalogStores()

  if (product.state.status === 'loading' || stores.state.status === 'loading') {
    return (
      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6" role="status" aria-live="polite">
        <span className="sr-only">Loading equipment...</span>
        <Skeleton className="h-[60vh] rounded-3xl" />
      </div>
    )
  }

  if (product.state.status === 'error') {
    if (product.state.httpStatus === 404) {
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

    return (
      <div className="mx-auto max-w-3xl px-4 py-16">
        <div role="alert" className="rounded-3xl border bg-card p-6">
          <AlertCircle className="size-6 text-destructive" aria-hidden="true" />
          <p className="mt-3 font-semibold">Unable to load this equipment</p>
          <p className="mt-1 text-sm text-muted-foreground">{product.state.error}</p>
          <Button variant="outline" className="mt-4 rounded-full" onClick={() => product.reload()}>Try again</Button>
        </div>
      </div>
    )
  }

  const storeList = stores.state.status === 'success' ? stores.state.data : []
  const store = storeFor(product.state.data, storeList)
  const item = toEquipment(product.state.data, store)
  const image = product.state.data.main_image_url || item.image

  return (
    <EquipmentDetail
      item={item}
      image={image}
      canBook={user?.role === 'customer'}
      isGuest={!user}
      booking={{
        storeId: product.state.data.store_id,
        storeName: store?.name ?? 'Equipment provider',
        pricePerHourCents: product.state.data.price_per_hour,
        depositPerUnitCents: product.state.data.deposit_amount,
        available: product.state.data.stock?.available ?? 0,
        totalQuantity: product.state.data.stock?.quantity ?? 0,
      }}
    />
  )
}

export function EquipmentPage() {
  return LIVE_CATALOG ? <LiveEquipmentPage /> : <MockEquipmentPage />
}
