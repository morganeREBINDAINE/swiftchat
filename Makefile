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
