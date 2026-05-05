## Project Bootstrap

This project uses **[`dunglas/symfony-docker`](https://github.com/dunglas/symfony-docker)** as its base, which provides a production-ready Docker stack out of the box.

### What this stack gives us for free
- **FrankenPHP** as the PHP runtime (replaces nginx + php-fpm)
- **Caddy** as the web server with automatic SSL
- **Mercure Hub built-in** — no separate Mercure service needed; it runs inside FrankenPHP
- **PostgreSQL** service pre-configured
- Hot reload in dev, optimized builds for prod

### Initial setup (already done)
```bash
git clone https://github.com/dunglas/symfony-docker.git swiftchat
cd swiftchat
docker compose up --build   # bootstraps the full stack + creates Symfony app
```

### Important: do NOT
- Install FrankenPHP or Caddy manually — they are managed by the Docker stack
- Add a separate Mercure service in `docker-compose.yml` — it is already built into FrankenPHP
- Modify `Caddyfile` or `frankenphp/` config unless strictly necessary

### Daily dev workflow
```bash
docker compose up           # start the stack
docker compose exec php bin/console ...   # run Symfony commands
docker compose exec php composer require ...
docker compose down         # stop
```

---

## Technical Decisions

| Topic         | Decision           |
|---------------|--------------------|
| JS frontend   | Stimulus           |
| Avatar upload | VichUploaderBundle |
| CSS           | Bootstrap 5        |
