# Phase 02 — Authentication

## Goal
Implement the public auth flows using the exact backend contract.

## Read first
- `AGENTS.md`
- `.agents/rules/api-contract.md`
- `docs/RENTIFY_MASTER_SPEC.md` sections 10, 20–24, 26

## Tasks
- `/login`
- `/register`
- `/verify-email`
- Google OAuth browser navigation, backend 303 handoff, `/oauth/success` and `/register/complete`
- pending profile read via GET `/auth/api/oauth/profile` and completion via POST `/auth/api/oauth/complete`
- centralized fetch client + credentials
- one-shot refresh interceptor
- auth state in Zustand
- protected-route behavior
- logout
- password-change redirect behavior

## Constraints
Do not expose or manipulate auth cookies from JavaScript. Do not invent endpoints. Preserve existing shell and design system.

## Done when
Email auth, OAuth entry, verification states, logout, refresh behavior, and protected navigation behave consistently and have clear error/loading states.
