import { Minus, Plus, ShoppingBag, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { ROUTES } from '@/app/routes'
import { EmptyState } from '@/components/common/empty-state'
import { LinkButton } from '@/components/common/link-button'
import { PageHeader } from '@/components/common/page-header'
import { Button } from '@/components/ui/button'
import { formatEurCents } from '@/lib/money'
import { billedHours, formatDateTime } from '@/lib/rental'
import { cartTotals, rentalItemKey, useRentalCartStore } from '@/store/rental-cart-store'
import { useAuthStore } from '@/store/auth-store'
import { submitRentalOrder } from '@/services/rental-checkout'
import { orderErrorMessage } from '@/api/order-client'

export function RentalCheckoutPage() {
  const navigate = useNavigate()
  const user = useAuthStore((state) => state.user)
  const items = useRentalCartStore((state) => state.items)
  const updateQuantity = useRentalCartStore((state) => state.updateQuantity)
  const removeItem = useRentalCartStore((state) => state.removeItem)
  const clear = useRentalCartStore((state) => state.clear)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const totals = cartTotals(items)
  const store = items[0]

  async function handleConfirm() {
    if (submitting) return
    if (!user?.id) {
      setError('Your session expired. Please sign in again.')
      return
    }
    setSubmitting(true)
    setError('')
    try {
      const order = await submitRentalOrder(user.id, items)
      clear()
      toast.success('Order created. We are reserving your equipment.')
      navigate(ROUTES.order(order.id), { replace: true })
    } catch (failure) {
      setError(orderErrorMessage(failure, 'Could not create your order. Please try again.'))
    } finally {
      setSubmitting(false)
    }
  }

  if (items.length === 0) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16">
        <EmptyState
          icon={ShoppingBag}
          title="Your cart is empty"
          description="Find equipment and choose your rental period to continue."
          action={<LinkButton to={ROUTES.catalog}>Browse equipment</LinkButton>}
        />
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <PageHeader title="Checkout" description="Review your rental period and confirm the order." />
      <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div className="min-w-0 space-y-4">
          {store ? (
            <p className="rounded-2xl border bg-card p-4 text-sm text-muted-foreground">
              Pickup from <span className="font-semibold text-foreground">{store.storeName}</span>
              {store.location ? ` · ${store.location}` : ''}
            </p>
          ) : null}

          <ul className="space-y-4">
            {items.map((item) => {
              const key = rentalItemKey(item)
              const hours = billedHours(item.rentalStartAt, item.rentalEndAt)
              return (
                <li key={key} className="rounded-3xl border bg-card p-4 sm:p-5">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <h2 className="break-words font-bold">{item.name}</h2>
                      <p className="mt-1 text-sm text-muted-foreground">
                        {formatDateTime(item.rentalStartAt)} – {formatDateTime(item.rentalEndAt)} · {hours} {hours === 1 ? 'hour' : 'hours'}
                      </p>
                      <p className="mt-1 text-sm text-muted-foreground">
                        {formatEurCents(item.pricePerHourCents)} / hour · {formatEurCents(item.depositPerUnitCents)} deposit / unit
                      </p>
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      aria-label={`Remove ${item.name}`}
                      onClick={() => removeItem(key)}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </div>
                  <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                    <div className="flex items-center gap-2">
                      <Button type="button" variant="outline" size="icon" aria-label={`Decrease quantity of ${item.name}`} disabled={item.quantity <= 1} onClick={() => updateQuantity(key, item.quantity - 1)}>
                        <Minus className="size-4" />
                      </Button>
                      <span className="min-w-8 text-center font-semibold" aria-live="polite">{item.quantity}</span>
                      <Button type="button" variant="outline" size="icon" aria-label={`Increase quantity of ${item.name}`} disabled={item.quantity >= 1000} onClick={() => updateQuantity(key, item.quantity + 1)}>
                        <Plus className="size-4" />
                      </Button>
                    </div>
                    <p className="font-bold">{formatEurCents(hours * item.pricePerHourCents * item.quantity)}</p>
                  </div>
                </li>
              )
            })}
          </ul>
        </div>

        <aside className="space-y-3 lg:sticky lg:top-24 lg:self-start">
          <section className="rounded-3xl border bg-card p-6" aria-labelledby="rental-summary-heading">
            <h2 id="rental-summary-heading" className="text-lg font-bold">Order summary</h2>
            <dl className="mt-4 space-y-2 text-sm">
              <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Rental subtotal</dt><dd>{formatEurCents(totals.subtotalCents)}</dd></div>
              <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Refundable deposit</dt><dd>{formatEurCents(totals.depositCents)}</dd></div>
              <div className="flex justify-between gap-3 border-t pt-3 text-base font-bold"><dt>Total</dt><dd>{formatEurCents(totals.totalCents)}</dd></div>
            </dl>
            <p className="mt-4 text-xs text-muted-foreground">The deposit is refunded when the equipment is returned.</p>
          </section>
          {error ? <p role="alert" className="rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">{error}</p> : null}
          <Button type="button" size="lg" className="h-12 w-full rounded-full" disabled={submitting} onClick={handleConfirm}>
            {submitting ? 'Creating order…' : 'Confirm rental'}
          </Button>
          <Button type="button" variant="outline" className="w-full" disabled={submitting} onClick={() => navigate(ROUTES.catalog)}>
            Continue browsing
          </Button>
        </aside>
      </div>
    </div>
  )
}
