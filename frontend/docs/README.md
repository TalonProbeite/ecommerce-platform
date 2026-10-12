# Rentify development workflow

`RENTIFY_MASTER_SPEC.md` keeps the complete product/API specification.

Use phases in order:
1. `01-foundation.md`
2. `02-auth.md`
3. `03-catalog.md`
4. `04-checkout.md`
5. `05-profile.md`
6. `06-admin.md`
7. `07-notifications.md`
8. `08-integration-hardening.md`
9. `09-visual-qa.md`

In Antigravity, run one phase at a time. Ask the agent to read the phase file, implement only that phase, test it, and stop.

## Frontend API modes

Run the existing mocks:

```sh
pnpm dev:mock
```

Configure REAL transport for later contract verification:

```sh
cp .env.real.local.example .env.real.local
# Set VITE_API_URL in .env.real.local to your actual backend base URL.
pnpm dev:real
```

`VITE_API_MODE=mock` always selects the existing mock adapter, even if an API URL is set. `VITE_API_MODE=real` uses Axios network requests with `withCredentials: true` and requires a valid HTTP(S) `VITE_API_URL`. A missing or invalid URL stops Vite with a configuration error. HTTP and network failures never switch REAL to mocks. Restart Vite after changing environment files.

`.env.mock` and `.env.real` select the modes; local overrides follow Vite's environment precedence. `.env.real.local` is ignored by Git. All `VITE_*` values are public browser configuration: do not put secrets in them. No backend address is supplied by default. Configure only the backend origin, without paths, query parameters, fragments or URL credentials. Paths already contain `/auth/api` or `/notification/api`; an additional base path would duplicate service prefixes. Invalid origins are rejected.

Plain `pnpm dev` and `pnpm build` retain the previous behavior when `VITE_API_MODE` is unset: mocks without `VITE_API_URL`, network requests when it is configured. Select a build mode explicitly with `pnpm build --mode mock` or `pnpm build --mode real` (the latter also requires `VITE_API_URL`).

REAL uses the documented auth, registration, email, profile mutation, password, logout and admin endpoints. Requests are mapped separately from legacy mock payloads, and message responses are validated before showing success. Registration immediately probes the profile because the server establishes cookies. Verification sends only the trimmed code; resend/logout send no body. No failed REAL request falls back to mocks.

Refresh uses `POST /auth/api/refresh`, shares one in-flight refresh across concurrent failures (including late 401s from requests made before a completed refresh), and retries each request once. Only refresh/retry 401 expires local authentication; 403, server/network errors and cancellation remain visible without declaring the session expired. Password success ends local authentication; logout clears local state even if the server cannot confirm logout. OAuth uses full browser navigation to `/auth/api/oauth/google` at the configured REAL origin (the spec's localhost origin in MOCK).

**End-to-end REAL account access is still blocked:** the project does not define the profile response schema (including role, verification, OAuth account status). Initialization and login request the actual profile but a 200 produces an explicit schema error rather than an invented User. Network/403/bootstrap errors show retryable feedback; guest 401 does not prevent public navigation. Confirm this schema before implementing the DTO mapper in `src/services/auth.ts`.

Notifications remain explicitly MOCK-only because list/detail schemas, enum values and pagination metadata are missing. OAuth completion has a confirmed service request but its frontend callback/handoff is unspecified, so no callback route or query flags were invented. Catalog remains visibly demo data in REAL; live reservation, checkout, order history, analytics and listing mutations are unavailable. MOCK retains the existing local business flows.

See [REAL integration audit](real-api-integration.md) for the endpoint matrix, conflicts, blockers, changed files and verification evidence. Actual backend availability and cookie/TLS/OAuth behavior are not certified by fixtures.

Verify adapter selection and REAL HTTP, network errors, and refresh handling with:

```sh
node scripts/api-modes.test.mjs
node scripts/integration-hardening.test.mjs
node scripts/real-integration.test.mjs
node --test --test-concurrency=1 scripts/*.test.mjs
```

The REAL transport test uses a temporary local HTTP fixture, not the actual backend.
