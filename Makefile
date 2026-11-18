# VFF - Raccourcis dev
# Usage : `make help` pour la liste des cibles
#
# L'app tourne sur l'hôte (composer run dev). Docker/Podman n'héberge que Postgres et Valkey.
# Sans PHP / Composer / Node sur l'hôte, les cibles basculent automatiquement sur les
# wrappers conteneurisés bin/php, bin/composer et bin/pnpm (cf. README).

.DEFAULT_GOAL := help
SHELL := /bin/bash

# ---------- Outils : binaire de l'hôte s'il existe, sinon wrapper conteneurisé ----------

PHP      := $(if $(shell command -v php 2>/dev/null),php,bin/php)
COMPOSER := $(if $(shell command -v composer 2>/dev/null),composer,bin/composer)
PNPM     := $(if $(shell command -v pnpm 2>/dev/null),pnpm,bin/pnpm)
ENGINE   := $(or $(CONTAINER_ENGINE),$(shell command -v podman 2>/dev/null),$(shell command -v docker 2>/dev/null))

# Podman → podman-compose (ou `podman compose`) ; Docker → `docker compose`.
# Surcharge possible : make up DC="docker compose"
DC := $(shell if [[ "$(ENGINE)" == *podman ]]; then \
		if command -v podman-compose >/dev/null 2>&1; then echo podman-compose; else echo "podman compose"; fi; \
	else echo "docker compose"; fi)

PHP_IMAGE := localhost/vff-php:8.5
TEST_DB   := vff_test
# Processus des tests parallèles (paratest) : 4 au plus sur la machine partagée.
PROCESSES ?= 4

# ---------- Setup initial ----------

.PHONY: setup
setup: ## Premier démarrage : env, services data, deps, migrations
	@[ -f .env ] || cp .env.example .env
	@if [ "$(PHP)" = "bin/php" ] && ! $(ENGINE) image exists $(PHP_IMAGE) 2>/dev/null; then $(MAKE) php-image; fi
	$(DC) up -d
	@echo "→ Attente que la DB soit prête…"
	@until $(DC) exec -T db pg_isready -U vff -d vff > /dev/null 2>&1; do sleep 1; done
	$(PNPM) install
	$(COMPOSER) install
	$(PHP) artisan key:generate
	$(PHP) artisan migrate
	@$(MAKE) --no-print-directory test-db
	@echo ""
	@echo "✓ VFF prêt. Lance : make dev"
	@echo "  Adminer : http://localhost:8080  (serveur=db utilisateur=vff mdp=vff)"

.PHONY: php-image
php-image: ## Construit l'image PHP 8.5 utilisée par bin/php et bin/composer
	$(ENGINE) build -t $(PHP_IMAGE) docker/php

.PHONY: tools
tools: ## Affiche les outils détectés (hôte ou bin/*)
	@echo "php      → $(PHP)"
	@echo "composer → $(COMPOSER)"
	@echo "pnpm     → $(PNPM)"
	@echo "compose  → $(DC)"
	@echo "moteur   → $(ENGINE)"

.PHONY: dev
dev: ## Lance le dev complet (serve + queue + vite)
ifeq ($(PHP),php)
	composer run dev
else
	@echo "→ Mode conteneurisé : artisan serve (:8000) + queue + Vite (:5173) - Ctrl+C pour tout arrêter"
	@trap 'kill 0' INT TERM EXIT; \
		bin/php artisan serve --host=127.0.0.1 --port=8000 < /dev/null & \
		bin/php artisan queue:listen --tries=1 < /dev/null & \
		$(PNPM) dev < /dev/null & \
		wait
endif

# ---------- Services Docker/Podman (Postgres + Valkey + Adminer) ----------

.PHONY: up
up: ## Démarre les services de données
	$(DC) up -d

.PHONY: down
down: ## Stoppe les services de données (volumes préservés)
	$(DC) down

.PHONY: restart
restart: ## Redémarre les services
	$(DC) restart

.PHONY: logs
logs: ## Logs des services. Ex : make logs s=db
	$(DC) logs -f $(s)

.PHONY: ps
ps: ## État des conteneurs
	$(DC) ps

.PHONY: nuke
nuke: ## ATTENTION : stoppe ET supprime les volumes (DB perdue)
	$(DC) down -v

# ---------- Shells DB / Cache ----------

.PHONY: psql
psql: ## Console psql sur la DB
	$(DC) exec db psql -U $${DB_USERNAME:-vff} -d $${DB_DATABASE:-vff}

.PHONY: valkey-cli
valkey-cli: ## Console valkey-cli
	$(DC) exec valkey valkey-cli

# ---------- Composer / pnpm / Artisan ----------

.PHONY: install
install: ## composer install + pnpm install
	$(COMPOSER) install
	$(PNPM) install

.PHONY: update
update: ## composer update + pnpm update
	$(COMPOSER) update
	$(PNPM) update

.PHONY: artisan
artisan: ## Lance artisan avec args. Ex : make artisan cmd="make:model Line"
	$(PHP) artisan $(cmd)

.PHONY: tinker
tinker: ## REPL Laravel
	$(PHP) artisan tinker

.PHONY: migrate
migrate: ## Exécute les migrations
	$(PHP) artisan migrate

.PHONY: migrate-fresh
migrate-fresh: ## Reset complet de la DB + seed (DÉTRUIT les données importées)
	$(PHP) artisan migrate:fresh --seed

.PHONY: seed
seed: ## Exécute les seeders
	$(PHP) artisan db:seed

# ---------- Qualité ----------

.PHONY: test-db
test-db: ## Crée la base de test isolée vff_test (PostGIS) si absente
	@$(DC) exec -T db psql -U vff -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$(TEST_DB)'" | grep -q 1 \
		|| $(DC) exec -T db createdb -U vff -O vff $(TEST_DB)
	@$(DC) exec -T db psql -q -U vff -d $(TEST_DB) -c "SET client_min_messages = warning; CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS pg_trgm; CREATE EXTENSION IF NOT EXISTS unaccent;"
	@echo "✓ Base $(TEST_DB) prête (vidée/re-migrée à chaque run de tests)"

.PHONY: test
test: ## Lance la suite de tests en parallèle (base vff_test + vff_test_test_1 à _N, jamais la base de dev). Ex : make test PROCESSES=2
	$(PHP) artisan test --parallel --processes=$(PROCESSES)

.PHONY: test-serial
test-serial: ## Suite de tests en un seul processus (base vff_test)
	$(PHP) artisan test

.PHONY: test-parallel
test-parallel: test ## Alias de make test

.PHONY: coverage
coverage: ## Couverture PHPUnit (pcov à la demande) : résumé par dossier + HTML dans storage/app/reports/coverage
	$(COMPOSER) test:coverage

.PHONY: mutation
mutation: ## Tests de mutation Infection (services purs critiques, 4 threads) : long, hors make check
	@$(COMPOSER) test:mutation; status=$$?; \
		$(PHP) scripts/mutation-summary.php storage/app/reports/infection/infection.json; \
		exit $$status

.PHONY: i18n
i18n: ## Vérifie que chaque clé FR existe en EN et DE
	$(PHP) artisan vff:i18n-check

.PHONY: pint
pint: ## Formate TOUT le code PHP (Pint) - à éviter pendant des chantiers parallèles
	$(PHP) vendor/bin/pint

.PHONY: pint-dirty
pint-dirty: ## Formate uniquement les fichiers PHP modifiés (git)
	$(PHP) vendor/bin/pint --dirty

.PHONY: lint
lint: ## Vérifie le formatage front (Prettier)
	$(PNPM) lint

.PHONY: format
format: ## Formate le front (Prettier)
	$(PNPM) format

.PHONY: build
build: ## Build de production Vite
	$(PNPM) build

.PHONY: docs-graphql
docs-graphql: ## Régénère la référence GraphQL statique (Magidoc) : public/docs/schema.graphql puis public/docs/graphql/ (/docs/graphql)
	$(PHP) artisan lighthouse:print-schema > public/docs/schema.graphql
	$(PNPM) docs:graphql

.PHONY: analyse
analyse: ## Analyse statique PHP : PHPStan + Larastan niveau 6 (dette gelée dans phpstan-baseline.neon)
	$(PHP) vendor/bin/phpstan analyse --memory-limit=2G

.PHONY: analyse-baseline
analyse-baseline: ## Régénère phpstan-baseline.neon (après revue des erreurs restantes)
	$(PHP) vendor/bin/phpstan analyse --memory-limit=2G --generate-baseline --allow-empty-baseline

.PHONY: test-static
test-static: ## Contrôles statiques du projet (tirets, clés i18n, palette carte, routes), sans base
	$(PHP) artisan test tests/Unit/Static

.PHONY: check-front
check-front: ## Front : ESLint (bugs JS/Svelte) + svelte-check (JSDoc, exceptions svelte-check-baseline.json)
	$(PNPM) lint:js
	$(PNPM) check

.PHONY: check
check: ## Mini-CI locale : Pint (fichiers modifiés), i18n, analyse PHP, contrôles front, tests
	$(PHP) vendor/bin/pint --test --dirty
	$(PHP) artisan vff:i18n-check
	@$(MAKE) --no-print-directory analyse
	@$(MAKE) --no-print-directory check-front
	$(PHP) artisan test --parallel --processes=$(PROCESSES)

# ---------- Aide ----------

.PHONY: help
help: ## Affiche cette aide
	@awk 'BEGIN {FS = ":.*?## "; printf "\n\033[1mVFF - Commandes disponibles\033[0m\n\n"} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ""
