# REAL API integration audit

## Reading this report — status clarified 2026-10-20

This file combines an original historical audit (evidence paths dated
2026-10-09) with later dated updates. The original endpoint matrix, blockers,
resulting status and test counts are snapshots, not current contracts. The
2026-10-12 password-auth update supersedes registration-to-OTP and `/email/resend`;
the 2026-10-13 updates supersede OAuth/profile and notification-schema blockers.
See [README](README.md#current-real-application-contract--2026-10-20) and the
master specification for the current application contract.

The successful browser session below followed the unsuccessful initial Phase 09
attempt recorded in [the archived QA report](phases/09-visual-qa-report.md).
These are different runs, not contradictory results. The 320 px screenshot
was a narrow OTP layout check with mocks/fixtures, not live-backend validation
or complete visual/accessibility sign-off. A later 2026-10-20 PR 4 MOCK smoke
pass at 1440/390 px is recorded in that QA report. This clarification does not
claim new browser or backend tests on the foundation-only PR.

## OAuth update — 2026-10-13

OAuth handoff blockers listed in the historical audit below are resolved. The
callback now returns 303 to `/oauth/success` or `/register/complete`; the latter
reads `GET /auth/api/oauth/profile` through AuthService/SessionRepository and
submits `POST /auth/api/oauth/complete`. Both use HttpOnly cookies, with safe
DTOs and no key/token query parameters. `UserProfile.is_oauth` is documented and
mapped. Active-account checks, final-form validation, browser-bound atomic state,
PKCE/nonce, Origin validation and identity-based partial-failure recovery are
implemented. See [OAuth implementation report](oauth-refactoring-report.md) for
verification and runtime configuration. Earlier missing-schema and OAuth UI
handoff statements describe the original audit, not the current implementation.


## Historical audit — 2026-10-09 evidence

### Sources and limits

Audited a clean working tree at `d076845` before changing code. Read `AGENTS.md`, both `.agents/rules/` files, `docs/README.md`, the entire master specification, every phase document and the Phase 09 report. Searched tracked and hidden project files, excluding dependencies and Git internals, for API maps, Swagger/OpenAPI, Postman, JSON/YAML specifications and endpoint references. No additional backend contract was found. `dist/docs/` contains copies of the same documentation, not a separate API map.

Authoritative maps: `docs/RENTIFY_MASTER_SPEC.md` sections 19–30 and `.agents/rules/api-contract.md`. Frontend models in `src/types/`, mock adapters and fixtures are NOT response contracts. Existing tests use mocks or a temporary HTTP fixture, not the backend.

## Endpoint matrix

All paths below are relative to a configured backend **origin**, normally `https://localhost`. Fetch uses `credentials: 'include'`; JavaScript never accesses auth cookies. Message responses are JSON `{message: string}`; errors are `{error: string}`. Common errors are 400/422/401/403/500 where applicable.

| Function | Method / endpoint | Request | Response | Auth / role | Documented errors | Initial integration / work |
| --- | --- | --- | --- | --- | --- | --- |
| Register | POST `/auth/api/register` | email, password ≥8, first_name required ≤100, last_name optional ≤100, phone 10–30 | 201 message; sets auth cookies | Public | 400,409,422,500 | MOCK-only; connect mutation, then load profile without assuming its shape |
| Login | POST `/auth/api/login` | email,password | 200 message; sets auth cookies | Public | 400,401,403,422,500 | MOCK-only; connect mutation, then load profile |
| Logout | POST `/auth/api/logout` | No body | message; clears cookies | Works without tokens | Common errors | Already REAL; validate message and clear local state even on failure |
| Refresh | POST `/auth/api/refresh` | No body | 200 message | Refresh cookie | 401 | Already REAL; retain single-flight/retry limit, preserve session on transient failures |
| Google entry | GET `/auth/api/oauth/google` | Browser navigation | Redirect to Google | Public | Not enumerated | Already full navigation |
| Google callback | GET `/auth/api/oauth/google/callback` | Google code,state; browser state cookie | 303 frontend redirect + cookies | Backend handles | Safe login error redirect | Implemented; frontend never calls callback |
| Pending OAuth profile | GET `/auth/api/oauth/profile` | No body | email,first_name,last_name; no-store | profile_key cookie | 401 missing/expired | Implemented; non-consuming read |
| OAuth completion | POST `/auth/api/oauth/complete` | first_name,last_name,phone | 201 registration message; auth cookies | profile_key cookie + trusted Origin | 400,401,403,409,422,500 | Implemented page/service/store handoff |
| Verify email | POST `/auth/api/private/email/verify` | code, trimmed, exactly 10 chars | message | Auth cookie | 400,422; common auth errors | MOCK-only; connect REAL without email payload |
| Resend | POST `/auth/api/private/email/resend` | No body | message | Auth cookie | 422; common auth errors | MOCK-only; connect REAL |
| Profile read | GET `/auth/api/private/profile` | No body | 200; **schema missing** | Auth cookie | 401,403 | MOCK-only; REAL request can discover auth errors, but cannot construct User safely |
| Profile update | PATCH `/auth/api/private/profile` | Changed nonempty email,first_name,last_name,phone; ≥1 | message | Auth cookie; OAuth email immutable | 400,401,403,422,500 | MOCK-only; connect mutation, invalidate/reload profile |
| Password | PUT `/auth/api/private/profile/password` | old_pass,new_pass, both ≥8 | message; terminates session | Auth cookie | 401,422,500 | Already REAL; validate success before clearing state |
| Admin role | PATCH `/auth/api/admin/users/:id/role` | role: admin/customer/analyst | message | Auth, admin | 400,404,422,500 + auth errors | MOCK-only; enable REAL, no local success before response |
| Admin ban | PATCH `/auth/api/admin/users/:id/ban` | is_banned: boolean | message | Auth, admin | 400,404,422,500 + auth errors | MOCK-only; enable REAL |
| Notification list | GET `/notification/api/private/notifications` | status,channel,recipient,start_date,end_date,limit,offset | **Schema/enums/pagination metadata missing** | Auth, admin/analyst | 400,401,403,500 | MOCK-only; retain explicit REAL contract blocker |
| Notification detail | GET `/notification/api/private/notifications/:id` | ID | **Schema missing** | Auth, admin/analyst | 400,401,403,404; user also requests 500 handling | MOCK-only; retain explicit REAL blocker |
| Catalog / availability | Not found | Not defined | Not defined | Not defined | Not defined | Local fixtures; expose demo status in REAL |
| Cart / checkout | Not found | Not defined | Not defined | Not defined | Not defined | Local cart; prevent simulated order success in REAL |
| Rentals / orders | Not found | Not defined | Not defined | Not defined | Not defined | Local history; label demo content in REAL |
| Analytics / listing status | Not found | Not defined | Not defined | Not defined | Not defined | Local fixtures; prevent pretend listing mutations in REAL |

## Contradictions requiring care

- Section 15 gives notification detail as `/api/private/notifications/:id`; sections 27–29 and existing code confirm nginx prefix `/notification`. Preserve `/notification/api/...`.
- README describes all account/staff actions as MOCK-only, while the master spec confirms mutation requests and message responses. Update README to distinguish implemented transport from blocked response mapping.
- Mock login requires verified email and mock registration does not authenticate. REAL registration authenticates immediately. Keep the legacy mock flow isolated, never infer REAL behavior from it.
- Notifications accept limit 0 in section 29, though UI page size is 20–100. Preserve tested query support for 0; the UI does not offer 0.
- A failed refresh cannot justify clearing a session on network/500/cancellation/403. Only definitive 401 expires authentication; other errors remain retryable and visible.

## Blockers

1. Profile JSON envelope, ID type, field names, role representation, verification flag, OAuth flag and ban/account status. No role defaults or mock-derived DTOs are permitted. This blocks end-to-end REAL login, session restoration and all guarded screens.
2. Notification list/detail schemas, status/channel values and pagination metadata. Keep REAL reads explicitly unavailable rather than presenting fixture data.
3. OAuth frontend redirect destination and reliable new-user handoff. Do not invent query flags or read profile_key cookies.
4. Catalog, availability, orders, history, listing management and checkout contracts do not exist here. Demo data is not real backend data; REAL checkout must never create a pretend confirmed order.
5. Backend reachability and authorized test accounts. GET `https://localhost/auth/api/private/profile` failed to connect on port 443 inside and outside the sandbox. No backend response or TLS handshake was obtained.

## Implementation plan

User has already authorized incremental execution of these phases in the current workspace. No commits, backend changes, new dependencies or unrelated redesign. Execute locally with tests before production changes.

- [x] Add `src/services/auth.ts` with separated mock/REAL paths, snake_case request mapping, message validation, raw profile read with an explicit missing-schema error, OAuth-complete service and changed-field profile mapping. Test methods, paths, body omission, errors and no mock fallback.
- [x] Update auth store bootstrap/login/register/verification/profile/password/logout to use services. Represent profile/network/403 failures with explicit retryable state, never fake a role. Test stale response vs logout and refresh transient errors.
- [x] Enable confirmed Admin mutations and validate responses; update self role only after confirmation and invalidate REAL privileges after self-change. Preserve notification schema blocker and query behavior.
- [x] Audit every registered route; show forbidden access, ensure safe post-login destination, session error/retry UI and refresh-page handling. Preserve mock guards and verify roles with render/route tests.
- [x] Make mock marketplace data visible as demo in REAL and reject local order/listing success. Test absence of writes on REAL checkout.
- [x] Run all existing/new tests, typecheck, both builds and diff checks. Attempt browser verification if available; record exact fixture/backend/manual limits here.

## Validation ledger

Baseline: existing suite passed seven files in sandbox; api-modes local listener is sandbox-restricted. Standalone `node scripts/api-modes.test.mjs` outside sandbox passed all three tests. No backend API was successfully exercised.

## Resulting integration status

| Feature | REAL status after this work | Remaining work |
| --- | --- | --- |
| Auth | Login/register/logout/refresh transport connected; profile is actually requested. No local auth cookies or mock fallback. | Profile schema blocks authenticated User construction and successful end-to-end login/session restoration. |
| OAuth | Entry remains full browser navigation. Confirmed completion request has its own service. | Backend frontend redirect/profile-key handoff missing; no completion route invented. |
| Email | Real verification/resend requests and confirmed UI success enabled, no email/body added to private endpoints. | Actual server cookies/delivery/OTP lifecycle not tested. |
| Profile/password | Changed fields mapped to snake_case; no-op PATCH omitted; password/logout lifecycle tested. | Guarded live profile UI depends on missing profile schema. |
| Admin | REAL role/ban mutations enabled, validated message required; self-change reloads profile. | Real UI entry and refreshed permissions depend on profile schema; backend role enforcement not tested live. |
| Notifications | Existing MOCK list/detail, filters, pagination, errors and cancellation preserved. REAL explicitly blocked before requests. | Confirm list/detail schemas, enums and pagination metadata. |
| Catalog/cart | Fixture catalog explicitly marked demo in REAL; live reservation disabled. | Catalog, availability and cart contract absent. |
| Checkout/orders/rentals | Full local MOCK flows preserved. REAL checkout never creates a simulated order; history and analytics unavailable. | Contracts absent. |

## Changed files

- `src/services/auth.ts` (new), `src/api/response.ts` (new): request mapping and runtime message validation, separate legacy MOCK branches and explicit profile-schema barrier.
- `src/api/client.ts`: single-flight refresh including late parallel 401s; refresh errors preserve their actual status; only definitive 401 expires sessions; safe extraction of string error messages.
- `src/store/auth-store.ts`: services, retryable bootstrap state, generation guards against late profile updates, account mutation deduplication and confirmed session lifecycle.
- `src/services/admin.ts`, `src/components/admin/user-action-form.tsx`, `src/pages/admin-page.tsx`: REAL mutations enabled, self-change invalidation, demo catalog status controls disabled in REAL.
- `src/components/auth/session-feedback.tsx` (new), `src/components/auth/protected-route.tsx`, `src/app/router.tsx`, `src/lib/roles.ts`: retryable account feedback, explicit forbidden/banned access and safe role-compatible login destinations.
- `src/app/app.tsx`, `src/components/layout/app-layout.tsx`, `src/components/layout/site-header.tsx`, `src/pages/root-page.tsx`: session revalidation on REAL navigation/focus and every 60 seconds while visible, hide stale navigation while loading/error, redirect on expiry, demo notice. Expiry redirects once rather than trapping subsequent public-page navigation.
- `src/pages/login-page.tsx`, `src/pages/register-page.tsx`, `src/pages/profile-page.tsx`: duplicate submit guards, safe redirects and registration-to-verification flow.
- `src/pages/verify-email-page.tsx`: cookie-based REAL verification needs no email field, preserves code case, shared verify/resend pending guard, combined invalid/expired 422 state.
- `src/services/checkout.ts`, `src/services/rental-history.ts`, `src/pages/checkout-page.tsx`, `src/pages/orders-page.tsx`, `src/pages/equipment-page.tsx`, `src/pages/analytics-page.tsx`: prevent pretending to perform live rentals or expose live fixture history.
- `scripts/real-integration.test.mjs` (new): 15 contract/state/race tests. Existing admin and hardening tests updated to assert the new REAL behavior rather than obsolete MOCK-only prohibitions; no test files removed.
- `docs/README.md`, this report: corrected mode documentation and exact remaining scope.

## Routes and browser evidence

Actual router is `src/app/router.tsx` (there is no App.tsx route map). App shell lives in `src/app/app.tsx`.

| Route | Guard | Evidence |
| --- | --- | --- |
| `/` | Public; authenticated role home | Guest page rendered in MOCK; REAL account error shown when bootstrap fails |
| `/login`, `/register` | Public; existing user redirect | Guest pages rendered; MOCK role logins; REAL fixture registration, expiry/public-page navigation |
| `/verify-email` | Public entry; private REAL operation enforced by backend | Guest page rendered; cookie-only REAL form, 422/resend/success fixture checks |
| `/catalog`, `/equipment/:id` | Customer | Browser direct load/reload for all three roles; missing equipment state |
| `/profile`, `/orders` | Customer | Browser direct load/reload for all roles; live profile entry remains schema-blocked |
| `/checkout`, `/checkout/cart`, `/checkout/delivery`, `/checkout/success`, unknown checkout step | Customer | Role restrictions, empty cart, unknown step/confirmation; MOCK reservation → checkout → confirmation; confirmation reload correctly reports missing session-only order |
| `/admin` | Admin | All three roles checked in browser; self-role/ban lifecycle covered by tests |
| `/notifications` | Admin/analyst | All roles checked; MOCK detail modal opened; list/filter/pagination/error tests passed |
| `/analytics` | Admin/analyst | All roles checked; only MOCK metrics supported |
| `*` | Public not found | `/missing` rendered; unknown protected checkout/equipment states checked |

Browser tools were available in this session. Vite MOCK served port 3000; REAL served port 3001 using a temporary same-origin fixture configuration, not a production backend setting. No `.env.real.local` or secrets were written.

- 13 guest direct-navigation checks passed; public pages stay public, protected pages redirect to login.
- 36 MOCK direct-navigation/reload role checks passed (12 paths × customer/analyst/admin). Guards and role navigation remain enforced.
- 7 REAL browser checks passed using Playwright request interception: missing profile schema, retry after profile 403 without logout redirect, guest 401 public registration, one registration for duplicate submit, invalid/expired verification 422, resend with no body, and case-preserving code-only confirmed verification.
- Additional REAL expiry regression was reproduced and fixed: after login/profile/refresh 401, redirect to login occurs; the subsequent Create an account link can open `/register`.
- MOCK checkout/confirmation, honest confirmation-not-found after reload, and notification detail modal passed interactively.
- At 320 px, verification had no horizontal viewport overflow; screenshot inspected. This is a narrow visual check, not full responsive/accessibility sign-off.
- Early browser QA attempts timed out because the harness assumed role-home login rather than a saved allowed destination, assumed every empty state used h1, and used an incomplete checkout button label. Corrected scenarios passed. A dev hot reload cleared the intentionally non-persistent MOCK cart; the uninterrupted checkout flow passed afterward.
- MOCK browser had no console errors; REAL fixtures generated expected HTTP 401/403/422 console messages. React Router warns about the existing missing HydrateFallback on initial lazy route hydration; this remains a follow-up for initial route loading feedback.

## Final checks and manual follow-up

- `node --test --test-concurrency=1 scripts/*.test.mjs` outside sandbox: **32/32 tests passed** across all nine test files. Sandbox-only execution cannot open the HTTP fixture listener and fails api-modes; this environment limitation was separately verified and then resolved by the authorized outside-sandbox run.
- `pnpm typecheck`: passed.
- `pnpm build --mode mock`: passed.
- `VITE_API_URL=https://localhost pnpm build --mode real`: passed. This proves compilation/configuration, not backend reachability.
- `git diff --check`: passed. No formatter/linter script exists in package.json. Existing Vite warnings remain about `__dirname` and the extensionless configuration import; no unrelated build-config changes made.
- Actual backend calls: only an attempted GET `https://localhost/auth/api/private/profile`, both inside and outside sandbox, returning connection failure on port 443. **No backend endpoint successfully verified.** HTTP fixture tests and browser intercepts are explicitly separate evidence.
- Manual/live follow-up: obtain missing schemas/maps and OAuth handoff; implement and validate DTO mapping; provide reachable backend/TLS and permitted test accounts; verify real cookie issuance, refresh/expiry, CORS/SameSite/Secure behavior, password session invalidation, OAuth existing/new-user paths, email delivery and codes, server role/ban changes and notification responses. Check all authenticated flows in browser after profile mapping. Full responsive, keyboard, accessibility and cross-browser verification is still pending.

The full REAL integration is **not complete**. Confirmed transport and defensive state/UI improvements are reviewable; the missing contracts above prevent honest end-to-end completion. No backend changes, commits, new dependencies or publishing occurred.

Temporary MOCK/REAL dev servers were stopped. Generated builds and browser evidence were moved to `/tmp/rentify-validated-real-build-20261009` and `/tmp/rentify-browser-evidence-20261009`; the originally tracked `dist/` was restored without changing source edits. Test/build logs are in `/tmp/rentify-suite-full.log`, `/tmp/rentify-typecheck.log` and `/tmp/rentify-build-{mock,real}.log`.


## Profile and account navigation update — 2026-10-12

The backend `UserProfile` now returns `email`, `first_name`, `last_name`, `phone`, `role` and `is_verified`. The frontend maps the last two fields to `User.role` and `User.emailVerified`, validates supplied values, and leaves undocumented ID/OAuth/ban fields unset. Earlier missing-role/verification limitations in this report are superseded by this update.

The profile shows role badges only for admins and analysts, displays email verification status, and links unverified accounts to `/verify-email`. The account menu uses the required Base UI menu group, includes a profile entry for every role, and replaces the separate profile navigation tab. Staff returning from login can also reach their profile.

Profile rental cards are available without a backend user ID. REAL profiles show explicitly labelled demo orders; no orders endpoint is called and the existing live orders page remains unavailable. Seed orders now live in `src/data/orders.ts` and are shared with the mock orders store.

Verification: profile contract regression tests, TypeScript check, production build, and browser checks of account menu navigation, customer/admin badges, verified/unverified email states, demo orders and a 390px mobile viewport. Browser checks used injected account state; live cookie issuance and email delivery were not exercised. Backend files were not modified.


## Password authentication and optional verification — 2026-10-12

Login and registration now return the direct UserProfile JSON rather than a message. A shared frontend mapper handles all three profile responses. The auth store authenticates from the mutation response, with no immediate GET or code request. Registration enters the customer catalog; email confirmation is optional. The mock adapter also authenticates new and unverified password accounts.

The profile's neutral email status panel sends POST `/auth/api/private/email/send-verification` without a body only after an explicit click, then opens the OTP screen. Failure keeps the user in the profile with a retryable error. Direct visits to the OTP screen offer Send code without automatically sending email. Successful verification returns to the profile with the existing session. The previous `/email/resend` endpoint and automatic registration-to-OTP flow are superseded.

GET profile remains for initial session restoration, account updates and periodic/focus revalidation. Navigation alone no longer reloads the profile. A regression test covers a late guest bootstrap 401 racing with registration, preventing it from cancelling the new session.

Browser verification covered form registration, optional confirmation, sending failure and retry, cooldown, OTP success and profile return. MOCK and REAL transport with intercepted HTTP fixture responses were exercised; no live email delivery or backend containers were used.


## Notification integration — 2026-10-13

The notification schema blockers in the original integration report above are resolved by inspecting `services/notification/internal/domain/models.go`, the HTTP analytics handlers, the event repository, and the email dispatcher.

Both notification GET routes now work in REAL mode through the shared credentialed fetch client and existing refresh handling. The list response is an array of EventLog records; details return one record. Fields are `id`, `created_at`, `type`, `channel`, `recipient`, `status`, `payload`, `attempts`, and optional `error`. Current delivery statuses are `sent`/`failed` and the implemented channel is `email`. Recipient filtering is exact. Payload is rendered as text JSON, without assuming a specific event shape.

The backend returns no total or pagination metadata. The UI shows the current range and enables Next for a full page. At an exact page boundary, Next can reach an empty page; Previous remains available. Requests retain `limit`/`offset`, reset offset on filter changes, omit empty filters, and use RFC3339 dates. A zero API limit uses the backend default of 100. Mock responses now use the same array/record contract and matching semantics.

Verification: notification service and role/error tests, the full frontend test suite, TypeScript, production build, and browser checks for mock list pagination and the detail dialog. A live notification backend was not used for this verification. Backend files were not modified.
