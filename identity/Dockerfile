FROM node:24-bookworm-slim AS builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
# Public browser configuration only. Never pass private keys or client secrets.
ARG VITE_API_BASE_URL=https://backend.nvnhan0810.com
ARG VITE_SSO_CLIENT_ID=identity
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL VITE_SSO_CLIENT_ID=$VITE_SSO_CLIENT_ID
RUN npm run build

FROM node:24-bookworm-slim AS runtime
WORKDIR /app
COPY --from=builder /app/package.json ./package.json
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/server ./server
USER 10001:10001
ENV NODE_ENV=production PORT=8080
EXPOSE 8080
STOPSIGNAL SIGTERM
CMD ["node", "server/index.ts"]
