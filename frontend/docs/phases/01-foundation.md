# Phase 01 — Audit, Design System, App Shell

## Goal
Establish a stable visual and structural foundation without rewriting the existing app.

## Read first
- `AGENTS.md`
- `docs/RENTIFY_MASTER_SPEC.md` sections 3–6, 8–9, 31–34

## Tasks
1. Audit the current repository and identify what already exists.
2. Confirm the existing stack and shadcn/ui setup. If shadcn is absent, initialize it only after inspecting the current Vite/Tailwind setup.
3. Define the Rentify design tokens/theme: neutral base + one accent, typography scale, radii, spacing, shadows, focus states.
4. Build or refine the application shell and shared navigation.
5. Centralize route definitions and establish public/customer/staff guards without inventing backend behavior.
6. Establish reusable shared states: loading, empty, error, forbidden, not-found.

## Constraints
Do not implement catalog, checkout, profile, admin, or notification features beyond what is required to make the shell testable.

## Done when
The app starts cleanly, the shell is coherent on mobile/desktop, shared UI primitives are reusable, and routing foundations are stable.
