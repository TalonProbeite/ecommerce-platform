# Frontend Docker Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build and connect the frontend to the global Compose project, with an independent MOCK stack.

**Architecture:** Node/pnpm builds Vite assets; a separate nginx runtime serves the SPA as UID 101 on port 8080. The REAL service joins shop_global via include; MOCK publishes its own loopback port.

**Tech Stack:** Docker BuildKit, Docker Compose include, Node 22 Alpine, pnpm 12.3.4, nginx Alpine.

**Spec:** User request in this conversation.

## Global Constraints

- Do not change any files under services/.
- Preserve existing backend routes and global HTTP-to-HTTPS redirect.
- No source-code or frontend business-flow changes.
- Do not commit, deploy, or restart existing backend services.

## Review Focus

- Deep SPA links must return index.html; missing assets must return 404.
- Runtime must work with a read-only root filesystem, dropped capabilities and a non-root user.
- Local environment files, node_modules and tracked dist must not enter the build context.
- MOCK starts without any backend service; REAL build arguments select the correct mode/origin.
- Global nginx preserves /auth/ and /notification/ routing and serves the frontend fallback.

### Task 1: Image and frontend Compose files

Files: frontend/Dockerfile, frontend/.dockerignore, frontend/docker/nginx.conf, frontend/docker-compose.prod.yaml, frontend/docker-compose.local.yaml.

- [x] Add three build stages: dependencies, build, runtime; frozen lockfile, cached pnpm store, explicit source copies, runtime static files only, USER 101:101, EXPOSE 8080, healthcheck.
- [x] Add REAL and standalone MOCK Compose files with read_only, tmpfs /tmp, cap_drop ALL and no-new-privileges.
- [x] Validate both Compose models; build both images; smoke-test health, SPA routes, missing assets, ownership and image contents.

### Task 2: Global integration and documentation

Files: docker-compose.yaml, docker/nginx/conf.d/default.conf, frontend/docs/docker.md.

- [x] Include frontend prod Compose; gate global nginx startup on frontend health.
- [x] Proxy global nginx HTTPS fallback to frontend:8080; keep port 80 redirect and API locations.
- [x] Validate global Compose and nginx syntax with isolated test upstreams, without starting backend services.
- [x] Document commands, build-time configuration, port 80/443 behavior and validation limits; verify git diff contains no services/ changes.

## Execution notes

The user explicitly authorized implementation in this shared workspace. Keep the unrelated untracked `AGENTS.md ` file untouched. Build and test only frontend and disposable infrastructure. Docker daemon access is approved.

Validation: both images built successfully; all three Compose models validate; standalone MOCK and isolated global nginx/REAL smoke checks passed. Runtime UID 101, read-only root, ALL capabilities dropped, no-new-privileges and absence of Node/pnpm confirmed. Temporary test containers/networks removed. No services/ changes; no full backend stack startup.
