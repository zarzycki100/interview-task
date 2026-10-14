#!/usr/bin/env bash
set -euo pipefail

cp -n .env.example .env
touch database/database.sqlite

export WWWUSER="${WWWUSER:-$(id -u)}"
export WWWGROUP="${WWWGROUP:-$(id -g)}"

docker compose up --build --remove-orphans -d
docker compose exec -T -u sail app composer install

docker compose exec -T -u sail app php artisan migrate:fresh --seed
