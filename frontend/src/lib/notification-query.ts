import type { NotificationFilters } from '../types/notifications'

export function buildNotificationQuery(filters: NotificationFilters): URLSearchParams {
  const limit = filters.limit ?? 20
  const offset = filters.offset ?? 0
  if (!Number.isInteger(limit) || limit < 0 || limit > 100) throw new Error('Page size must be an integer between 0 and 100.')
  if (!Number.isInteger(offset) || offset < 0) throw new Error('Offset must be a non-negative integer.')
  const query = new URLSearchParams()
  for (const key of ['status', 'channel', 'recipient'] as const) {
    const value = filters[key]?.trim()
    if (value) query.set(key, value)
  }
  for (const key of ['start_date', 'end_date'] as const) {
    const value = filters[key]?.trim()
    if (!value) continue
    const date = new Date(value)
    if (!Number.isFinite(date.getTime())) throw new Error('Enter valid start and end dates.')
    query.set(key, date.toISOString())
  }
  if (query.has('start_date') && query.has('end_date') && query.get('start_date')! > query.get('end_date')!) {
    throw new Error('Start date must be before or equal to end date.')
  }
  query.set('limit', String(limit))
  query.set('offset', String(offset))
  return query
}

export function changeNotificationFilters(current: NotificationFilters, changes: NotificationFilters): NotificationFilters {
  return { ...current, ...changes, offset: 0 }
}
