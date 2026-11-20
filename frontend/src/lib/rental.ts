const HOUR_MS = 3_600_000
const MINUTE_MS = 60_000

export const MIN_RENTAL_MS = HOUR_MS

export function rentalDurationMs(startIso: string, endIso: string): number {
  const start = Date.parse(startIso)
  const end = Date.parse(endIso)
  if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) return 0
  return end - start
}

export function hasMinimumRentalDuration(startIso: string, endIso: string): boolean {
  return rentalDurationMs(startIso, endIso) >= MIN_RENTAL_MS
}

export function billedHours(startIso: string, endIso: string): number {
  const duration = rentalDurationMs(startIso, endIso)
  if (duration === 0) return 0
  return Math.max(1, Math.ceil(duration / HOUR_MS))
}

export function toDateTimeInput(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export function localInputToIso(value: string): string | null {
  if (!value) return null
  const date = new Date(value)
  return Number.isFinite(date.getTime()) ? date.toISOString() : null
}

export function isoToDateTimeInput(value: string): string {
  const date = new Date(value)
  return Number.isFinite(date.getTime()) ? toDateTimeInput(date) : ''
}

export function defaultRentalStart(now = new Date()): string {
  const date = new Date(now.getTime() + MINUTE_MS)
  date.setSeconds(0, 0)
  return toDateTimeInput(date)
}

export function defaultRentalEnd(startInput: string): string {
  const start = new Date(startInput)
  const fallback = new Date()
  const date = Number.isFinite(start.getTime()) ? new Date(start.getTime() + 2 * HOUR_MS) : new Date(fallback.getTime() + 2 * HOUR_MS)
  date.setSeconds(0, 0)
  return toDateTimeInput(date)
}

export function isValidRentalRange(startIso: string, endIso: string, now = new Date()): boolean {
  const start = Date.parse(startIso)
  const end = Date.parse(endIso)
  return Number.isFinite(start)
    && Number.isFinite(end)
    && start >= now.getTime()
    && end > start
    && end - start >= MIN_RENTAL_MS
}

export function formatDateTime(iso: string): string {
  const date = new Date(iso)
  if (!Number.isFinite(date.getTime())) return iso
  return date.toLocaleString('en-IE', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
