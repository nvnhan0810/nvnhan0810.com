# syntax=docker/dockerfile:1
FROM rust:1.99.1-bookworm AS builder
WORKDIR /app
COPY . .
RUN cargo build --locked --release --package api-gateway

FROM debian:bookworm-slim AS runtime
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=builder /app/target/release/api-gateway /usr/local/bin/api-gateway
USER 10001:10001
EXPOSE 8080
STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/local/bin/api-gateway"]
