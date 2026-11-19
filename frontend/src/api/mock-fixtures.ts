import type { User } from '@/types'

export interface MockAccount extends User {
  password?: string
  verified?: boolean
  isOAuth?: boolean
}

export const DEMO_VERIFICATION_CODE = '1234567890'
export const EXPIRED_VERIFICATION_CODE = '0000000000'

export const DEMO_ACCOUNTS: Array<{ label: string; email: string; password?: string; isOAuth?: boolean }> = [
  { label: 'Customer', email: 'user@rentify.dev', password: 'password123' },
  { label: 'Google Account', email: 'google.user@rentify.dev', isOAuth: true },
  { label: 'Unverified User', email: 'unverified@rentify.dev', password: 'password123' },
  { label: 'Admin', email: 'admin@rentify.dev', password: 'password123' },
  { label: 'Analyst', email: 'analyst@rentify.dev', password: 'password123' },
]

export const MOCK_ACCOUNTS: MockAccount[] = [
  { id: 'u-user', firstName: 'Maya', lastName: 'Chen', email: 'user@rentify.dev', phone: '+1 555 0142 000', role: 'customer', password: 'password123', verified: true, isOAuth: false },
  { id: 'u-google', firstName: 'Jordan', lastName: 'Lee', email: 'google.user@rentify.dev', phone: '+1 555 0199 888', role: 'customer', verified: true, isOAuth: true },
  { id: 'u-unverified', firstName: 'Taylor', lastName: 'Swift', email: 'unverified@rentify.dev', phone: '+1 555 0188 777', role: 'customer', password: 'password123', verified: false, isOAuth: false },
  { id: 'u-admin', firstName: 'Alex', lastName: 'Rivera', email: 'admin@rentify.dev', role: 'admin', password: 'password123', verified: true, isOAuth: false },
  { id: 'u-analyst', firstName: 'Sam', lastName: 'Patel', email: 'analyst@rentify.dev', role: 'analyst', password: 'password123', verified: true, isOAuth: false },
]

// Local fixture references, not an undocumented users-list API.
export const ADMIN_MOCK_TARGETS = MOCK_ACCOUNTS.map(({ id, email }) => ({ id, email }))
