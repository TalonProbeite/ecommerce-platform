# OAuth refactoring — implementation record

Approved scope: backend callback redirects, pending profile API via AuthService and SessionRepository, completion page, transaction binding/PKCE/nonce, active-account checks, validation, conflicts, retry/concurrency safety, profile OAuth flag, tests/lint. No commits.

Architecture: backend owns Google exchange and identity; Redis stores expiring transaction/profile data; frontend only reads safe DTOs with credentialed requests. Shared HTTPS origin via existing global nginx.

- [x] Baseline inspection and active/blocked account regression RED→GREEN.
- [x] OAuth service/repository/client contracts and security tests.
- [x] Pending profile API, redirects, cookies and HTTP tests.
- [x] Frontend services, state coordination, routes and completion UI.
- [x] Profile mapping and docs/configuration.
- [x] Full verification and final review.

Ruling: work in the existing feature checkout to leave changes directly reviewable; no branch/worktree/commit operations.
Ruling: recover partial registration by looking up the trusted provider/sub link on retry, avoiding a second pending user-id write that could itself fail. PostgreSQL uniqueness remains the final concurrency guard.
Ruling: use a bounded completion operation (30s) under a 60s ownership-checked Redis lease; all DB/Redis operations use its context.

Final review: independent read-only reviewer found initialization remaining idle after leaving handoff and optional surname not clearing. Both fixed; browser navigation regression and backend surname regression pass. No deferred findings.
Verification: backend race suite with isolated Redis/PostgreSQL passed; golangci-lint 0 issues; frontend full suite 45/45, TypeScript and REAL build passed; browser success/expiry/conflict/mobile/retry/double-submit checks passed.
Ruling: initialize inside AppLayout on pathname changes instead of once in App, preserving handoff ownership and resuming normal bootstrap after navigation.
