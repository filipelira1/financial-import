.DEFAULT_GOAL := help
COMPOSE := docker compose

help: ## List the available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Build and start the stack (first run installs dependencies, migrates and seeds)
	HOST_UID=$$(id -u) HOST_GID=$$(id -g) $(COMPOSE) up -d --build --wait

down: ## Stop the stack
	$(COMPOSE) down

test: ## Run the test suite
	$(COMPOSE) exec app php artisan test

smoke: ## Import the provided CSV through the running API and check the totals
	./scripts/smoke-test.sh

test-frontend: ## Run the frontend checks (type-check and Vitest) in a Node container
	docker run --rm -u "$$(id -u):$$(id -g)" -e HOME=/tmp -v "$(CURDIR)/frontend:/app" -w /app node:22.22-bookworm-slim sh -c "npm ci --no-audit --no-fund && npm run type-check && npm test"

lint: ## Check code style (Pint)
	$(COMPOSE) exec app ./vendor/bin/pint --test

docs: ## Regenerate docs/openapi.json from the code (Scramble)
	$(COMPOSE) exec app php artisan scramble:export --path=storage/app/openapi.json
	mv backend/storage/app/openapi.json docs/openapi.json

logs: ## Follow app and worker logs
	$(COMPOSE) logs -f app worker

restart-worker: ## Reload the queue worker after code changes
	$(COMPOSE) exec app php artisan queue:restart

shell: ## Open a shell in the app container
	$(COMPOSE) exec app bash

fresh: ## Recreate the database with the demo user
	$(COMPOSE) exec app php artisan migrate:fresh --seed --force

.PHONY: help up down test smoke test-frontend lint docs logs restart-worker shell fresh
