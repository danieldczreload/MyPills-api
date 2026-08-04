.PHONY: up down sh logs db reset-db test migrate cs stan help

COMPOSE_ENV_FILE ?= $(if $(wildcard .env.local),.env.local,.env)
COMPOSE = docker compose --env-file $(COMPOSE_ENV_FILE)

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

migrate: ## Run database migrations
	$(COMPOSE) exec php bin/console doctrine:migrations:migrate --no-interaction

cs: ## Run PHP CS Fixer
	$(COMPOSE) exec php vendor/bin/php-cs-fixer fix --dry-run --diff

stan: ## Run PHPStan
	$(COMPOSE) exec php vendor/bin/phpstan analyse --memory-limit=-1
