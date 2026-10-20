# Phase 09 — QA report

## Historical status — clarified 2026-10-20

This is the archived initial Phase 09 code-review pass. Its exact execution
date was not recorded; it predates the successful browser session recorded in
the [historical REAL integration audit](../real-api-integration.md), whose
evidence paths are dated 2026-10-09. Statements below about unavailable browser
tools, failed Chromium startup, test counts and pending viewport checks apply
only to that initial pass.

Later browser checks did succeed. The integration audit records a 320 px OTP
screenshot/overflow check, route/role checks and intercepted REAL HTTP fixture
scenarios. Its 2026-10-12 and 2026-10-13 updates record subsequent account and
notification checks. These supersede the earlier lack of browser evidence for
those specific scenarios; they do not establish full responsive/accessibility
coverage or live backend/OAuth/email verification.

On 2026-10-20, PR 4 preparation additionally passed 46 frontend tests, REAL/MOCK
Vite builds, and Playwright smoke checks of public pages and MOCK customer
catalog/profile/orders at 1440 and 390 px, with no JS errors or horizontal
viewport overflow. Those checks ran on the assembled application branch,
not on this foundation-only PR. Full cross-browser, keyboard, screen-reader,
contrast and live-backend sign-off remains outstanding.

## Scope and evidence

Reviewed AGENTS.md, frontend rules, the master specification, Phase 09, all page modules, the application shell, shared marketplace/account/checkout/staff components and UI primitives. Applied Web Interface Guidelines (https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md) and the applicable React performance guidance.

**No browser visual testing was completed during this initial pass.** `/browser` was not exposed in that session. One headless Chrome attempt exited before opening a page: Crashpad `setsockopt: Operation not permitted` (exit 133). No retries, browser installation or sandbox bypass were attempted. The attempted URL used port 5173; Vite actually served port 3000. Chrome failed before navigation, so neither URL was visually inspected during that pass.

`pnpm dev:mock --host 127.0.0.1` started successfully at http://127.0.0.1:3000. A subsequent HTTP request to `/login` returned the Vite HTML shell. This proves server availability, not React rendering or interactive behavior. No real backend was connected.

## Checklist results — code review only

| Screen/scenario | Result |
| --- | --- |
| Home, header, footer | Reviewed responsive grids, role navigation, CTAs and imagery. Added skip navigation and main target. |
| Catalog | Reviewed 1/2/3/4-column grid, category selection, sorting, query filters and empty results. Fixed category strip sizing alongside sort control. Data is synchronous local mock data; no catalog API loading/error state is applicable. |
| Equipment | Reviewed image, metadata, booking panel, invalid dates, disabled reservation and missing listing. Booking panel stacks below content before `lg` and uses sticky positioning afterward. |
| Cart / Checkout | Reviewed empty cart, invalid items/dates, delivery/pickup, field validation, pending/disabled submission, submission error, confirmation and unknown step/order. Shared summary/items and first invalid field focus already exist. |
| Orders | Reviewed populated/empty user-specific order history, removed listings and responsive cards. Reads synchronous session data; no orders API fetch exists. |
| Profile | Reviewed account fields, verification warning, OAuth read-only email, save feedback, password states and recent rentals loading/empty/error/retry. Fixed long email wrapping and badge wrapping. |
| Admin | Reviewed labeled user-action forms, pending/disabled states, success/errors, mock-only explanation and catalog table. Table has an internal horizontal scrolling wrapper. |
| Notifications | Reviewed filters, invalid dates, pagination, loading/error/retry/empty, keyboard-accessible detail button, scrollable details, 401/403/404 messages. Table scrolls horizontally rather than forcing columns into mobile width. |
| Login / Register | Reviewed labels, autocomplete, passwords, inline validation, API errors, pending buttons, demo controls and OAuth navigation. Shared field errors now have alert semantics and inputs default to a name. |
| Verify email | Reviewed invalid/expired code, pending, resend cooldown, success and email content. Fixed ten OTP cells overflowing the auth card using flexible group/cell widths. |
| Unauthorized / Forbidden | Reviewed centralized authentication loading and login redirects, role guards and explicit notification forbidden feedback. Other role guards redirect to the user's role home by design. |
| Not found | Reviewed page, equipment, checkout step/confirmation and notification not-found feedback with recovery actions where present. |
| Mobile: 320/390 px | Assessed breakpoint classes and intrinsic widths only. OTP widths are now flexible; forms/grids stack; long email/headings wrap. No screenshot or measured layout pass. |
| Tablet: 768 px | Assessed `sm`/`md` grids/navigation, category strip and staff table scroll wrappers only. No rendered viewport pass. |
| Desktop: 1280/1440 px | Assessed max-width containers, `lg` booking/checkout sidebars and grid columns only. No rendered viewport pass. |
| Focus / keyboard / labels | Checked semantic controls, icon labels, associated field labels and Base UI primitives. Added visible focus fallback for native links/buttons and skip-link. Keyboard traversal and focus trapping need browser verification. |
| Motion | Added reduced-motion override for smooth scrolling, transitions and animations. Actual preference switching needs browser verification. |
| Visual consistency / performance | Existing tokens, shared cards, empty states, form fields and staff feedback preserved. Route lazy imports and catalog memoized filtering preserved; no dependencies or request behavior changed. |

## Changes

- `src/pages/verify-email-page.tsx`: constrain OTP to available card width.
- `src/pages/catalog-page.tsx`: allow category strip to shrink and scroll; preserve sort width.
- `src/pages/profile-page.tsx`: wrap long verification emails and account badges.
- `src/components/auth/auth-shell.tsx`: allow card/content shrinkage and long text wrapping.
- `src/components/auth/form-field.tsx`: allow field wrapper shrinkage, default input name, disable email spellcheck, announce inline errors.
- `src/components/common/page-header.tsx`: constrain and wrap long page headings/descriptions.
- `src/components/layout/app-layout.tsx`: skip-link, focusable main target and header scroll offset.
- `src/index.css`: native link/button focus fallback and reduced-motion support.
- This report records evidence and follow-up requirements.

## Remaining limitations

Browser follow-up is required for screenshots, real viewport overflow, text contrast, image cropping, hover/focus appearance, mobile date inputs, zoom, keyboard traversal, screen-reader announcements and overlay focus restoration. Code inspection is not visual sign-off.

The guideline review also found existing lower-priority gaps: auth/profile/password forms do not move focus to the first invalid field on submit, some decorative icons lack explicit `aria-hidden`, and several existing components use `transition-all`. These were not expanded into a general component rewrite during this constrained pass.

Build warnings concern existing Vite configuration compatibility (`__dirname`, extensionless config import) and an entry chunk above 500 kB. No bundle refactor was performed in this visual phase. Generated `dist` changes from validation were removed; no commit was created.

## Validation results

- `pnpm typecheck`: exit 0.
- `pnpm build`: exit 0, with the warnings above.
- `node --test --test-concurrency=1 scripts/*.test.mjs`: seven files passed (admin, checkout, integration-hardening, notifications, password-security, profile, recent-rentals); API-modes failed inside the sandbox. The earlier concurrent run had the same file-level result.
- Direct diagnostic execution exposed the API-modes failure as `listen EPERM` when its local HTTP fixture bound to 127.0.0.1. This is an environment restriction, not an assertion failure.
- `node scripts/api-modes.test.mjs` outside the sandbox using the existing approved command rule: exit 0, all 3 tests passed. Across these runs all eight existing test files passed; there was no single all-green sandbox suite run.
- `git diff --check`: exit 0 after code changes and generated-output cleanup.
- No tests were added for these small CSS/semantic changes: existing state/integration coverage was rerun; browser layout/accessibility verification remains explicitly pending.
- The temporary mock dev server was stopped after the HTTP smoke check.
