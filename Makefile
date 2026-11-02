# Lấy tất cả các từ đứng sau lệnh chính (ví dụ: identity trong "make up identity")
SERVICES := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))

# Chuyển các từ đó thành target ảo để Make không báo lỗi "No rule to make target"
$(eval $(SERVICES):;@:)

.PHONY: up down restart log clean

up:
	@if [ -z "$(SERVICES)" ]; then \
		echo "Starting all services..."; \
		docker compose up -d; \
	else \
		echo "Starting selected services: $(SERVICES)..."; \
		docker compose up -d $(SERVICES); \
	fi

down:
	@if [ -z "$(SERVICES)" ]; then \
		echo "Stopping and removing all services..."; \
		docker compose down; \
	else \
		echo "Stopping and removing selected services: $(SERVICES)..."; \
		docker compose rm -s -f $(SERVICES); \
	fi

restart:
	@if [ -z "$(SERVICES)" ]; then \
		echo "Restarting all services..."; \
		docker compose restart; \
	else \
		echo "Restarting selected services: $(SERVICES)..."; \
		docker compose restart $(SERVICES); \
	fi

log:
	@if [ -z "$(SERVICES)" ]; then \
		docker compose logs -f; \
	else \
		docker compose logs -f $(SERVICES); \
	fi

clean:
	@echo "Warning: This will remove all containers and volumes!"
	docker compose down -v

migrate:
	@echo "Running database migrations..."
	docker compose exec backend make migrate

migrate-down:
	@echo "Running database migrations..."
	docker compose exec backend make migrate-down

seed:
	@echo "Seeding the database..."
	docker compose exec backend make seed