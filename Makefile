up:
	docker compose up -d

down:
	docker compose down

php:
	docker exec -ti symfony-php-1 bash
