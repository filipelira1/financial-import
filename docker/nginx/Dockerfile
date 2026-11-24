# Build the Vue SPA, then serve it with nginx next to the API proxy.
# Debian-based build image: the native bindings of Vite/Tailwind in package-lock match glibc.
FROM node:22.22-bookworm-slim AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

FROM nginx:1.30-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/security-headers.conf /etc/nginx/snippets/security-headers.conf
COPY --from=frontend /app/dist /usr/share/nginx/html
