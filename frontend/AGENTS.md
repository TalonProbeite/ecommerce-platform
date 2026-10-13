# Rentify — Antigravity Project Instructions

## Mission
Build and extend Rentify as a polished, maintainable equipment-rental marketplace frontend. Treat this repository as an existing product: inspect before changing, preserve working code, and prefer incremental changes over rewrites.

## Non-negotiable stack
- React + Vite + TypeScript
- Tailwind CSS + shadcn/ui
- Lucide React
- react-router-dom
- Zustand
- Axios

Do not switch frameworks or introduce dependencies without a clear reason.

## Product direction
Rentify should feel premium, energetic, confident, friendly, and consumer-oriented — not like an enterprise CRUD dashboard. Use a light neutral base, one coherent accent palette, generous whitespace, strong typography, expressive imagery, soft rounding, subtle shadows, clear CTAs, and restrained gradients/animation.

## Implementation rules
- Reuse existing components and design tokens before creating new ones.
- Prefer shadcn/ui components when an appropriate primitive exists.
- Use Lucide React for icons.
- Keep components focused; avoid giant page components and duplicated markup.
- Centralize routes and API configuration.
- Keep local UI state local; use Zustand only for genuinely shared state.
- Keep mock business data separate from pages and shape it like future API responses.
- Preserve responsive behavior across desktop, tablet, and mobile.
- Every important data-driven screen needs loading, success, empty, validation-error, API-error, unauthorized, forbidden, and not-found states where applicable.

## Backend contract
Do not invent endpoints, request fields, response formats, or auth behavior. The detailed backend contract lives in `docs/RENTIFY_MASTER_SPEC.md` and the relevant API rule. When an exact contract is not defined, keep business data mocked and isolate the integration behind services.

Authentication uses httpOnly cookies. The frontend never reads or writes auth cookies. Axios must use `withCredentials: true`, and 401 refresh behavior must remain centralized and loop-safe.

## Working protocol
1. Inspect the current implementation before editing.
2. Read the relevant phase file from `docs/phases/`.
3. Make only the requested phase changes unless a tiny prerequisite is necessary.
4. Reuse the established design system; do not redesign unrelated pages.
5. Run the project checks available in the repository.
6. Start the app and use `/browser` for visual verification when UI changes are involved.
7. Summarize changed files, checks run, and any remaining issues.
8. Stop after the phase. Do not silently continue to the next phase.

## Source of truth
`docs/RENTIFY_MASTER_SPEC.md` is the full product/API specification supplied for this project. This file is reference material; do not blindly duplicate all of it into every prompt.
