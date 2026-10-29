export type Role = 'customer' | 'analyst' | 'admin' | 'store_manager'

export interface UpdateRolePayload { role: Role }
export interface UpdateBanPayload { is_banned: boolean }
export interface UpdateStorePayload { store_id?: string | null }

export interface User {
  id?: string
  email: string
  firstName: string
  lastName?: string
  phone?: string
  role?: Role
  storeId?: string | null
  emailVerified?: boolean
  isOAuth?: boolean
  isBanned?: boolean
}

export type CategoryId = 'cameras' | 'audio-video' | 'tools' | 'outdoor' | 'mobility'

export interface Category {
  id: CategoryId
  label: string
  description: string
}

export type ListingStatus = 'active' | 'paused' | 'draft'

export interface Equipment {
  id: string
  name: string
  category: CategoryId
  image: string
  pricePerDay: number
  deposit: number
  rating: number
  reviewCount: number
  location: string
  provider: string
  providerVerified: boolean
  description: string
  features: string[]
  includes: string[]
  featured: boolean
  status: ListingStatus
  totalRentals: number
}

export interface CartItem {
  equipmentId: string
  startDate: string
  endDate: string
}

export type OrderStatus = 'confirmed' | 'active' | 'returned' | 'cancelled'
export type Fulfillment = 'pickup' | 'delivery'

export interface OrderLine extends CartItem {
  days: number
  pricePerDay: number
}

// Local mock business model; not a backend request contract.
export interface CheckoutDetails {
  fulfillment: Fulfillment
  contactName: string
  phone: string
  address: string
  city: string
  postalCode: string
}

export interface Order {
  id: string
  userId: string
  lines: OrderLine[]
  subtotal: number
  serviceFee: number
  total: number
  status: OrderStatus
  fulfillment: Fulfillment
  createdAt: string
  checkoutDetails?: CheckoutDetails
}

export interface LoginPayload {
  email: string
  password: string
}

export interface ChangePasswordPayload {
  old_pass: string
  new_pass: string
}

export interface RegisterPayload extends LoginPayload {
  firstName: string
  lastName?: string
  phone: string
}

export interface VerifyEmailPayload {
  email: string
  code: string
}

export interface ProfileChanges {
  email?: string
  firstName: string
  lastName?: string
  phone?: string
}

export type NotificationKind = 'order' | 'system' | 'alert'

export interface StaffNotification {
  id: string
  kind: NotificationKind
  title: string
  body: string
  createdAt: string
}
