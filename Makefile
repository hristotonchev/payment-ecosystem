.PHONY: help up down build rebuild restart logs migrate \
        test test-gateway test-fraud test-notify \
        shell-gateway shell-fraud shell-notify \
        validate check-compose

help:
	@echo "Payment Ecosystem — Makefile Commands"
	@echo ""
	@echo "Lifecycle:"
	@echo "  make build          Build Docker images"
	@echo "  make up             Start all services"
	@echo "  make down           Stop all services"
	@echo "  make rebuild        Rebuild and restart (clean)"
	@echo "  make restart        Restart running services"
	@echo ""
	@echo "Operations:"
	@echo "  make logs           View container logs (follow)"
	@echo "  make migrate        Run Payment Gateway DB migrations"
	@echo "  make validate       Validate Docker Compose setup"
	@echo ""
	@echo "Testing:"
	@echo "  make test           Run all service tests"
	@echo "  make test-gateway   Run Payment Gateway tests"
	@echo "  make test-fraud     Run Fraud Engine tests"
	@echo "  make test-notify    Run Notification Service tests"
	@echo ""
	@echo "Access:"
	@echo "  make shell-gateway  Shell into Payment Gateway"
	@echo "  make shell-fraud    Shell into Fraud Engine"
	@echo "  make shell-notify   Shell into Notification Service"

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

rebuild: down build up

restart: down up

logs:
	docker compose logs -f

migrate:
	docker compose exec payment-gateway php bin/console doctrine:migrations:migrate --no-interaction

test-gateway:
	docker compose exec payment-gateway php vendor/bin/phpunit --testdox

test-fraud:
	docker compose exec fraud-engine php vendor/bin/phpunit --testdox

test-notify:
	docker compose exec notification-service php vendor/bin/phpunit --testdox

test: test-gateway test-fraud test-notify

shell-gateway:
	docker compose exec payment-gateway sh

shell-fraud:
	docker compose exec fraud-engine sh

shell-notify:
	docker compose exec notification-service sh

validate: check-compose
	@echo "✓ Docker Compose configuration is valid"

check-compose:
	docker compose config > /dev/null

