.DEFAULT_GOAL := help
COMPOSE := docker compose

help: ## List the available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

up: ## Build and start the stack (first run installs dependencies, migrates and seeds)
	$(COMPOSE) up -d --build --wait

down: ## Stop the stack
	$(COMPOSE) down

test: ## Run the test suite
	$(COMPOSE) exec app php artisan test

lint: ## Check code style (Pint)
	$(COMPOSE) exec app ./vendor/bin/pint --test

logs: ## Follow app and worker logs
	$(COMPOSE) logs -f app worker

shell: ## Open a shell in the app container
	$(COMPOSE) exec app bash

fresh: ## Recreate the database with the demo user
	$(COMPOSE) exec app php artisan migrate:fresh --seed --force

.PHONY: help up down test lint logs shell fresh
