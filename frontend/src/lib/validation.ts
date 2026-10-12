const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export const OTP_LENGTH = 10

export type FieldErrors<T> = Partial<Record<keyof T, string>>

export function validateEmail(value: string): string | undefined {
  const email = value.trim()
  if (!email) return 'Email is required.'
  if (!EMAIL_PATTERN.test(email)) return 'Enter a valid email address.'
}

export function validatePassword(value: string): string | undefined {
  if (!value) return 'Password is required.'
  if (value.length < 8) return 'Password must be at least 8 characters.'
}

export function validateFirstName(value: string): string | undefined {
  const name = value.trim()
  if (!name) return 'First name is required.'
  if (name.length > 100) return 'First name must be 100 characters or fewer.'
}

export function validateLastName(value: string): string | undefined {
  if (value.trim().length > 100) return 'Last name must be 100 characters or fewer.'
}

export function validatePhone(value: string): string | undefined {
  const phone = value.trim()
  if (!phone) return 'Phone is required.'
  if (phone.length < 10 || phone.length > 30) return 'Phone must be between 10 and 30 characters.'
}

export function hasErrors<T>(errors: FieldErrors<T>): boolean {
  return Object.values(errors).some(Boolean)
}
