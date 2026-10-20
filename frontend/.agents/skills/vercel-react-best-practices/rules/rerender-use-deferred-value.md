---
title: Use useDeferredValue for Expensive Derived Renders
impact: MEDIUM
impactDescription: keeps input responsive during heavy computation
tags: rerender, useDeferredValue, optimization, concurrent
---

## Use useDeferredValue for Expensive Derived Renders

When user input triggers expensive computations or renders, use `useDeferredValue` to keep the input responsive. The deferred value lags behind, allowing React to prioritize the input update and render the expensive result when idle.

**Incorrect (input feels laggy while filtering):**

```tsx
function Search({ items }: { items: Item[] }) {
  const [query, setQuery] = useState('')
  const filtered = items.filter(item => fuzzyMatch(item, query))

  return (
    <>
      <input value={query} onChange={e => setQuery(e.target.value)} />
      <ResultsList results={filtered} />
    </>
  )
}
```

**Correct (input stays snappy, results render when ready):**

```tsx
import { memo, useDeferredValue, useMemo, useState } from 'react'

// Skip urgent renders while the memoized filtered array is unchanged.
const DeferredResultsList = memo(ResultsList)

function Search({ items }: { items: Item[] }) {
  const [query, setQuery] = useState('')
  const deferredQuery = useDeferredValue(query)
  const filtered = useMemo(
    () => items.filter(item => fuzzyMatch(item, deferredQuery)),
    [items, deferredQuery]
  )
  const isStale = query !== deferredQuery

  return (
    <>
      <input value={query} onChange={e => setQuery(e.target.value)} />
      <div style={{ opacity: isStale ? 0.7 : 1 }}>
        <DeferredResultsList results={filtered} />
      </div>
    </>
  )
}
```

**When to use:**

- Filtering/searching large lists
- Expensive visualizations (charts, graphs) reacting to input
- Any derived state that causes noticeable render delays

**Note:** Keep `items` stable between query updates and memoize the expensive list component as well as the filtered array. `useMemo` reuses the array while the deferred query is unchanged, and `memo` lets the list skip the urgent parent render. Deferring a value does not itself memoize child components.

Reference: [React useDeferredValue](https://react.dev/reference/react/useDeferredValue)
