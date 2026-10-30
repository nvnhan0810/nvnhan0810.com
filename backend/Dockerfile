# syntax=docker/dockerfile:1
FROM rust:1.99.1-bookworm AS chef
# Cached independently of application source changes.
RUN cargo install cargo-chef --version 0.1.77 --locked
WORKDIR /app

FROM chef AS planner
COPY . .
RUN cargo chef prepare --recipe-path recipe.json

FROM chef AS builder
# Cache compiled dependencies until manifests/lockfile change.
COPY --from=planner /app/recipe.json recipe.json
RUN cargo chef cook --locked --release --package api-gateway --package console --bins --recipe-path recipe.json
COPY . .
RUN cargo build --locked --release --package api-gateway --package console --bins

FROM debian:bookworm-slim AS runtime
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=builder /app/target/release/api-gateway /usr/local/bin/api-gateway
COPY --from=builder /app/target/release/migrate /usr/local/bin/migrate
COPY --from=builder /app/target/release/seed /usr/local/bin/seed
USER 10001:10001
EXPOSE 8080
STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/local/bin/api-gateway"]
