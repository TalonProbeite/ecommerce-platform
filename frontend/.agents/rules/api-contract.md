---
trigger: model_decision
description: "Use when implementing or reviewing authentication, fetch services, profile, admin, or notification API integration in Rentify."
---
# Rentify API Contract Rules

Read `docs/RENTIFY_MASTER_SPEC.md` before implementing backend integration. Never invent undocumented endpoints.

## Auth
- Auth is cookie-based with httpOnly cookies. Never read/write auth cookies in browser JavaScript.
- Fetch requests use `credentials: 'include'`.
- Centralize the fetch client and refresh handling.
- On protected-request 401: make exactly one refresh attempt, retry the original request on success, otherwise clear client auth state and redirect to `/login`. Avoid loops.
- OAuth callback uses backend redirects to `/oauth/success` or `/register/complete`. Read pending fields using GET `/auth/api/oauth/profile`; submit POST `/auth/api/oauth/complete`. Never put the key or Google data into URLs.
- `UserProfile.is_oauth` maps to `User.isOAuth`; pending profiles are not authenticated Users.
- Google OAuth starts with full browser navigation to the backend OAuth URL; do not call it via fetch.

## Business APIs
- Use mocks where the master spec says business data is not finalized.
- Put HTTP calls in reusable service modules.
- Do not send empty query parameters.
- Notification pagination uses `limit`/`offset`; changing filters resets offset to 0.
- Dates for notification filters use `date.toISOString()`.

## Access control
- customer: customer area only.
- analyst: staff notification area.
- admin: admin + notification areas.
- Hide staff navigation from customers and enforce route protection in code.
