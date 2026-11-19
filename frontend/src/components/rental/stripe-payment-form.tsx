import { Elements, PaymentElement, useElements, useStripe } from '@stripe/react-stripe-js'
import { loadStripe, type Stripe } from '@stripe/stripe-js'
import { useMemo, useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'

interface StripePaymentFormProps {
  orderId: string
  clientSecret: string
  publishableKey: string
  returnUrl: string
  onConfirmed: () => void
  onFailed?: () => void
}

const stripePromises = new Map<string, Promise<Stripe | null>>()

function stripeFor(publishableKey: string): Promise<Stripe | null> {
  const cached = stripePromises.get(publishableKey)
  if (cached) return cached
  const promise = loadStripe(publishableKey)
  stripePromises.set(publishableKey, promise)
  return promise
}

export function StripePaymentForm({ orderId, clientSecret, publishableKey, returnUrl, onConfirmed, onFailed }: StripePaymentFormProps) {
  const stripePromise = useMemo(() => (publishableKey ? stripeFor(publishableKey) : null), [publishableKey])

  if (!stripePromise) {
    return <p role="alert" className="rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">Stripe is not configured. The order remains unpaid.</p>
  }

  return (
    <Elements
      stripe={stripePromise}
      options={{
        clientSecret,
        appearance: { theme: 'stripe', variables: { colorPrimary: '#166534' } },
      }}
    >
      <PaymentForm orderId={orderId} returnUrl={returnUrl} onConfirmed={onConfirmed} onFailed={onFailed} />
    </Elements>
  )
}

function PaymentForm({ orderId, returnUrl, onConfirmed, onFailed }: Pick<StripePaymentFormProps, 'orderId' | 'returnUrl' | 'onConfirmed' | 'onFailed'>) {
  const stripe = useStripe()
  const elements = useElements()
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState('')
  const [complete, setComplete] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!stripe || !elements || !complete || submitting) return
    setSubmitting(true)
    setMessage('')
    try {
      const result = await stripe.confirmPayment({
        elements,
        confirmParams: { return_url: returnUrl },
        redirect: 'if_required',
      })
      if (result.error?.message) {
        setMessage(result.error.message)
        onFailed?.()
        return
      }
      onConfirmed()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'The payment could not be confirmed.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5" aria-busy={submitting}>
      <div id={`payment-element-${orderId}`} className="min-h-24 rounded-2xl border bg-card p-4">
        <PaymentElement
          options={{ layout: 'tabs' }}
          onChange={(event) => setComplete(Boolean(event.complete))}
          onLoadError={(event) => setMessage(event.error.message ?? 'Could not load the payment form.')}
        />
      </div>
      {message ? <p role="alert" className="rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">{message}</p> : null}
      <Button type="submit" size="lg" className="h-12 w-full rounded-full" disabled={!stripe || !complete || submitting}>
        {submitting ? 'Confirming payment…' : 'Pay now'}
      </Button>
      <p className="text-xs text-muted-foreground">
        Payment details are handled directly by Stripe. The order remains unpaid if the payment is not confirmed.
      </p>
    </form>
  )
}
