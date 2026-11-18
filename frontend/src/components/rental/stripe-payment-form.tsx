import { useEffect, useRef, useState, type FormEvent } from 'react'
import { loadStripe, type StripeElements, type StripeInstance, type StripePaymentElement } from '@/lib/stripe'
import { Button } from '@/components/ui/button'

interface StripePaymentFormProps {
  orderId: string
  clientSecret: string
  publishableKey: string
  returnUrl: string
  onConfirmed: () => void
  onFailed?: () => void
}

export function StripePaymentForm({ orderId, clientSecret, publishableKey, returnUrl, onConfirmed, onFailed }: StripePaymentFormProps) {
  const mountRef = useRef<HTMLDivElement>(null)
  const stripeRef = useRef<{ stripe: StripeInstance; elements: StripeElements } | null>(null)
  const paymentRef = useRef<StripePaymentElement | null>(null)
  const [ready, setReady] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState('')
  const [complete, setComplete] = useState(false)

  useEffect(() => {
    let cancelled = false
    setReady(false)
    setMessage('')
    setComplete(false)

    loadStripe(publishableKey).then((stripe) => {
      if (cancelled || !mountRef.current) return
      const elements = stripe.elements({
        clientSecret,
        appearance: { theme: 'stripe', variables: { colorPrimary: '#166534' } },
      })
      const payment = elements.create('payment', { layout: 'tabs' })
      payment.on('ready', () => { if (!cancelled) setReady(true) })
      payment.on('change', (event) => {
        if (cancelled) return
        setComplete(Boolean(event.complete))
        if (event.error?.message) setMessage(event.error.message)
      })
      payment.mount(mountRef.current)
      stripeRef.current = { stripe, elements }
      paymentRef.current = payment
    }).catch((error: unknown) => {
      if (!cancelled) setMessage(error instanceof Error ? error.message : 'Could not load the payment form.')
    })

    return () => {
      cancelled = true
      paymentRef.current?.destroy()
      paymentRef.current = null
      stripeRef.current = null
    }
  }, [clientSecret, publishableKey])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!stripeRef.current || !complete || submitting) return
    setSubmitting(true)
    setMessage('')
    try {
      const result = await stripeRef.current.stripe.confirmPayment({
        elements: stripeRef.current.elements,
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
      <div ref={mountRef} id={`payment-element-${orderId}`} className="min-h-24 rounded-2xl border bg-card p-4" />
      {!ready && !message ? <p role="status" className="text-sm text-muted-foreground">Loading secure payment form…</p> : null}
      {message ? <p role="alert" className="rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">{message}</p> : null}
      <Button type="submit" size="lg" className="h-12 w-full rounded-full" disabled={!ready || !complete || submitting}>
        {submitting ? 'Confirming payment…' : 'Pay now'}
      </Button>
      <p className="text-xs text-muted-foreground">
        Payment details are handled directly by Stripe. The order remains unpaid if the payment is not confirmed.
      </p>
    </form>
  )
}
