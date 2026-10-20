---
title: Cache Storage API Calls
impact: LOW-MEDIUM
impactDescription: reduces expensive I/O
tags: javascript, localStorage, storage, caching, performance
---

## Cache Storage API Calls

`localStorage`, `sessionStorage`, and `document.cookie` are synchronous and expensive. Cache reads in memory.

**Incorrect (reads storage on every call):**

```typescript
function getTheme() {
  return localStorage.getItem('theme') ?? 'light'
}
// Called 10 times = 10 storage reads
```

**Correct (Map cache):**

```typescript
const storageCache = new Map<string, string | null>()

function getLocalStorage(key: string): string | null {
  if (!storageCache.has(key)) {
    try {
      storageCache.set(key, localStorage.getItem(key))
    } catch {
      // Do not cache failures; a later read may succeed.
      return null
    }
  }
  return storageCache.get(key) ?? null
}

function setLocalStorage(key: string, value: string): boolean {
  try {
    localStorage.setItem(key, value)
    storageCache.set(key, value)  // update only after persistence succeeds
    return true
  } catch {
    storageCache.delete(key)
    return false
  }
}
```

Use a Map (not a hook) so it works everywhere: utilities, event handlers, not just React components.

**Cookie caching:**

```typescript
let cookieCache: Record<string, string> | null = null

function getCookie(name: string) {
  if (!cookieCache) {
    cookieCache = Object.fromEntries(
      document.cookie.split(';').flatMap(part => {
        const cookie = part.trim()
        const separator = cookie.indexOf('=')
        if (separator <= 0) return []
        return [[cookie.slice(0, separator), cookie.slice(separator + 1)]]
      })
    )
  }
  return cookieCache[name]
}
```

**Important (invalidate on external changes):**

If storage can change externally (another tab, server-set cookies), invalidate cache:

```typescript
window.addEventListener('storage', (e) => {
  if (e.key === null) {
    storageCache.clear() // another tab called localStorage.clear()
  } else {
    storageCache.delete(e.key)
  }
})

document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') {
    storageCache.clear()
    cookieCache = null
  }
})
```

Register these listeners only in the browser. The `storage` event is not emitted in the tab that made the change: route same-tab writes through the helpers, and explicitly clear the cache after same-tab `localStorage.clear()`. Reset `cookieCache` after known cookie changes; cookies have no equivalent `storage` event, so visibility-based invalidation alone cannot guarantee freshness.
