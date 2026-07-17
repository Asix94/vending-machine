.DEFAULT_GOAL := help

COMPOSE = docker compose
PHP = $(COMPOSE) exec php

.PHONY: help up down restart ps logs logs-php logs-nginx shell health \
	test test-all test-setup test-usecases test-controllers test-buy test-integration test-concurrency

help: ## Show available commands
	@awk 'BEGIN {FS = ":.*##"; printf "\nAvailable commands:\n\n"} /^[a-zA-Z0-9_-]+:.*##/ {printf "  %-18s %s\n", $$1, $$2} END {printf "\n"}' $(MAKEFILE_LIST)

up: ## Start containers in background
	$(COMPOSE) up -d --build

down: ## Stop and remove containers
	$(COMPOSE) down

restart: ## Restart all containers
	$(COMPOSE) restart

ps: ## Show container status
	$(COMPOSE) ps

logs: ## Follow all service logs
	$(COMPOSE) logs -f

logs-php: ## Follow php logs
	$(COMPOSE) logs -f php

logs-nginx: ## Follow nginx logs
	$(COMPOSE) logs -f nginx

shell: ## Open shell in php container
	$(PHP) sh

health: ## Check health endpoint
	curl http://localhost:8080/health

test-setup: ## Create and migrate test database
	$(PHP) php bin/console doctrine:database:create --env=test --if-not-exists
	$(PHP) php bin/console doctrine:migrations:migrate --env=test --no-interaction

test: test-all ## Alias for full test suite

test-all: ## Run full test suite
	$(PHP) php bin/phpunit

test-usecases: ## Run application use case tests
	$(PHP) php bin/phpunit tests/Wallet/Application tests/VendingMachine/Application

test-controllers: ## Run controller tests
	$(PHP) php bin/phpunit tests/Controller

test-integration: ## Run integration tests
	$(PHP) php bin/phpunit tests/Integration

test-concurrency: ## Run concurrency tests
	$(PHP) php bin/phpunit tests/Concurrency

test-buy: ## Run buy product controller test
	$(PHP) php bin/phpunit tests/Controller/BuyProductControllerTest.php
