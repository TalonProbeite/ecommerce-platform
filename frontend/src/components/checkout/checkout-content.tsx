import { Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { FormField } from '@/components/auth/form-field'
import { getEquipmentById } from '@/data/equipment'
import { formatCurrency, formatDate } from '@/lib/pricing'
import { cn } from '@/lib/utils'
import type { FieldErrors } from '@/lib/validation'
import type { CartItem, OrderLine, CheckoutDetails } from '@/types'

export function CheckoutSummary({ subtotal, serviceFee, total }: { subtotal: number; serviceFee: number; total: number }) {
  return <section className="rounded-3xl border bg-card p-6" aria-labelledby="summary-heading">
    <h2 id="summary-heading" className="text-lg font-bold">Order summary</h2>
    <dl className="mt-4 space-y-2 text-sm">
      <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Subtotal</dt><dd>{formatCurrency(subtotal)}</dd></div>
      <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Service fee</dt><dd>{formatCurrency(serviceFee)}</dd></div>
      <div className="flex justify-between gap-3 border-t pt-3 text-base font-bold"><dt>Total</dt><dd>{formatCurrency(total)}</dd></div>
    </dl>
    <p className="mt-4 text-xs text-muted-foreground">No payment is taken in this demo.</p>
  </section>
}

export function CheckoutItems({ items, lines, onRemove }: { items: CartItem[]; lines: OrderLine[]; onRemove?: (id: string) => void }) {
  return <section aria-labelledby="items-heading">
    <h2 id="items-heading" className="text-lg font-bold">Your items</h2>
    <ul className="mt-4 space-y-3">{items.map((item) => {
      const equipment = getEquipmentById(item.equipmentId)
      const line = lines.find((entry) => entry.equipmentId === item.equipmentId)
      return <li key={item.equipmentId} className="flex gap-3 rounded-3xl border bg-card p-4 sm:gap-4">
        {equipment ? <img src={equipment.image} alt="" className="size-16 shrink-0 rounded-2xl object-cover sm:size-24" /> : null}
        <div className="min-w-0 flex-1">
          <h3 className="break-words font-bold">{equipment?.name ?? 'Removed listing'}</h3>
          {line ? <><p className="mt-1 text-sm text-muted-foreground">{formatDate(line.startDate)} &ndash; {formatDate(line.endDate)} &middot; {line.days} {line.days === 1 ? 'day' : 'days'}</p><p className="mt-2 text-sm text-muted-foreground">{formatCurrency(line.pricePerDay)} / day</p><p className="font-bold">{formatCurrency(line.days * line.pricePerDay)}</p></> : <p className="mt-1 text-sm text-destructive">Unavailable item or invalid rental dates.</p>}
        </div>
        {onRemove ? <Button variant="ghost" size="icon" aria-label={`Remove ${equipment?.name ?? 'removed listing'}`} onClick={() => onRemove(item.equipmentId)}><Trash2 className="size-4" /></Button> : null}
      </li>
    })}</ul>
  </section>
}

export function CheckoutSteps({ step }: { step: number }) {
  return <nav aria-label="Checkout progress" className="mt-6"><ol className="grid grid-cols-3 gap-2 sm:gap-4">
    {['Cart', 'Delivery details', 'Confirmation'].map((label, index) => <li key={label} aria-current={index === step ? 'step' : undefined} className={cn('rounded-2xl border px-3 py-3 text-sm font-semibold', index === step ? 'border-primary bg-secondary text-primary' : 'text-muted-foreground')}><span className="mr-2">{index + 1}.</span>{label}</li>)}
  </ol></nav>
}

export function CheckoutDetailsFields({ details, errors, onChange, disabled }: { details: CheckoutDetails; errors: FieldErrors<CheckoutDetails>; disabled: boolean; onChange: (details: CheckoutDetails) => void }) {
  return <fieldset disabled={disabled} className="space-y-6">
    <legend className="mb-4 text-lg font-bold">Delivery details</legend>
    <fieldset><legend className="font-semibold">How do you want to get it?</legend>
      <div className="mt-3 grid gap-3 sm:grid-cols-2">{(['pickup', 'delivery'] as const).map((value) => <label key={value} className={cn('flex cursor-pointer flex-col rounded-3xl border p-5 has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring/50', details.fulfillment === value ? 'border-primary bg-secondary' : 'bg-card hover:bg-accent')}>
        <input className="sr-only" type="radio" name="fulfillment" value={value} checked={details.fulfillment === value} onChange={() => onChange({ ...details, fulfillment: value })} />
        <span className="font-bold">{value === 'pickup' ? 'Pickup' : 'Delivery'}</span><span className="text-sm text-muted-foreground">{value === 'pickup' ? 'Collect from the provider, free' : 'Brought to your address'}</span>
      </label>)}</div>
      {errors.fulfillment ? <p role="alert" className="mt-2 text-sm text-destructive">{errors.fulfillment}</p> : null}
    </fieldset>
    <div className="grid gap-4 sm:grid-cols-2">
      <FormField id="checkout-contact" label="Contact name" autoComplete="name" required maxLength={100} value={details.contactName} error={errors.contactName} onChange={(e) => onChange({ ...details, contactName: e.target.value })} />
      <FormField id="checkout-phone" label="Phone" type="tel" autoComplete="tel" required maxLength={30} value={details.phone} error={errors.phone} onChange={(e) => onChange({ ...details, phone: e.target.value })} />
    </div>
    {details.fulfillment === 'delivery' ? <div className="grid gap-4 sm:grid-cols-2">
      <div className="sm:col-span-2"><FormField id="checkout-address" label="Street address" autoComplete="street-address" required maxLength={200} value={details.address} error={errors.address} onChange={(e) => onChange({ ...details, address: e.target.value })} /></div>
      <FormField id="checkout-city" label="City" autoComplete="address-level2" required maxLength={200} value={details.city} error={errors.city} onChange={(e) => onChange({ ...details, city: e.target.value })} />
      <FormField id="checkout-postal" label="Postal code" autoComplete="postal-code" required maxLength={200} value={details.postalCode} error={errors.postalCode} onChange={(e) => onChange({ ...details, postalCode: e.target.value })} />
    </div> : <p className="text-sm text-muted-foreground">Collect your equipment from the providers shown below.</p>}
  </fieldset>
}

export function CheckoutFeedback({ placing, error, invalid }: { placing: boolean; error: string; invalid: boolean }) {
  return <>
    {invalid ? <p role="alert" className="text-sm text-destructive">Check the highlighted delivery details.</p> : null}
    {error ? <p role="alert" className="rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">{error} Your order was not placed. Review your details and try again.</p> : null}
    {placing ? <p role="status" className="text-sm text-muted-foreground">Placing order… Please wait.</p> : null}
  </>
}
