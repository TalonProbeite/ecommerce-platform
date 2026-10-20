---
title: Per-Request Deduplication with React.cache()
impact: MEDIUM
impactDescription: deduplicates within request
tags: server, cache, react-cache, deduplication
---

## Per-Request Deduplication with React.cache()

Use `React.cache()` for request deduplication within the React Server Components tree. Authentication and database queries benefit most. Calls outside that tree, including Next.js Route Handlers, do not share React's request cache and are not deduplicated by this API.

**Usage:**

```typescript
import { cache } from 'react'

export const getCurrentUser = cache(async () => {
  const session = await auth()
  if (!session?.user?.id) return null
  return await db.user.findUnique({
    where: { id: session.user.id }
  })
})
```

Within a single Server Components render request, multiple calls to the same cached `getCurrentUser()` wrapper execute the query only once.

**Avoid inline objects as arguments:**

`React.cache()` uses shallow equality (`Object.is`) to determine cache hits. Inline objects create new references each call, preventing cache hits.

**Incorrect (always cache miss):**

```typescript
const getUser = cache(async (params: { uid: number }) => {
  return await db.user.findUnique({ where: { id: params.uid } })
})

// Each call creates new object, never hits cache
getUser({ uid: 1 })
getUser({ uid: 1 })  // Cache miss, runs query again
```

**Correct (cache hit):**

```typescript
const getUser = cache(async (uid: number) => {
  return await db.user.findUnique({ where: { id: uid } })
})

// Primitive args use value equality
getUser(1)
getUser(1)  // Cache hit, returns cached result
```

If you must pass objects, pass the same reference:

```typescript
const getUserByParams = cache(async (params: { uid: number }) => {
  return await db.user.findUnique({ where: { id: params.uid } })
})

const params = { uid: 1 }
getUserByParams(params)  // Query runs
getUserByParams(params)  // Cache hit (same reference)
```

**Next.js-Specific Note:**

In Next.js, matching GET/HEAD `fetch` requests within the React component tree are request-memoized during a render. This does not apply to Route Handlers, which are outside that tree, and is separate from persistent data caching. Use `React.cache()` for other repeated work in the Server Components tree:

- Database queries (Prisma, Drizzle, etc.)
- Heavy computations
- Authentication checks
- File system operations
- Any non-fetch async work

Use `React.cache()` to deduplicate these operations across your component tree.

Reference: [React.cache documentation](https://react.dev/reference/react/cache)
