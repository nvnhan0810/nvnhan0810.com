.PHONY: dev build check clean migrate migrate-down seed

# Tùy chọn: make migrate-down TARGET=0 hoặc TARGET=<migration-version>
TARGET ?=

# Chạy chế độ phát triển với hot-reload cho API Gateway
dev:
	cargo watch -x "run -p api-gateway"

# Kiểm tra lỗi toàn bộ workspace nhanh chóng
check:
	cargo check

# Chạy các migration chưa áp dụng; app tự đọc DB_* từ .env.
migrate:
	cargo run --locked --package console --bin migrate -- up

# Mặc định lùi một migration. TARGET=0 rollback toàn bộ schema và dữ liệu Identity.
migrate-down:
	cargo run --locked --package console --bin migrate -- down $(TARGET)

# Build toàn bộ project ở chế độ release (tối ưu hóa hiệu năng)
build:
	cargo build --release

# Dọn dẹp các tệp build tạm thời (thư mục target)
clean:
	cargo clean

# Local fixtures: Identity Admin + Foreign public clients; không dùng cho production.
seed:
	cargo run --locked --package console --bin console -- seed

# Production reference data: Identity Admin client, giữ nguyên nếu đã tồn tại.
.PHONY: master-data command
master-data:
	cargo run --locked --package console --bin console -- master-data

# Ví dụ: make command CMD=master-data
command:
	cargo run --locked --package console --bin console -- $(CMD)

# Laravel PostgreSQL vocabulary import; dry-run by default. IMPORT_ARGS=--apply to write.
.PHONY: import-foreign
import-foreign:
	cargo run --locked --package console --bin console -- foreign:import $(IMPORT_ARGS)
