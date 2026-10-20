---
title: Use Lazy State Initialization
impact: MEDIUM
impactDescription: wasted computation on every render
tags: react, hooks, useState, performance, initialization
---

## Use Lazy State Initialization

Pass a function to `useState` for expensive initial values. Without the function form, the initializer runs on every render even though the value is only used once.

**Incorrect (runs on every render):**

```tsx
function FilteredList({ items }: { items: Item[] }) {
  // buildSearchIndex() runs on EVERY render, even after initialization
  const [searchIndex, setSearchIndex] = useState(buildSearchIndex(items))
  const [query, setQuery] = useState('')
  
  // When query changes, buildSearchIndex runs again unnecessarily
  return <SearchResults index={searchIndex} query={query} />
}

function UserProfile() {
  // JSON.parse runs on every render
  const [settings, setSettings] = useState(
    JSON.parse(localStorage.getItem('settings') || '{}')
  )
  
  return <SettingsForm settings={settings} onChange={setSettings} />
}
```

**Correct (memoize prop-derived data; lazily initialize independent state):**

```tsx
function FilteredList({ items }: { items: Item[] }) {
  // Recompute when items change, not when only the query changes.
  const searchIndex = useMemo(() => buildSearchIndex(items), [items])
  const [query, setQuery] = useState('')
  
  return <SearchResults index={searchIndex} query={query} />
}

function UserProfile() {
  // Client-only initialization; guard SSR and unavailable or invalid storage.
  const [settings, setSettings] = useState(() => {
    if (typeof window === 'undefined') return {}
    try {
      const stored = localStorage.getItem('settings')
      return stored ? JSON.parse(stored) : {}
    } catch {
      return {}
    }
  })
  
  return <SettingsForm settings={settings} onChange={setSettings} />
}
```

Use lazy initialization for independent initial state. An initializer is used on mount, not when props change; React Strict Mode may invoke it twice in development to check purity. Derive indexes from changing props with `useMemo` instead.

The storage example is suitable for client-only rendering. For SSR hydration, initialize with the same default on the server and client, then read storage in an Effect; a `typeof window` guard alone does not prevent hydration mismatches.

For simple primitives (`useState(0)`), direct references (`useState(props.value)`), or cheap literals (`useState({})`), the function form is unnecessary.
