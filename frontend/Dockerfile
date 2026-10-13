# syntax=docker/dockerfile:1

FROM node:22-alpine AS dependencies
WORKDIR /app

# Matches package.json. Package-manager/cache never reach the runtime image.
RUN npm install --global pnpm@12.3.4 && npm cache clean --force
RUN chown node:node /app
USER node
COPY --chown=node:node package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN --mount=type=cache,id=shop-frontend-pnpm,target=/home/node/.local/share/pnpm/store,uid=1000,gid=1000 \
    pnpm install --frozen-lockfile --store-dir /home/node/.local/share/pnpm/store

FROM dependencies AS build
ARG VITE_API_MODE=real
ARG VITE_API_URL=https://localhost
COPY --chown=node:node index.html tsconfig.json vite.config.ts ./
COPY --chown=node:node src ./src
COPY --chown=node:node public ./public
RUN case "$VITE_API_MODE" in real|mock) ;; *) echo 'VITE_API_MODE must be real or mock' >&2; exit 1 ;; esac \
    && VITE_API_MODE="$VITE_API_MODE" VITE_API_URL="$VITE_API_URL" pnpm build --mode "$VITE_API_MODE"

FROM nginx:1.28-alpine AS runtime
RUN rm -rf /usr/share/nginx/html/* /etc/nginx/conf.d/default.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY --from=build /app/dist /usr/share/nginx/html
RUN find /usr/share/nginx/html -type d -exec chmod 755 {} + \
    && find /usr/share/nginx/html -type f -exec chmod 644 {} + \
    && chmod 644 /etc/nginx/nginx.conf

# Root-owned, read-only assets; nginx only writes temporary files under /tmp.
USER 101:101
EXPOSE 8080
STOPSIGNAL SIGQUIT
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/healthz || exit 1
# Skip the base image's entrypoint scripts: no runtime config rewriting needed.
ENTRYPOINT ["nginx"]
CMD ["-g", "daemon off;"]
