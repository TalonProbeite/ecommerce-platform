# Phase 07 — Notifications

## Goal
Implement `/notifications` for admin/analyst users.

## Read first
- `AGENTS.md`
- `.agents/rules/api-contract.md`
- `docs/RENTIFY_MASTER_SPEC.md` sections 15, 27–29

## Tasks
- role-restricted route + navigation
- notification table
- status/channel/recipient/date filters
- pagination with `limit`/`offset`
- reset offset when filters change
- omit empty query parameters
- detail modal/screen
- 404 “Notification not found”
- loading/empty/error/forbidden states

## Constraints
Read-only notification area. Never add a frontend “send notification” endpoint.
