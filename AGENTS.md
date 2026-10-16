# Rentify — Agent Instructions

This repository contains the Rentify frontend, backend services, and infrastructure.

## Existing AI infrastructure

The `frontend/` directory contains an established AI-assisted development infrastructure, including:

- `frontend/AGENTS.md` — frontend agent instructions.
- `frontend/.agents/rules/` — development rules and API contract guidelines.
- `frontend/.agents/skills/` — frontend-specific skills, if present.
- `frontend/docs/` — project documentation, specifications, implementation phases, and integration reports.

**Do not ignore, duplicate, or relocate this infrastructure.**

## Agent workflow

- When working on frontend tasks, first read `frontend/AGENTS.md` and follow its references to the relevant rules, skills, and documentation.
- Treat instructions inside `frontend/` as authoritative for frontend-specific work.
- When working on integration tasks, inspect the relevant frontend, backend, and infrastructure code before making changes.
- When working exclusively on backend tasks, do not modify frontend files unless necessary and explicitly justified.
- Run frontend commands from the `frontend/` directory.
- Preserve existing project conventions and avoid unrelated changes.

## General principles

- Do not invent API endpoints, DTO fields, authentication behavior, or backend capabilities.
- Use the actual implementation and documented contracts as the source of truth.
- Keep changes focused and verify the affected functionality.
- Do not create Git commits unless explicitly requested.