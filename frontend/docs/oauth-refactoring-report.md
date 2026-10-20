# OAuth refactoring report — 2026-10-13

## Delivered behavior

Google entry remains a full browser navigation. The backend callback now issues
303 redirects after setting cookies: existing active users go to `/oauth/success`,
new users go to `/register/complete`. Errors return to login with a safe code.
The completion form pre-fills Google names/email, keeps email read-only, collects
phone, validates fields, and finishes registration before restoring the account
profile and navigating by role. No secret is passed through frontend URLs.

## Backend structure and fixes

- `application/oauth.go` keeps OAuth orchestration in AuthService. Its public
  `GetOAuthProfile(ctx, key)` uses a private pending-profile reader and
  SessionRepository; handlers do not talk directly to Redis.
- New GET `/auth/api/oauth/profile` exposes only email/first_name/last_name with
  `Cache-Control: no-store`. Reading neither consumes the key nor extends TTL.
- Browser-bound state is stored with PKCE verifier and OIDC nonce. Redis GETDEL
  consumes the transaction atomically. Google verifies signed ID tokens and nonce.
- New accounts are active customers. Blocked accounts cannot get OAuth sessions.
  Google email verification is retained only for authoritative Gmail/Workspace
  identities with email_verified=true; other addresses remain unverified.
- Completion validates trimmed final names/phone. Explicit empty surname clears
  the optional field; explicit empty first name is rejected. Trusted identity
  fields are taken exclusively from Redis.
- Ownership-checked Redis leases serialize completion; operations have bounded
  contexts. Account creation remains a PostgreSQL transaction. Partial session
  failures recover using the provider/sub link, without automatic email linking.
- Both pgx and lib/pq uniqueness errors become an account conflict. Nullable OAuth
  password_hash reads use COALESCE, preventing scan failures on later sign-ins.
- Cookies share helpers across login/register/refresh/logout/OAuth. Pending and
  transaction cookies are cleared at the appropriate lifecycle points.
- Missing/expired keys return 401; validation/conflict/blocked/origin/internal
  errors have stable codes. Completion rejects missing or foreign Origin.
- Ordinary profiles expose is_oauth, mapped to frontend isOAuth for email and
  password restrictions.

## Frontend integration

Services own pending/completion requests. Zustand owns session completion and
restoration and ignores stale guest probes. Handoff pages own initial loading;
AppLayout resumes normal initialization when leaving them. A failed profile read
after successful registration restores the session without a second registration
POST. Forms cover loading, validation, retryable errors, expiry and account
conflicts and reuse existing components/styles.

Independent read-only review found the navigation-idle and surname-clearing bugs;
both were fixed and checked. No review findings remain deferred.

## Verification

- Backend: `go test -race -json ./...` with AUTH_TEST_DATABASE_URL and
  AUTH_TEST_REDIS_URL pointing at isolated PostgreSQL 18 / Redis 7 containers:
  **127 passing test cases/subtests, 0 failures**. Seventeen existing live HTTP
  tests were skipped because RUN_INTEGRATION was not enabled against a full
  running auth service. New real repository tests were executed, not skipped.
- Real repositories: both pgx/lib/pq, nullable passwords, profile flags, unique
  email/identity conflicts, transaction rollback, parallel single-use state,
  ownership-safe lock release, lease expiry and pending-profile expiry.
- Google client: local signed ID tokens and JWKS validate PKCE exchange, claims,
  correct/wrong nonce and audience. No live Google login was performed.
- `golangci-lint run ./...`: **0 issues**.
- Frontend: `node --test --test-concurrency=1 scripts/*.test.mjs`: **45/45 passed**.
- `pnpm typecheck`: passed.
- `VITE_API_URL=https://localhost pnpm build --mode real`: passed. Existing Vite
  config-loader and bundle-size warnings remain. The frontend has no configured
  ESLint/lint script; TypeScript/build were used for its static checks.
- Browser with intercepted API contracts: prefill/read-only email, field
  validation, success redirect, duplicate submit (one POST), session restoration,
  expired key without access refresh, email conflict, navigation away without an
  idle spinner, recovery after profile 503 without another completion POST.
- Mobile viewport 375px: no horizontal overflow; form/conflict layout inspected.
- `git diff --check`: passed. Test containers/dev server stopped; no commits.

## Runtime configuration

Set FRONTEND_URL to the frontend HTTPS origin, e.g. `https://localhost`; no trailing
slash, path, query or credentials. Set REDIRECT_URL to the registered Google
backend callback, e.g. `https://localhost/auth/api/oauth/google/callback`. Configure
CLIENT_ID and CLIENT_SECRET normally. The existing global nginx provides frontend
and API on one HTTPS origin; this is the supported cookie arrangement. The
standalone auth compose callback uses `/api/...`, while global nginx uses
`/auth/api/...`; choose the registered URI for the topology actually used.

No database schema migration or new package dependency was required. Old OAuth
transactions/pending cookies can be restarted through Google after deployment.
