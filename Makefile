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

# Production targets — requires env vars to be set (see deploy instructions)
prod-build:
	docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache

prod-up:
	docker compose -f compose.yaml -f compose.prod.yaml up -d --wait

prod-down:
	docker compose -f compose.yaml -f compose.prod.yaml down

prod-logs:
	docker compose -f compose.yaml -f compose.prod.yaml logs -f
