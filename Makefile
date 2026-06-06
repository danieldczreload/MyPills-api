.PHONY: up down sh logs db reset-db test migrate cs stan help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Start all services and wait for health
	docker compose up --pull always -d --wait

down: ## Stop all services
	docker compose down --remove-orphans

sh: ## Open shell in PHP container
	docker compose exec php sh

logs: ## Show logs from all services
	docker compose logs -f

db: ## Open PostgreSQL shell
	docker compose exec database psql -U app -d app

reset-db: ## Drop and recreate database
	docker compose exec php bin/console doctrine:database:drop --force --if-exists
	docker compose exec php bin/console doctrine:database:create
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

test: ## Run PHPUnit tests
	docker compose exec php vendor/bin/simple-phpunit

migrate: ## Run database migrations
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

cs: ## Run PHP CS Fixer
	docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff

stan: ## Run PHPStan
	docker compose exec php vendor/bin/phpstan analyse --memory-limit=-1
