SHELL := /bin/sh

APP_NAME := Waflow
COMPOSE_FILE := infra/compose.yml
ENV_FILE := infra/.env
ENV_EXAMPLE := infra/.env.example
HTTP_PORT ?= 8080
SERVICE ?= laravel
CMD ?=

COMPOSE := docker compose --env-file $(ENV_FILE) -f $(COMPOSE_FILE)
LARAVEL_DIR := laravel
ARTISAN := $(COMPOSE) exec -T laravel php artisan

.DEFAULT_GOAL := help

.PHONY: help init env up start stop down restart build rebuild ps logs shell \
	artisan migrate seed queue-restart composer-install npm-install npm-dev \
	npm-build test pint lint quality health compose-config clean-build destroy \
	test-postgres e2e-live

help: ## Lista os comandos disponíveis
	@printf '%s\n' "$(APP_NAME) make targets:"
	@awk 'BEGIN {FS = ":.*## "} /^[a-zA-Z0-9_-]+:.*## / {printf "  %-18s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

init: ## Cria infra/.env com segredos locais se ainda não existir
	@if [ ! -f "$(ENV_FILE)" ]; then ./infra/scripts/init-env.sh; else printf '%s\n' "$(ENV_FILE) já existe."; fi

env: init ## Alias para init

up: init ## Sobe o stack com build das imagens
	$(COMPOSE) up -d --build

start: init ## Sobe o stack sem rebuild forçado
	$(COMPOSE) up -d

stop: ## Para containers sem remover rede/volumes
	$(COMPOSE) stop

down: ## Remove containers e rede, preservando volumes
	$(COMPOSE) down

restart: ## Reinicia o stack preservando volumes
	$(COMPOSE) restart

build: init ## Build das imagens
	$(COMPOSE) build

rebuild: init ## Build sem cache das imagens
	$(COMPOSE) build --no-cache

ps: ## Mostra status dos containers
	$(COMPOSE) ps

logs: ## Mostra logs. Use SERVICE=gateway, laravel, openwa, etc.
	$(COMPOSE) logs -f --tail=200 $(SERVICE)

shell: ## Abre shell no serviço. Use SERVICE=laravel por padrão
	$(COMPOSE) exec $(SERVICE) sh

artisan: ## Executa artisan. Use CMD="route:list"
	@test -n "$(CMD)" || (printf '%s\n' 'Informe CMD="..."'; exit 1)
	$(ARTISAN) $(CMD)

migrate: ## Executa migrations no container Laravel
	$(ARTISAN) migrate --force --no-interaction

seed: ## Executa seeders no container Laravel
	$(ARTISAN) db:seed --force --no-interaction

queue-restart: ## Reinicia workers Laravel
	$(ARTISAN) queue:restart

composer-install: ## Instala dependências PHP locais do Laravel
	cd $(LARAVEL_DIR) && composer install --no-interaction

npm-install: ## Instala dependências Node locais do Laravel
	cd $(LARAVEL_DIR) && npm install

npm-dev: ## Inicia Vite dev server com livereload
	cd $(LARAVEL_DIR) && npm run dev

npm-build: ## Compila assets Vite
	cd $(LARAVEL_DIR) && npm run build

test: ## Executa testes Laravel
	cd $(LARAVEL_DIR) && php artisan test

test-postgres: ## Executa a suíte completa em PostgreSQL 16 isolado
	./infra/scripts/test-postgres.sh

pint: ## Executa Laravel Pint em modo check
	cd $(LARAVEL_DIR) && vendor/bin/pint --test

lint: ## Valida composer e formatação PHP
	cd $(LARAVEL_DIR) && composer validate --strict
	cd $(LARAVEL_DIR) && vendor/bin/pint --test

quality: npm-build test lint compose-config ## Executa validação principal do projeto

health: ## Consulta health check público
	curl -fsS http://localhost:$(HTTP_PORT)/api/v1/health

e2e-live: ## Envia mensagem real e testa concorrência (requer variáveis WAFLOW_E2E_*)
	./infra/scripts/e2e-live.sh

compose-config: init ## Valida a configuração Docker Compose
	$(COMPOSE) config --quiet

clean-build: ## Remove build Vite local
	rm -rf $(LARAVEL_DIR)/public/build

destroy: ## PERIGOSO: remove containers e volumes do stack
	@printf '%s\n' 'Isto apagará volumes Docker do Waflow.'
	@printf '%s' 'Digite "destroy" para continuar: '
	@read answer; [ "$$answer" = "destroy" ] || (printf '%s\n' 'Cancelado.'; exit 1)
	$(COMPOSE) down -v
