export interface StripePaymentElement {
  mount(target: HTMLElement | string): void
  destroy(): void
  on(event: 'ready' | 'change' | 'focus' | 'blur', handler: (event: { complete?: boolean; error?: { message?: string } }) => void): void
}

export interface StripeElements {
  create(type: 'payment', options?: Record<string, unknown>): StripePaymentElement
}

export interface StripeInstance {
  elements(options: { clientSecret: string; appearance?: Record<string, unknown> }): StripeElements
  confirmPayment(options: {
    elements: StripeElements
    confirmParams: { return_url: string }
    redirect: 'if_required'
  }): Promise<{ error?: { message?: string }; paymentIntent?: { status: string } }>
}

declare global {
  interface Window {
    Stripe?: (publishableKey: string) => StripeInstance
  }
}

const SCRIPT_ID = 'stripe-js'
const SCRIPT_URL = 'https://js.stripe.com/v3/'
let loader: Promise<StripeInstance> | null = null

function existingScript(): HTMLScriptElement | null {
  return document.querySelector<HTMLScriptElement>(`script[src="${SCRIPT_URL}"]`)
}

export function loadStripe(publishableKey: string): Promise<StripeInstance> {
  if (!publishableKey) return Promise.reject(new Error('Stripe is not configured for this environment.'))
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return Promise.reject(new Error('Stripe can only load in a browser.'))
  }
  if (window.Stripe) return Promise.resolve(window.Stripe(publishableKey))
  if (loader) return loader

  loader = new Promise<StripeInstance>((resolve, reject) => {
    const finish = () => {
      if (!window.Stripe) {
        reject(new Error('Stripe.js did not initialise.'))
        return
      }
      resolve(window.Stripe(publishableKey))
    }

    const script = existingScript()
    if (script) {
      if (script.dataset.loaded === 'true') finish()
      else {
        script.addEventListener('load', () => { script.dataset.loaded = 'true'; finish() }, { once: true })
        script.addEventListener('error', () => reject(new Error('Could not load Stripe.js.')), { once: true })
      }
      return
    }

    const element = document.createElement('script')
    element.id = SCRIPT_ID
    element.src = SCRIPT_URL
    element.async = true
    element.addEventListener('load', () => { element.dataset.loaded = 'true'; finish() }, { once: true })
    element.addEventListener('error', () => reject(new Error('Could not load Stripe.js.')), { once: true })
    document.head.appendChild(element)
  }).catch((error) => {
    loader = null
    throw error
  })

  return loader
}
