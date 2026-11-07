# MovieMov: everyday commands. Run from Git Bash in the repo root:  make   (lists everything)
# Needs only Docker Desktop, Git Bash and make: PHP, Composer, MySQL and Node all run in Docker.

SHELL := bash
.DEFAULT_GOAL := help

# Git Bash would rewrite container paths like /app into C:/Program Files/Git/app; keep them as they are.
export MSYS_NO_PATHCONV := 1

ROOT := $(shell pwd -W 2>/dev/null || pwd)
DC   := docker compose
# One-off PHP container: no database needed (tests use in-memory SQLite).
RUN  := $(DC) run --rm --no-deps -T app
# Inside the running app container (started with `make up`).
EXEC := $(DC) exec -T app
NODE := docker run --rm -v "$(ROOT):/app" -w /app node:22-alpine

.PHONY: help setup up down restart ps logs shell tinker artisan migrate admin \
        test lint fix build check docs release deploy-check deploy

help: ## Show this list
	@echo "MovieMov make targets (site: http://localhost:8000, admin: /admin, API docs: /docs/api)"
	@echo
	@grep -hE '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  \033[36mmake %-13s\033[0m %s\n", $$1, $$2}'
	@echo
	@echo "Options: make test FILTER=Draft    make admin EMAIL=you@example.com    make artisan CMD=\"route:list\""

# ---- local site --------------------------------------------------------------

setup: ## First run: .env, PHP + JS packages, CSS build, start, migrate
	@[ -f .env ] || { cp .env.example .env && echo "Created .env from .env.example"; }
	$(RUN) sh -c "composer install --no-interaction && (grep -q '^APP_KEY=base64:' .env || php artisan key:generate)"
	$(NODE) sh -c "npm ci --no-audit --no-fund && npm run build"
	$(DC) up -d --wait
	$(EXEC) php artisan migrate --force
	@echo
	@echo "Ready: http://localhost:8000   Next: create your admin login with  make admin"

up: ## Start the site and database (http://localhost:8000)
	$(DC) up -d --wait
	@echo "Running: http://localhost:8000  (admin: http://localhost:8000/admin)"

down: ## Stop the site and database (data is kept)
	$(DC) down

restart: ## Restart the app container
	$(DC) restart app

ps: ## Show container status
	$(DC) ps

logs: ## Follow the app log (Ctrl+C to stop)
	$(DC) logs -f --tail=100 app

shell: ## Open a shell inside the app container
	$(DC) exec app sh

tinker: ## Laravel tinker (PHP console) on the local database
	$(DC) exec app php artisan tinker

artisan: ## Any artisan command: make artisan CMD="route:list"
	$(EXEC) php artisan $(CMD)

migrate: ## Run new database migrations locally
	$(EXEC) php artisan migrate

admin: ## Create or update an admin login (asks for the password). EMAIL=... optional
	$(DC) exec app php artisan app:create-admin $(EMAIL)

# ---- checks ------------------------------------------------------------------

test: ## Run the test suite (FILTER=... to run some tests)
	$(RUN) php artisan test $(if $(FILTER),--filter=$(FILTER))

lint: ## Check code style (Pint)
	$(RUN) vendor/bin/pint --test

fix: ## Fix code style (Pint)
	$(RUN) vendor/bin/pint

build: ## Build the CSS (Tailwind/Vite) into public/build
	$(NODE) sh -c "[ -d node_modules ] || npm ci --no-audit --no-fund; npm run build"

check: test lint build ## Everything a deploy checks: tests, code style, CSS build

docs: up ## Regenerate the API reference docs/openapi.json (commit it after API changes)
	$(EXEC) php artisan scramble:export

# ---- release / deploy ----------------------------------------------------------

release: ## Build the production zip only (.release/release.zip), no upload
	bash deploy/build-release.sh

deploy-check: ## Full deploy rehearsal without uploading anything (deploy.cmd --dry-run)
	bash deploy/deploy.sh --dry-run

deploy: ## Deploy to moviemov.id (asks you to type the domain; needs deploy/deploy.env)
	bash deploy/deploy.sh
