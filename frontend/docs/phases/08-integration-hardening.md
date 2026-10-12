# Phase 08 — Integration & Hardening

## Goal
Replace only the mocks that have confirmed backend contracts and harden cross-cutting behavior.

## Read first
- `AGENTS.md`
- `.agents/rules/api-contract.md`
- `docs/RENTIFY_MASTER_SPEC.md` sections 18–30

## Tasks
- verify Axios base paths and credential behavior
- verify refresh interceptor and retry loop prevention
- verify role guards
- verify service boundaries and types
- verify query serialization and date handling
- remove accidental hardcoded secrets/endpoints
- replace mocks only where the master spec gives an exact API

## Constraints
Do not invent missing backend contracts. Avoid unrelated refactors.
