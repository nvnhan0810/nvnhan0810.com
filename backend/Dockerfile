# syntax=docker/dockerfile:1
FROM rust:1.99.1-bookworm

# Cài đặt cargo-watch để tự động build/run khi code thay đổi
RUN cargo install cargo-watch

WORKDIR /app

# Khởi chạy api-gateway mặc định (sẽ tự reload khi bạn save code)
CMD ["cargo", "watch", "-x", "run --bin api-gateway"]