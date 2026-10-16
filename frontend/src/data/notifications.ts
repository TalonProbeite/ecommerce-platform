import { NOTIFICATION_STATUSES, type NotificationRecord } from '../types/notifications'

// Deterministic fixtures matching the notification EventLog contract.
export const STAFF_NOTIFICATIONS: NotificationRecord[] = Array.from({ length: 53 }, (_, index) => {
  const channel = 'email'
  const status = NOTIFICATION_STATUSES[index % 2]
  const type = ['order.confirmed', 'order.paid', 'order.cancelled'][index % 3]
  return {
    id: `n-${index + 1}`,
    recipient: ['maya.chen@example.com', 'jordan.lee@example.com', 'sam.patel@example.com'][index % 3],
    status, channel,
    created_at: new Date(Date.UTC(2026, 9, 9, 10) - index * 6 * 3600000).toISOString(),
    type,
    attempts: status === 'failed' ? 3 : 1,
    payload: { order_id: String(1042 + index), email: ['maya.chen@example.com', 'jordan.lee@example.com', 'sam.patel@example.com'][index % 3], ...(type === 'order.paid' ? { amount: 125 } : {}), ...(type === 'order.cancelled' ? { reason: 'Customer request' } : {}) },
    ...(status === 'failed' ? { error: 'Recipient mailbox unavailable.' } : {}),
  }
})
