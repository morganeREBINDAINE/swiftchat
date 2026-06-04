up:
	docker compose up -d

down:
	docker compose down

php:
	docker exec -ti symfony-php-1 bash

perm:
	docker compose exec php chown -R 1000:1000 /app

fixtures:
	docker compose exec php php bin/console d:f:l --no-interaction

install-hooks:
	cp scripts/pre-commit.sh .git/hooks/pre-commit
	chmod +x .git/hooks/pre-commit
	@echo "Git hooks installed."

# Production targets — reads secrets from .env.prod.local (never committed)
PROD_COMPOSE=docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local

prod-build:
	$(PROD_COMPOSE) build --pull --no-cache

prod-up:
	$(PROD_COMPOSE) up -d --wait

prod-down:
	$(PROD_COMPOSE) down

prod-logs:
	$(PROD_COMPOSE) logs -f
