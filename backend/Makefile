.PHONY: dev build check clean clean-cache migrate migrate-down seed master-data command queue-worker

# Tùy chọn: make migrate-down TARGET=0 hoặc TARGET=<migration-version>
TARGET ?=

# -------------------------------------------------------------------
# Cấu hình Caching & Tối ưu tài nguyên cho Cargo
# -------------------------------------------------------------------
# Tự động dùng sccache nếu máy đã cài đặt
export RUSTC_WRAPPER := $(shell which sccache 2>/dev/null)

# Tự động phát hiện số CPU cores, cho phép override: make build CARGO_BUILD_JOBS=4
CARGO_BUILD_JOBS ?= 2
export CARGO_BUILD_JOBS

# Giảm thiểu debug info sinh ra trong binary để tăng tốc link và giảm dung lượng đĩa
export CARGO_PROFILE_RELEASE_DEBUG := 0
export CARGO_PROFILE_DEV_DEBUG := 0

# Tách riêng thư mục cache cho release build để không làm bẩn/hỏng cache của dev
export CARGO_TARGET_DIR ?= target

# -------------------------------------------------------------------
# Targets
# -------------------------------------------------------------------

# Chạy chế độ phát triển với hot-reload cho API Gateway
dev:
	cargo watch -x "run -p api-gateway"

# Kiểm tra lỗi toàn bộ workspace nhanh chóng
check:
	cargo check --workspace --all-targets

# Build toàn bộ project ở chế độ release
build:
	@echo "Building release with $(CARGO_BUILD_JOBS) jobs (sccache: $(if $(RUSTC_WRAPPER),enabled,disabled))..."
	CARGO_INCREMENTAL=0 cargo build --release --locked --workspace

# Dọn dẹp thư mục target thông thường
clean:
	cargo clean

# Dọn dẹp cả sccache nếu có cài đặt
clean-cache: clean
	@if command -v sccache >/dev/null 2>&1; then \
		echo "Clearing sccache..."; \
		sccache --stop-server 2>/dev/null || true; \
		sccache --clear; \
		sccache --start-server; \
	fi

# Chạy các migration chưa áp dụng; app tự đọc DB_* từ .env.
migrate:
	cargo run --locked --package console --bin migrate -- up

# Mặc định lùi một migration. TARGET=0 rollback toàn bộ schema và dữ liệu Identity.
migrate-down:
	cargo run --locked --package console --bin migrate -- down $(TARGET)

# Local fixtures: Identity Admin + Foreign public clients; không dùng cho production.
seed:
	cargo run --locked --package console --bin console -- seed

# Production reference data: Identity Admin client, giữ nguyên nếu đã tồn tại.
master-data:
	cargo run --locked --package console --bin console -- master-data

# Ví dụ: make command CMD=master-data
command:
	cargo run --locked --package console --bin console -- $(CMD)

# One consumer process for all backend job types (Supervisor runs the release binary).
queue-worker:
	cargo run --locked --package console --bin console -- queue:work
