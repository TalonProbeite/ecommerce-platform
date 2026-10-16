export const SERVICE_FEE_RATE = 0.08

const wholeCurrency = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  maximumFractionDigits: 0,
})

const preciseCurrency = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  minimumFractionDigits: 2,
})

export function formatCurrency(value: number): string {
  return Number.isInteger(value) ? wholeCurrency.format(value) : preciseCurrency.format(value)
}

function toLocalISO(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

export function todayISO(): string {
  return toLocalISO(new Date())
}

export function addDaysISO(iso: string, days: number): string {
  const date = new Date(`${iso}T00:00:00`)
  date.setDate(date.getDate() + days)
  return toLocalISO(date)
}

export function formatDate(iso: string): string {
  return new Date(`${iso}T00:00:00`).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

export function rentalDays(startDate: string, endDate: string): number {
  if (!startDate || !endDate) return 0
  const start = new Date(`${startDate}T00:00:00`).getTime()
  const end = new Date(`${endDate}T00:00:00`).getTime()
  if (Number.isNaN(start) || Number.isNaN(end) || end < start) return 0
  return Math.round((end - start) / 86_400_000) + 1
}

export function calculateTotals(subtotal: number) {
  const serviceFee = Math.round(subtotal * SERVICE_FEE_RATE * 100) / 100
  return { subtotal, serviceFee, total: subtotal + serviceFee }
}
