.PHONY: up down sh logs db reset-db test migrate cs stan lint check help secrets-pull secrets-clean

export PATH := $(HOME)/.local/bin:$(PATH)

BWS_TOKEN ?= $(shell (secret-tool lookup service bws account mypills-local 2>/dev/null || (test -f $(HOME)/.bws_token && cat $(HOME)/.bws_token 2>/dev/null)) | tr -d '\r\n')
BWS_EXEC = $(if $(BWS_TOKEN),BWS_ACCESS_TOKEN=$(BWS_TOKEN) bws run --,$(if $(BWS_ACCESS_TOKEN),bws run --,))

COMPOSE_ENV_FILE ?= $(if $(wildcard .env.local),.env.local,.env)
COMPOSE = $(BWS_EXEC) docker compose --env-file $(COMPOSE_ENV_FILE)

secrets-pull: ## Fetch secrets from Bitwarden Secrets Manager into .env.local
	@./bin/bws-pull

secrets-clean: ## Remove .env.local securely
	@rm -f .env.local && echo "🧹 Removed .env.local"

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Start all services and wait for health
	$(COMPOSE) up --pull always -d --wait

down: ## Stop all services
	$(COMPOSE) down --remove-orphans

sh: ## Open shell in PHP container
	$(COMPOSE) exec php sh

logs: ## Show logs from all services
	$(COMPOSE) logs -f

db: ## Open PostgreSQL shell
	$(COMPOSE) exec database psql -U app -d app

reset-db: ## Drop and recreate database
	$(COMPOSE) exec php bin/console doctrine:database:drop --force --if-exists
	$(COMPOSE) exec php bin/console doctrine:database:create
	$(COMPOSE) exec php bin/console doctrine:migrations:migrate --no-interaction

test: ## Run PHPUnit tests
	$(COMPOSE) exec php vendor/bin/simple-phpunit

coverage: ## Run PHPUnit tests with code coverage report
	$(COMPOSE) exec -e XDEBUG_MODE=coverage php vendor/bin/simple-phpunit --coverage-text

migrate: ## Run database migrations
	$(COMPOSE) exec php bin/console doctrine:migrations:migrate --no-interaction

cs: ## Run PHP CS Fixer
	$(COMPOSE) exec php vendor/bin/php-cs-fixer fix --dry-run --diff

stan: ## Run PHPStan
	$(COMPOSE) exec php vendor/bin/phpstan analyse --memory-limit=-1

lint: ## Run Symfony container, YAML and Doctrine schema linters
	$(COMPOSE) exec php bin/console lint:container
	$(COMPOSE) exec php bin/console lint:yaml config --parse-tags
	$(COMPOSE) exec php bin/console doctrine:schema:validate --skip-sync

check: lint cs stan test ## Run all quality gates (lint, cs, stan, test)
