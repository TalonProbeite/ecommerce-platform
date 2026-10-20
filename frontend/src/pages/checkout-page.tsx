import { CheckCircle2, ShoppingBag } from 'lucide-react'
import { useRef, useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { CheckoutDetailsFields, CheckoutFeedback, CheckoutItems, CheckoutSteps, CheckoutSummary } from '@/components/checkout/checkout-content'
import { EmptyState } from '@/components/common/empty-state'
import { LinkButton } from '@/components/common/link-button'
import { PageHeader } from '@/components/common/page-header'
import { Button } from '@/components/ui/button'
import { getEquipmentById } from '@/data/equipment'
import { hasErrors, type FieldErrors } from '@/lib/validation'
import { placeMockOrder, reviewCart, validateCheckoutDetails } from '@/services/checkout'
import { useAuthStore } from '@/store/auth-store'
import { useCartStore } from '@/store/cart-store'
import { useOrdersStore } from '@/store/orders-store'
import { isMockApi } from '@/api/client'
import type { CheckoutDetails } from '@/types'

export function CheckoutPage() {
  const navigate = useNavigate()
  const { pathname, search } = useLocation()
  const user = useAuthStore((state) => state.user)
  const items = useCartStore((state) => state.items)
  const removeItem = useCartStore((state) => state.removeItem)
  const orders = useOrdersStore((state) => state.orders)
  const [details, setDetails] = useState<CheckoutDetails>(() => ({
    fulfillment: 'pickup', contactName: [user?.firstName, user?.lastName].filter(Boolean).join(' '),
    phone: user?.phone ?? '', address: '', city: '', postalCode: '',
  }))
  const [errors, setErrors] = useState<FieldErrors<CheckoutDetails>>({})
  const [error, setError] = useState('')
  const [placing, setPlacing] = useState(false)
  const submitting = useRef(false)
  const formRef = useRef<HTMLFormElement>(null)
  const step = pathname.replace(/\/$/, '')
  const isCart = step === ROUTES.checkout || step === ROUTES.checkoutCart
  const isDelivery = step === ROUTES.checkoutDelivery
  const isSuccess = step === `${ROUTES.checkout}/success`
  const review = reviewCart(items)

  if (!isMockApi) return <div className="mx-auto max-w-3xl px-4 py-16"><EmptyState icon={ShoppingBag} title="Rental booking unavailable" description="Live rental booking is not connected yet. No order has been placed." action={<LinkButton to={ROUTES.catalog}>Browse equipment</LinkButton>} /></div>

  async function handlePlaceOrder(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (submitting.current) return
    const validation = validateCheckoutDetails(details)
    setErrors(validation)
    setError('')
    if (hasErrors(validation)) {
      requestAnimationFrame(() => formRef.current?.querySelector<HTMLInputElement>('[aria-invalid="true"]')?.focus())
      return
    }
    submitting.current = true
    setPlacing(true)
    try {
      const order = await placeMockOrder(details)
      navigate(ROUTES.checkoutSuccess(order.id), { replace: true })
    } catch (failure) {
      setError(failure instanceof Error ? failure.message : 'Could not place your order. Please try again.')
    } finally {
      submitting.current = false
      setPlacing(false)
    }
  }

  if (!isCart && !isDelivery && !isSuccess) return <div className="mx-auto max-w-3xl px-4 py-16"><EmptyState icon={ShoppingBag} title="Checkout step not found" description="Return to your cart to continue." action={<LinkButton to={ROUTES.checkoutCart}>View cart</LinkButton>} /></div>

  if (isSuccess) {
    const id = new URLSearchParams(search).get('order')
    const order = orders.find((entry) => entry.id === id && entry.userId === user?.id)
    if (!order) return <div className="mx-auto max-w-3xl px-4 py-16"><EmptyState icon={ShoppingBag} title="Confirmation not found" description="Check your rental history, or start a new rental. Demo orders are kept for this session." action={<LinkButton to={ROUTES.orders}>My rentals</LinkButton>} /></div>
    return <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
      <CheckCircle2 className="mb-4 size-12 text-success" aria-hidden="true" />
      <div role="status"><PageHeader title="Rental confirmed" description={`Order ${order.id} is confirmed. No payment was taken.`} /></div>
      <CheckoutSteps step={2} />
      <div className="mt-8 space-y-6">
        <CheckoutItems items={order.lines} lines={order.lines} />
        {order.checkoutDetails ? <section className="rounded-3xl border bg-card p-6">
          <h2 className="font-bold">{order.fulfillment === 'delivery' ? 'Delivery details' : 'Pickup details'}</h2>
          <p className="mt-2 break-words">{order.checkoutDetails.contactName} · {order.checkoutDetails.phone}</p>
          {order.fulfillment === 'delivery' ? <p className="mt-1 break-words">{order.checkoutDetails.address}, {order.checkoutDetails.city}, {order.checkoutDetails.postalCode}</p> : <ul className="mt-2 space-y-1 text-sm text-muted-foreground">{order.lines.map((line) => { const item = getEquipmentById(line.equipmentId); return <li key={line.equipmentId}>{item?.provider} · {item?.location}</li> })}</ul>}
        </section> : null}
        <CheckoutSummary {...order} />
        <div className="flex flex-col gap-3 sm:flex-row"><LinkButton to={ROUTES.orders}>View my rentals</LinkButton><LinkButton variant="outline" to={ROUTES.catalog}>Browse equipment</LinkButton></div>
      </div>
    </div>
  }

  if (!items.length) return <div className="mx-auto max-w-3xl px-4 py-16"><EmptyState icon={ShoppingBag} title="Your cart is empty" description="Find something you need and reserve it for your dates." action={<LinkButton to={ROUTES.catalog}>Browse equipment</LinkButton>} /></div>
  if (isDelivery && review.errors.length) return <Navigate to={ROUTES.checkoutCart} replace />

  return <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
    <PageHeader title="Checkout" description={isCart ? 'Review your items and rental dates.' : 'Choose pickup or delivery and confirm your rental.'} />
    <CheckoutSteps step={isCart ? 0 : 1} />
    <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
      <div className="min-w-0 space-y-6">
        {review.errors.length ? <div role="alert" className="rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"><ul className="space-y-2">{review.errors.map((message, index) => <li key={index}>{message}</li>)}</ul></div> : null}
        {isDelivery ? <form ref={formRef} id="checkout-details" noValidate onSubmit={handlePlaceOrder} aria-busy={placing} className="space-y-6">
          <CheckoutDetailsFields details={details} errors={errors} disabled={placing} onChange={(value) => { setDetails(value); setErrors({}); setError('') }} />
          <CheckoutFeedback placing={placing} error={error} invalid={hasErrors(errors)} />
        </form> : null}
        <CheckoutItems items={items} lines={review.lines} onRemove={isCart ? removeItem : undefined} />
        {isDelivery && details.fulfillment === 'pickup' ? <ul className="space-y-2 text-sm text-muted-foreground">{items.map((entry) => { const item = getEquipmentById(entry.equipmentId); return <li key={entry.equipmentId}>{item?.name}: {item?.provider} · {item?.location}</li> })}</ul> : null}
      </div>
      <aside className="space-y-3 lg:sticky lg:top-24 lg:self-start">
        <CheckoutSummary {...review} />
        {isCart ? <><Button size="lg" className="h-12 w-full rounded-full" disabled={review.errors.length > 0} onClick={() => navigate(ROUTES.checkoutDelivery)}>Continue to delivery details</Button><LinkButton variant="outline" className="w-full" to={ROUTES.catalog}>Continue browsing</LinkButton></> : <><Button type="submit" form="checkout-details" size="lg" className="h-12 w-full rounded-full" disabled={placing}>{placing ? 'Placing order...' : error ? 'Try again' : 'Confirm rental'}</Button><Button variant="outline" className="w-full" disabled={placing} onClick={() => navigate(ROUTES.checkoutCart)}>Back to cart</Button></>}
      </aside>
    </div>
  </div>
}
