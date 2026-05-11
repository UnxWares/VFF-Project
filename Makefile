# VFF — Raccourcis dev
# Usage : `make help` pour la liste des cibles
#
# L'app tourne sur l'hôte (composer run dev). Docker n'héberge que Postgres et Valkey.

.DEFAULT_GOAL := help
SHELL := /bin/bash

DC := docker compose

# ---------- Setup initial ----------

.PHONY: setup
setup: ## Premier démarrage : env, services data, deps, migrations
	@[ -f .env ] || cp .env.example .env
	$(DC) up -d
	@echo "→ Attente que la DB soit prête…"
	@until $(DC) exec -T db pg_isready -U vff -d vff > /dev/null 2>&1; do sleep 1; done
	pnpm install
	composer install
	php artisan key:generate
	php artisan migrate
	@echo ""
	@echo "✓ VFF prêt. Lance : make dev"
	@echo "  Adminer : http://localhost:8080  (serveur=db utilisateur=vff mdp=vff)"

.PHONY: dev
dev: ## Lance le dev complet (serve + queue + pail + vite)
	composer run dev

# ---------- Services Docker (Postgres + Valkey + Adminer) ----------

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

# ---------- Composer / pnpm / Artisan (sur l'hôte) ----------

.PHONY: install
install: ## composer install + pnpm install
	composer install
	pnpm install

.PHONY: update
update: ## composer update + pnpm update
	composer update
	pnpm update

.PHONY: artisan
artisan: ## Lance artisan avec args. Ex : make artisan cmd="make:model Line"
	php artisan $(cmd)

.PHONY: tinker
tinker: ## REPL Laravel
	php artisan tinker

.PHONY: migrate
migrate: ## Exécute les migrations
	php artisan migrate

.PHONY: migrate-fresh
migrate-fresh: ## Reset complet de la DB + seed
	php artisan migrate:fresh --seed

.PHONY: seed
seed: ## Exécute les seeders
	php artisan db:seed

# ---------- Qualité ----------

.PHONY: pint
pint: ## Formate le code PHP (Pint)
	./vendor/bin/pint

.PHONY: lint
lint: ## Vérifie le formatage front
	pnpm lint

.PHONY: format
format: ## Formate le front (Prettier)
	pnpm format

.PHONY: test
test: ## Lance la suite de tests
	php artisan test

# ---------- Aide ----------

.PHONY: help
help: ## Affiche cette aide
	@awk 'BEGIN {FS = ":.*?## "; printf "\n\033[1mVFF — Commandes disponibles\033[0m\n\n"} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ""
