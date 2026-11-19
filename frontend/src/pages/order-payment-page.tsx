import { ArrowLeft, CreditCard } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { ROUTES } from '@/app/routes'
import { createOrderPaymentIntent, orderErrorMessage } from '@/api/order-client'
import { STRIPE_PUBLISHABLE_KEY } from '@/api/endpoints'
import { LinkButton } from '@/components/common/link-button'
import { PageHeader } from '@/components/common/page-header'
import { StripePaymentForm } from '@/components/rental/stripe-payment-form'
import { UnderDevelopment } from '@/components/common/under-development'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useRentalOrder } from '@/hooks/use-rental-order'
import { useAuthStore } from '@/store/auth-store'
import { formatEurCents } from '@/lib/money'
import { canPay, ORDER_STATUS_LABELS } from '@/lib/order-status'
import type { PaymentIntent } from '@/types/rental-order'

function CustomerOrderPaymentPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { state, reload } = useRentalOrder(id, true)
  const [intent, setIntent] = useState<PaymentIntent | null>(null)
  const [intentError, setIntentError] = useState('')
  const [confirmed, setConfirmed] = useState(false)
  const [declined, setDeclined] = useState(false)
  const requested = useRef(false)

  const order = state.status === 'success' ? state.order : null
  const payable = order ? canPay(order.status) : false

  function requestIntent() {
    if (!id) return
    setIntentError('')
    setDeclined(false)
    createOrderPaymentIntent(id).then(setIntent).catch((error: unknown) => {
      setIntentError(orderErrorMessage(error, 'Could not start the payment.'))
    })
  }

  useEffect(() => {
    if (!id || !payable || requested.current) return
    requested.current = true
    requestIntent()
  }, [id, payable])

  useEffect(() => {
    if (!confirmed || !order) return
    if (order.status === 'paid') {
      toast.success('Payment received.')
      navigate(ROUTES.order(order.id), { replace: true })
    }
  }, [confirmed, order, navigate])

  useEffect(() => {
    if (intent?.status !== 'succeeded') return
    setConfirmed(true)
    setDeclined(false)
    setIntent(null)
    reload()
  }, [intent, reload])

  if (state.status === 'loading') {
    if (confirmed) {
      return (
        <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6" role="status" aria-live="polite">
          <p className="rounded-2xl border bg-card p-4 text-sm text-muted-foreground">Payment received. Updating your order…</p>
        </div>
      )
    }
    return <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6" role="status" aria-live="polite"><span className="sr-only">Loading payment...</span><Skeleton className="h-72 rounded-3xl" /></div>
  }

  if (state.status === 'error') {
    return (
      <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <div role="alert" className="rounded-3xl border bg-card p-6">
          <p className="font-semibold">Unable to load this order</p>
          <p className="mt-1 text-sm text-muted-foreground">{state.error}</p>
          <Button type="button" variant="outline" className="mt-4 rounded-full" onClick={reload}>Try again</Button>
        </div>
      </div>
    )
  }

  const current = state.order
  if (!canPay(current.status)) {
    const message = current.status === 'paid'
      ? 'This order is already paid.'
      : `Payment is not available while the order is ${ORDER_STATUS_LABELS[current.status].toLowerCase()}.`
    return (
      <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <div className="rounded-3xl border bg-card p-6">
          <CreditCard className="size-8 text-muted-foreground" aria-hidden="true" />
          <p className="mt-4 font-semibold">{message}</p>
          <LinkButton to={ROUTES.order(current.id)} className="mt-4">View order</LinkButton>
        </div>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6">
      <Link to={ROUTES.order(current.id)} className="inline-flex items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" /> Back to order
      </Link>
      <div className="mt-5">
        <PageHeader title="Payment" description={`Order ${current.id} · ${formatEurCents(current.totalAmount)}`} />
      </div>

      <div className="mt-8 rounded-3xl border bg-card p-6">
        {declined ? (
          <p role="alert" className="mb-4 rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">
            The payment was declined. Try another card.
          </p>
        ) : null}
        {!STRIPE_PUBLISHABLE_KEY ? (
          <p role="alert" className="rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">
            Stripe is not configured. The order remains unpaid.
          </p>
        ) : intentError ? (
          <div role="alert">
            <p className="rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">{intentError}</p>
            <Button type="button" variant="outline" className="mt-4 rounded-full" onClick={() => {
              if (!id) return
              setIntentError('')
              createOrderPaymentIntent(id).then(setIntent).catch((error: unknown) => setIntentError(orderErrorMessage(error)))
            }}>Try again</Button>
          </div>
        ) : confirmed || intent?.status === 'succeeded' ? (
          <p role="status" className="text-sm text-muted-foreground">Payment received. Updating your order…</p>
        ) : intent?.status === 'canceled' ? (
          <div role="alert">
            <p className="rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">This payment session is no longer active.</p>
            <Button type="button" variant="outline" className="mt-4 rounded-full" onClick={requestIntent}>Start a new payment</Button>
          </div>
        ) : !intent ? (
          <p role="status" className="text-sm text-muted-foreground">Preparing secure payment…</p>
        ) : (
          <StripePaymentForm
            orderId={current.id}
            clientSecret={intent.clientSecret}
            publishableKey={STRIPE_PUBLISHABLE_KEY}
            returnUrl={`${window.location.origin}${ROUTES.order(current.id)}`}
            onConfirmed={() => { setConfirmed(true); setDeclined(false); setIntent(null); reload() }}
            onFailed={(message) => {
              setDeclined(true)
              toast.error(message)
            }}
          />
        )}
      </div>
    </div>
  )
}

export function OrderPaymentPage() {
  const role = useAuthStore((state) => state.user?.role)
  if (role !== 'customer') {
    return <UnderDevelopment title="Payment" description="Payments are available to customer accounts in this release." />
  }
  return <CustomerOrderPaymentPage />
}
