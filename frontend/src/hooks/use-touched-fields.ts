import { useState } from 'react'

export function useTouchedFields() {
  const [touched, setTouched] = useState<Record<string, boolean>>({})
  const [allTouched, setAllTouched] = useState(false)

  return {
    touch: (name: string) => setTouched((current) => ({ ...current, [name]: true })),
    touchAll: () => setAllTouched(true),
    isTouched: (name: string) => allTouched || Boolean(touched[name]),
  }
}
