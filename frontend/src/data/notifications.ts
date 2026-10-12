import { NOTIFICATION_STATUSES, type NotificationRecord } from '../types/notifications'

// Deterministic delivery journal fixtures; all schema choices are mock-only.
export const STAFF_NOTIFICATIONS: NotificationRecord[] = Array.from({ length: 53 }, (_, index) => {
  const channel = index % 2 === 0 ? 'email' : 'sms'
  const status = NOTIFICATION_STATUSES[index % 3]
  const title = ['Rental confirmed', 'Return reminder', 'Rental cancelled', 'Weekly report ready'][index % 4]
  return {
    id: `n-${index + 1}`,
    recipient: channel === 'email' ? ['maya.chen@example.com', 'jordan.lee@example.com', 'sam.patel@example.com'][index % 3] : `+1555014${String(index % 8).padStart(4, '0')}`,
    status, channel,
    createdAt: new Date(Date.UTC(2026, 9, 9, 10) - index * 6 * 3600000).toISOString(),
    title,
    body: `${title} for rental #${1042 + index}. Your rental information is available in your account.`,
    ...(status === 'failed' ? { error: channel === 'email' ? 'Recipient mailbox unavailable.' : 'Delivery provider could not reach the recipient.' } : {}),
  }
})
