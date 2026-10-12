import { STAFF_NOTIFICATIONS } from '../data/notifications'
import { buildNotificationQuery } from '../lib/notification-query'
import { NOTIFICATION_CHANNELS, NOTIFICATION_STATUSES } from '../types/notifications'
import type { InternalAxiosRequestConfig } from 'axios'
import type { User } from '../types'

// Only handles documented GET paths; response shape and matching semantics are local fixtures.
export function mockNotifications(config: InternalAxiosRequestConfig, actor: User | undefined) {
  const path = config.url ?? ''
  const base = '/notification/api/private/notifications'
  if ((config.method ?? 'get').toLowerCase() !== 'get' || (path !== base && !path.startsWith(`${base}/`))) return null
  if (!actor) return { status: 401, data: { error: 'Not authenticated.' } }
  if (!['admin', 'analyst'].includes(actor.role) || actor.isBanned) return { status: 403, data: { error: 'Forbidden. Only active admins and analysts can view notifications.' } }
  if (path !== base) {
    let id: string
    try { id = decodeURIComponent(path.slice(base.length + 1)) } catch { return { status: 400, data: { error: 'Invalid notification ID.' } } }
    const item = STAFF_NOTIFICATIONS.find(record => record.id === id)
    return item ? { status: 200, data: item } : { status: 404, data: { error: 'Notification not found' } }
  }
  try {
    const input = config.params instanceof URLSearchParams ? config.params : new URLSearchParams(config.params)
    const query = buildNotificationQuery({
      status: input.get('status') ?? '', channel: input.get('channel') ?? '', recipient: input.get('recipient') ?? '',
      start_date: input.get('start_date') ?? '', end_date: input.get('end_date') ?? '',
      limit: input.has('limit') ? Number(input.get('limit')) : 20, offset: input.has('offset') ? Number(input.get('offset')) : 0,
    })
    const status = query.get('status'), channel = query.get('channel')
    if (status && !NOTIFICATION_STATUSES.some(value => value === status)) throw new Error('Invalid mock status.')
    if (channel && !NOTIFICATION_CHANNELS.some(value => value === channel)) throw new Error('Invalid mock channel.')
    const items = STAFF_NOTIFICATIONS.filter(item =>
      (!status || item.status === status) && (!channel || item.channel === channel) &&
      (!query.has('recipient') || item.recipient.toLowerCase().includes(query.get('recipient')!.toLowerCase())) &&
      (!query.has('start_date') || item.createdAt >= query.get('start_date')!) &&
      (!query.has('end_date') || item.createdAt <= query.get('end_date')!),
    )
    const limit = Number(query.get('limit')), offset = Number(query.get('offset'))
    return { status: 200, data: { items: items.slice(offset, offset + limit), total: items.length, limit, offset } }
  } catch (error) { return { status: 400, data: { error: error instanceof Error ? error.message : 'Invalid filters.' } } }
}
