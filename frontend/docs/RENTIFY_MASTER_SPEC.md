# Rentify — Frontend Master Prompt

## 1. Role

You are an expert Frontend Developer and UI/UX Designer.

Build the frontend for **Rentify**, a modern equipment rental marketplace.

The goal is to create a frontend that looks like a real, polished product rather than a generic CRUD application, while keeping the codebase simple, understandable, and easy to maintain.

The project must be structured so that it can be developed further manually after the initial generation.

---

# 2. Product

## Product name

**Rentify**

## Tagline

**Rentify — get the gear you need, when you need it.**

## Product concept

Rentify is a marketplace for renting equipment.

Users should not need to buy expensive equipment for a single task. They can:

1. Find the equipment they need.
2. Browse available items.
3. Select rental dates.
4. Add equipment to a checkout flow.
5. Complete the rental process.
6. Manage their profile and order history.

The marketplace may contain equipment supplied by multiple companies and private providers, each with their own inventory.

The current frontend should use **mock business data** for marketplace/catalog/order data unless a real API is explicitly specified below.

---

# 3. Product Personality

Rentify should feel like a combination of:

* premium marketplace
* modern startup
* lifestyle product

It should NOT feel like:

* enterprise software
* an internal corporate dashboard
* a generic CRUD application
* a developer admin panel

The main emotional impression should be:

> “This is a place where I can quickly find great equipment and easily rent it.”

The product should feel:

**Energetic. Premium. Confident. Friendly.**

---

# 4. Visual Direction

Create a modern, premium, light interface.

## Core visual principles

* Light background.
* Generous whitespace.
* Large, expressive equipment imagery.
* Strong modern typography.
* Soft rounded corners.
* Subtle shadows.
* Strong visual hierarchy.
* Large and clear CTAs.
* Vibrant accent color.
* Small gradients only where they improve the design.
* Clean layouts with enough breathing room.
* Slightly playful but still professional components.

Use a **neutral base palette with one strong accent color**.

Possible accent directions:

* electric blue
* violet
* lime
* coral

Choose one coherent accent palette and use it consistently for:

* primary buttons
* active navigation
* highlights
* important states
* selected controls
* key interaction points

Do not randomly introduce many accent colors.

## Avoid

* boring enterprise UI
* excessive gray/blue corporate styling
* excessive glassmorphism
* excessive gradients
* decorative visual noise
* overly complex dashboards
* cartoonish components
* unnecessarily dense layouts
* visual inconsistency
* excessive animation

The design should look polished and modern, but it should remain practical and maintainable.

---

# 5. Technology Stack

Use exactly this frontend stack:

* **React**
* **Vite**
* **TypeScript**
* **Tailwind CSS**
* **shadcn/ui**
* **Lucide React**
* **react-router-dom**
* **Zustand**
* **Native Fetch API**

Do not switch to Vue, Next.js, Angular, or another frontend framework.

Do not introduce unnecessary dependencies.

Prefer the existing stack and native browser functionality whenever possible.

---

# 6. Architecture Principles

The application must remain easy to maintain.

## Components

Use reusable components instead of duplicating UI.

Prefer shadcn/ui components whenever an appropriate component already exists.

Do not recreate existing shadcn components from scratch unless there is a strong reason.

Use Lucide React for icons.

Create reusable components for repeated product concepts such as:

* buttons
* cards
* inputs
* dialogs
* forms
* tables
* badges
* alerts
* navigation
* page headers
* empty states
* loading states

## Routing

Use `react-router-dom`.

Keep route definitions centralized and easy to modify.

Do not hardcode route paths across many unrelated components.

Use protected routes for authenticated areas.

Use role-based route protection for admin and analyst functionality.

## State management

Use Zustand for global application state.

Global state should include authentication-related information such as:

* current user profile
* authentication status
* role

Do not put every local UI state into Zustand.

Keep local component state local unless it genuinely needs to be shared globally.

## API client

Use the native Fetch API.

Create a centralized fetch client for all API requests.

All API requests must use credentials because authentication is handled through httpOnly cookies.

Configure:

`credentials: 'include'`

globally.

Implement authentication refresh handling in one place in the centralized fetch client.

---

# 7. Authentication Behavior

Authentication uses **httpOnly cookies**.

The frontend must NOT read, create, or manipulate authentication cookies using JavaScript.

Cookies are managed by the backend.

The frontend must use credentials on API requests.

Available authentication cookies:

* `access_token` — 15 minutes
* `refresh_token` — 7 days

When a protected API request receives `401`:

1. Make exactly one refresh request.
2. If refresh succeeds, retry the original request.
3. If refresh fails, clear authentication state.
4. Redirect the user to `/login`.

Avoid infinite refresh loops.

---

# 8. Application Zones

The application consists of three major areas:

## Public

Authentication and account entry screens.

## Customer / authenticated user

Marketplace and account functionality.

## Staff / administration

Administrative and notification functionality restricted by role.

Roles:

* `customer`
* `analyst`
* `admin`

---

# 9. Navigation

Use a clean, consistent application shell.

Authenticated application pages should share a consistent navigation structure.

The exact navigation should depend on the user's role.

### Customer

Show customer-oriented navigation such as:

* Catalog
* Profile

Additional checkout pages should be part of the user flow but do not need to appear as permanent navigation items.

### Analyst

Show staff navigation such as:

* Notifications

### Admin

Show:

* Admin
* Notifications

Do not expose staff-only navigation to customers.

---

# 10. Routes and Pages

## PUBLIC ROUTES

### `/login`

Purpose:

Allow the user to authenticate using email and password or Google OAuth.

UI:

* email field
* password field
* primary Login button
* Login with Google button
* link to registration
* validation errors
* loading state
* API error state

Google login must use a full browser navigation:

`window.location.href = "https://localhost/auth/api/oauth/google"`

Do not call this endpoint using fetch.

---

### `/register`

Purpose:

Create an account.

Fields:

* email
* password
* repeat password
* first name
* last name
* phone

The current backend requires:

* email: valid email
* password: minimum 8 characters
* first name: required, maximum 100 characters
* last name: optional, maximum 100 characters
* phone: required, 10–30 characters

Include:

* registration button
* Google registration/login button
* validation messages
* loading state
* API error state
* link back to login

---

### `/verify-email`

Purpose:

Verify the user's email address.

UI:

* OTP input
* verification button
* resend-code action
* loading state
* invalid-code state
* expired-code state
* success state

The verification code is exactly 10 characters.

---

# 11. CUSTOMER AREA

## `/catalog`

Purpose:

Main equipment marketplace.

Use mock product data for now.

The page should feel like the most visually important part of the product.

Include:

* page title / marketplace introduction
* search area
* category or filtering controls where useful
* responsive product grid
* premium equipment cards
* product image
* product name
* short description
* pricing information
* relevant availability / metadata
* clear rental CTA

Use visually strong imagery.

The catalog should feel like a real consumer marketplace rather than an admin table.

---

# 12. Checkout

## `/checkout/*`

Build a multi-step checkout flow.

The current flow is:

1. cart
2. delivery details
3. confirmation / success

The UI should make the current step obvious.

Include:

* order summary
* selected items
* rental information
* delivery details
* primary and secondary actions
* validation states
* success screen

The current product does not require a real checkout backend yet.

Use mock data and keep the architecture easy to connect to a real API later.

---

# 13. USER PROFILE

## `/profile`

The profile area should allow users to:

* view account information
* view order history
* see email verification status
* verify their email if needed
* log out
* update profile information
* change password

### Profile information

Support:

* email
* first name
* last name
* phone

Email must be read-only for OAuth accounts because the backend does not allow changing it for OAuth users.

### Email verification warning

When the email is not verified, display a clear but non-annoying warning with an action leading to email verification.

### Order history

Use mock data for now.

Create a clean order history section that can later be connected to a backend.

### Password change

Provide:

* old password
* new password

Backend requires a minimum of 8 characters.

After a successful password change, the backend terminates the session and clears authentication cookies.

The frontend must redirect the user to `/login`.

---

# 14. ADMIN AREA

## `/admin`

This page is available only to users with the `admin` role.

The current purpose of the page is a basic administration / testing dashboard.

It should contain clear UI controls for triggering test requests to the available administrative API endpoints.

Use a clean staff dashboard layout.

The interface should make it obvious:

* what action is being triggered
* which entity is affected
* what parameters are required
* whether the action succeeded
* whether the action failed

Do not overbuild this into a full enterprise admin product.

---

# 15. NOTIFICATION AREA

## `/notifications`

This area is available only to:

* `admin`
* `analyst`

Customers must not see this navigation item or access the page.

Purpose:

Display the history and analytics journal of notifications sent by the backend.

The frontend only reads this journal.

There is no frontend HTTP endpoint for sending notifications.

---

## Notification list

Display a table with:

* notification status
* delivery channel
* recipient
* timestamp/date information
* relevant identifying data
* row interaction for details

Provide filters:

* status
* channel
* recipient
* start date
* end date

Provide pagination using:

* `limit`
* `offset`

Recommended page size:

`20`

Maximum page size:

`100`

When filters change, reset pagination to:

`offset = 0`

Do not send empty query parameters.

---

## Notification details

Clicking a notification row should open a dedicated details screen or a modal.

The details should use:

`GET /api/private/notifications/:id`

If the list response already contains all required information, it may reuse the existing data instead of making another request.

Show a clear “Notification not found” state for a `404`.

---

# 16. UX STATES

Every important data-driven screen should account for:

* loading
* success
* empty
* validation error
* API error
* unauthorized
* forbidden
* not found

Do not leave empty areas without feedback.

Use consistent loading indicators, empty-state messaging, and error presentation.

---

# 17. Responsive Design

The frontend must work well on:

* desktop
* tablet
* mobile

Do not create a completely separate mobile application.

Use responsive layouts and reusable responsive components.

The catalog and important marketplace content should remain usable on small screens.

---

# 18. API INTEGRATION RULE

For the first UI generation, prioritize the visual frontend and use mock data for business entities where an exact API is not provided.

Do not invent backend endpoints.

Keep API communication behind reusable service / client functions so that mock data can later be replaced with real API calls without rewriting the UI.

The exact backend contracts are defined below.

---

# 19. AUTH SERVICE

## Base URL

`https://localhost/auth/api`

The `/auth` prefix is added by nginx routing.

All frontend requests must use the `/auth` prefix.

Example:

`https://localhost/auth/api/login`

---

## General rules

Request and response format:

`application/json`

Maximum request body:

`1 MB`

Authentication uses httpOnly cookies.

The frontend must never read or write authentication cookies directly.

Every request must use credentials.

Fetch:

`credentials: 'include'`

---

## Success and error format

Error:

```json
{"error": "text"}
```

Success without data:

```json
{"message": "text"}
```

Common status codes:

* `400` — invalid JSON or invalid request format
* `422` — validation error
* `401` — missing or invalid authentication
* `403` — insufficient permissions or inactive account
* `500` — server error

---

# 20. AUTH TOKEN REFRESH

When a private request receives `401`:

1. Call:

`POST /auth/api/refresh`

2. If successful:
   retry the original request.

3. If the retry fails with `401`:
   clear authentication state and redirect to `/login`.

Use centralized fetch refresh handling.

Do not create refresh loops.

---

# 21. AUTH ROUTES

## POST `/auth/api/register`

Request:

* `email`: string, required, valid email
* `password`: string, required, minimum 8 characters
* `first_name`: string, required, maximum 100
* `last_name`: string, optional, maximum 100
* `phone`: string, required, 10–30 characters

Success:

`201`

```json
{
  "email": "user@example.com",
  "first_name": "User",
  "last_name": "",
  "phone": "+48 123456789",
  "role": "customer",
  "is_verified": false,
  "is_oauth": false
}
```

The user is immediately authenticated and authentication cookies are set. The response body is the UserProfile returned by GET profile. Registration requests a welcome email, without generating a verification code or requiring confirmation.

Possible errors:

* `400`
* `409`
* `422`
* `500`

`409`:

```json
{"error": "email already in use"}
```

---

## POST `/auth/api/login`

Request:

* `email`
* `password`

Success:

`200`

```json
{
  "email": "user@example.com",
  "first_name": "User",
  "last_name": "",
  "phone": "+48 123456789",
  "role": "customer",
  "is_verified": false,
  "is_oauth": false
}
```

Errors:

* `401`
* `403`
* `400`
* `422`
* `500`

Invalid credentials:

```json
{"error": "invalid email or password"}
```

---

## POST `/auth/api/logout`

No request body.

Works even without tokens.

Success:

```json
{"message": "successfully logged out"}
```

The server clears authentication cookies.

The frontend should clear local authentication state after logout.

---

## POST `/auth/api/refresh`

No request body.

Uses `refresh_token` cookie.

Success:

`200`

```json
{"message": "successfully refreshed"}
```

Failure:

`401`

Then redirect to login.

---

# 22. GOOGLE OAUTH

## GET `/auth/api/oauth/google`

This must be opened using full browser navigation.

Use:

```text
window.location.href = "https://localhost/auth/api/oauth/google"
```

Do not call it using fetch.

The backend redirects the browser to Google.

---

## GET `/auth/api/oauth/google/callback`

Google redirects to this backend callback. The frontend never calls it directly.
The backend verifies a browser-bound, single-use `state`, PKCE S256 and the ID
token's signature, issuer, audience, expiry and transaction `nonce`.

Success returns **303 See Other**, after setting HttpOnly Secure cookies:

* Existing active user: auth cookies, redirect to frontend `/oauth/success`.
* New user: `profile_key` cookie (15 minutes), redirect to `/register/complete`.
* Failure: redirect to `/login?oauth_error=<safe-code>` (`oauth_canceled`,
  `oauth_invalid_state`, `account_inactive`, or `oauth_failed`).

`FRONTEND_URL` configures a trusted HTTPS origin without a path, query or
fragment. `REDIRECT_URL` remains the Google backend callback URI. Both SPA
routes are outside `/auth/`, which nginx reserves for backend requests.
The temporary `oauth_state` cookie is HttpOnly Secure SameSite=Lax so it is
sent on Google's top-level GET callback. Auth/profile cookies remain Strict.
No token, Google profile, registration key or arbitrary return URL is placed
in a frontend redirect URL.

## GET `/auth/api/oauth/profile`

Requires only the temporary `profile_key` cookie, not an access session.
AuthService reads the pending Redis entry through SessionRepository. Returns
`200` and `Cache-Control: no-store`:

```json
{"email":"user@gmail.com","first_name":"Jane","last_name":"Doe"}
```

Read does not consume the entry or extend its 15-minute TTL. No provider
identifiers, Google tokens or keys are exposed. Missing/expired keys return
`401` with `code: "oauth_registration_expired"`.

## Frontend `/register/complete`

Public route. Load the pending profile through credentialed fetch; show Google
email read-only, editable first/last names, and a required phone field. Display
loading, validation, retryable API failure, expired registration and conflicting
account states. An expired registration offers a fresh Google sign-in.

## POST `/auth/api/oauth/complete`

Requires `profile_key` and an `Origin` equal to `FRONTEND_URL`. Request:

* `first_name`: optional; omitted uses Google name, explicit empty is invalid;
  final trimmed name must be nonempty and at most 100 characters.
* `last_name`: optional; omitted uses Google surname, explicit empty clears it;
  at most 100 characters after trimming.
* `phone`: required, 10–30 characters after trimming.

Email/provider/sub come exclusively from the verified server-side identity.
New users are active customers. Google email is treated as verified only when
`email_verified` is true and Google is authoritative (Gmail or a hosted Workspace
identity). Other emails remain unverified and use existing email verification.
Accounts are never automatically linked by matching email.

Concurrent completion uses an ownership-checked Redis lease (60 seconds) and a
bounded 30-second operation. PostgreSQL identity uniqueness is the final guard.
A retry after the account was created but session issuance failed recovers by
provider/sub, not by email. Pending data is retained until session creation
succeeds, then deleted; the response clears the `profile_key` cookie.

Success: `201`, auth cookies, JSON `{"message":"successfully registered"}`.
The frontend loads `/auth/api/private/profile`, updates Zustand, and navigates
by role. If that follow-up fails, `/oauth/success` retries session restoration
without another completion POST. OAuth handoff pages own session loading;
normal initialization resumes when navigating away.

Errors include an `error` message and stable `code`:

| Status | Code | Meaning |
| --- | --- | --- |
| 400 | invalid_request | Malformed request |
| 401 | oauth_registration_expired | Missing or expired pending key |
| 403 | invalid_origin | Missing or untrusted Origin |
| 403 | account_inactive | Account blocked |
| 409 | email_already_exists | Existing account conflict; sign in using its existing method |
| 409 | oauth_registration_busy | Concurrent registration; retry shortly |
| 422 | validation_failed | Invalid final profile fields |
| 500 | internal_error | Retryable internal failure |

Pending-profile/completion 401s do not trigger the access-session refresh
interceptor. OAuth HTTP responses use `Cache-Control: no-store`; callback also
sets `Referrer-Policy: no-referrer`.

---

# 23. EMAIL VERIFICATION

## POST `/auth/api/private/email/verify`

Request:

* `code`: exactly 10 characters

Trim surrounding whitespace before sending.

Success:

```json
{"message": "email verification was successful"}
```

Possible errors:

* `400` — invalid code format
* `422` — invalid or expired confirmation code

---

## POST `/auth/api/private/email/send-verification`

No body.

Success:

```json
{"message": "verification email sent successfully"}
```

Failure:

`422`

```json
{"error": "failed to send verification email"}
```

---

# 24. PROFILE API

## GET `/auth/api/private/profile`

Returns the current user profile as `UserProfile`:

```json
{"email":"user@gmail.com","first_name":"Jane","last_name":"Doe","phone":"+48123456789","role":"customer","is_verified":true,"is_oauth":true}
```

`is_oauth` is computed from account OAuth links and mapped to frontend
`User.isOAuth`. It controls Google email/password UI restrictions; backend
restrictions remain authoritative.

Possible responses:

* `200`
* `401`
* `403`

---

## PATCH `/auth/api/private/profile`

Update only changed fields.

Possible fields:

* `email`
* `first_name`
* `last_name`
* `phone`

At least one field must be provided.

Changed fields cannot be empty strings.

Success:

```json
{"message": "profile updated successfully"}
```

OAuth users:

Email must be rendered as read-only because the backend returns:

`403 email cannot be changed for oauth account`

Other possible errors:

* `400`
* `401`
* `403`
* `422`
* `500`

---

## PUT `/auth/api/private/profile/password`

Request:

* `old_pass`
* `new_pass`

Both must contain at least 8 characters.

Success:

```json
{"message": "password has been successfully reset"}
```

Important:

The server terminates the current session and clears cookies.

After success, redirect the user to:

`/login`

Possible errors:

* `401`
* `422`
* `500`

---

# 25. ADMIN API

Admin-only functionality requires:

* authenticated user
* role = `admin`

---

## PATCH `/auth/api/admin/users/:id/role`

Request:

```json
{"role": "admin"}
```

Allowed roles:

* `admin`
* `customer`
* `analyst`

Success:

```json
{"message": "user role updated successfully"}
```

Possible errors:

* `404`
* `400`
* `422`
* `500`

---

## PATCH `/auth/api/admin/users/:id/ban`

Request:

```json
{"is_banned": true}
```

`true` = ban

`false` = unban

Success:

```json
{"message": "user ban status updated successfully"}
```

Possible errors:

* `404`
* `400`
* `422`
* `500`

---

# 26. AUTH FLOWS

## Email registration

1. register; receive UserProfile and authentication cookies
2. store the returned profile and enter the authenticated application
3. show email status and an optional verification button in the profile
4. only on user request, send a code and open the verification screen
5. after verification, refresh the profile and return there without logging in again

## Login

1. login
2. authentication cookies are set
3. store UserProfile from the login response; no extra GET required
4. enter authenticated application, including accounts with unverified email

GET profile restores the session on page reload and updates account details after mutations.

## Google OAuth

1. redirect to Google OAuth endpoint
2. backend handles Google authentication
3. existing user → `/oauth/success` → load ordinary profile and restore auth state
4. new user → `/register/complete` → GET `/auth/api/oauth/profile`
5. submit `/auth/api/oauth/complete`
6. load ordinary profile and enter authenticated application

## Logout

1. call logout
2. clear client authentication state
3. return to login

## Password change

1. submit password change
2. backend invalidates session
3. clear local state
4. redirect to login

---

# 27. NOTIFICATION SERVICE

## Base URL

`https://localhost/notification/api`

This service uses the same auth cookies issued by the auth service.

The browser automatically sends the cookies to this path because the cookies use:

`Path=/`

All requests require credentials.

---

# 28. Notification access rules

All private notification endpoints require:

* authenticated user
* role `admin` or `analyst`

Customers must receive a forbidden state and must not see notification navigation.

---

# 29. Notification API

All notification routes are GET-only.

---

## GET `/notification/api/private/notifications`

Purpose:

Retrieve notification history.

Optional query parameters:

* `status`
* `channel`
* `recipient`
* `start_date`
* `end_date`
* `limit`
* `offset`

Dates must be formatted using:

`date.toISOString()`

This produces RFC3339 timestamps with `Z`.

Prefer `URLSearchParams`.

Do not manually construct date query strings when avoidable.

Do not send empty filter parameters.

Pagination:

* `limit`: 0–100
* `offset`: 0 or greater
* recommended limit: 20

Pagination formula:

`offset = (page - 1) * limit`

When filters change:

`offset = 0`

Possible errors:

* `400`
* `401`
* `403`
* `500`

For invalid dates, show a useful user-facing validation or error state.

---

## GET `/notification/api/private/notifications/:id`

Returns one notification record.

Possible errors:

* `400`
* `401`
* `403`
* `404`

For `404`, show:

**Notification not found**

---

# 30. Infrastructure Endpoints

The following are backend infrastructure endpoints and must not be used by the frontend:

* `/auth/api/healthz`
* `/auth/api/readyz`
* `/notification/api/healthz`
* `/notification/api/readyz`

---

# 31. Frontend Structure

Keep the generated project understandable.

A reasonable structure is:

```text
src/
  components/
    ui/
    layout/
    shared/

  pages/
    auth/
    catalog/
    checkout/
    profile/
    admin/
    notifications/

  routes/
  store/
  services/
  hooks/
  lib/
  types/
  data/

  App.tsx
  main.tsx
```

Adapt the structure when necessary, but avoid unnecessary complexity.

Keep:

* routing
* global state
* API clients
* pages
* reusable components
* mock data
* shared utilities

clearly separated.

---

# 32. Mock Data

For functionality without a finalized business API, use local mock data.

Keep mock data in dedicated files or modules.

Do not hardcode large datasets directly inside page components.

Mock data should be structured so that it can later be replaced with API responses.

---

# 33. Maintainability Rules

This is important.

The generated code should be easy for a developer to continue manually.

### Avoid

* giant monolithic components
* duplicated markup
* duplicated API logic
* unnecessary abstractions
* deeply nested component hierarchies
* unnecessary libraries
* magic strings scattered across the application
* route paths duplicated throughout the codebase

### Prefer

* small reusable components
* clear filenames
* clear responsibilities
* centralized API configuration
* centralized routes
* reusable forms
* reusable table/filter patterns
* typed data models
* simple state management

Do not over-engineer the project.

---

# 34. Generation Strategy

Build the frontend in a way that allows the project to evolve.

### First priority

Generate:

1. application shell
2. design system
3. navigation
4. authentication screens
5. catalog
6. profile
7. checkout
8. admin
9. notifications

### Initial implementation

Use mock business data where real business APIs are not defined.

Create the frontend structure and components so API integration can be added without redesigning the UI.

Do not invent undocumented backend endpoints.

### Important

Do not redesign already-created components when implementing another page.

Reuse the existing design system.

Do not introduce a new visual language for every page.

Do not introduce new dependencies unless they are clearly necessary.

---

# 35. Final Design Goal

The finished frontend should look like a real modern product.

It should feel:

* premium
* clean
* energetic
* friendly
* visually confident

while remaining:

* simple
* understandable
* maintainable
* easy to extend
* easy to connect to the existing backend

The result should look significantly more polished than a basic CRUD dashboard, but it should not become an over-engineered design experiment.

Prioritize:

**clarity + consistency + maintainability + strong visual hierarchy.**
