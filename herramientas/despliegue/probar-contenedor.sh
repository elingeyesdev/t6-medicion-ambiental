#!/usr/bin/env bash
set -euo pipefail

image="${1:-geosur-web:revision}"
identifier="geosur49-${RANDOM}-$$"
network="$identifier-network"
volume="$identifier-storage"
postgres="$identifier-postgres"
web="$identifier-web"
export POSTGRES_PASSWORD DB_PASSWORD APP_KEY
POSTGRES_PASSWORD="$(openssl rand -hex 24)"
DB_PASSWORD="$POSTGRES_PASSWORD"
APP_KEY="base64:$(openssl rand -base64 32)"
temporary="$(mktemp -d)"

cleanup() {
    docker rm -f "$web" "$postgres" >/dev/null 2>&1 || true
    docker volume rm "$volume" >/dev/null 2>&1 || true
    docker network rm "$network" >/dev/null 2>&1 || true
    rm -rf "$temporary"
}
trap cleanup EXIT

docker network create "$network" >/dev/null
docker volume create "$volume" >/dev/null
docker run -d --name "$postgres" --network "$network" --network-alias postgres \
    --env POSTGRES_PASSWORD --env POSTGRES_DB=geosur_revision \
    postgres:16-bookworm >/dev/null
ready=false
for attempt in {1..30}; do
    if docker exec "$postgres" pg_isready -U postgres -d geosur_revision >/dev/null 2>&1; then
        ready=true
        break
    fi
    sleep 1
done
if [ "$ready" != true ]; then
    echo "PostgreSQL no inició dentro del tiempo de la prueba." >&2
    exit 1
fi

start_web() {
    docker run -d --name "$web" --network "$network" \
        --publish 127.0.0.1::8080 \
        --env APP_KEY --env DB_PASSWORD \
        --env DB_HOST=postgres --env DB_PORT=5432 \
        --env DB_DATABASE=geosur_revision --env DB_USERNAME=postgres \
        --env APP_URL=http://127.0.0.1 --env PORT=8080 \
        --mount "source=$volume,target=/var/www/geosur/web/storage" \
        "$image" >/dev/null
    binding="$(docker port "$web" 8080/tcp)"
    url="http://$binding"
    if ! curl --fail --silent --show-error --retry 20 --retry-delay 1 \
        --retry-all-errors "$url/api/salud" > "$temporary/salud.json"; then
        docker logs "$web"
        exit 1
    fi
}

start_web
docker exec "$web" php artisan migrate --force
curl --fail --silent --show-error "$url/login" > "$temporary/login.html"
curl --fail --silent --show-error "$url/api/v1/territorios" > "$temporary/territorios.json"
python3 - "$temporary" <<'PY'
import json
from pathlib import Path
import sys

directory = Path(sys.argv[1])
assert json.loads((directory / "salud.json").read_text())["estado"] == "disponible"
assert 'name="_token"' in (directory / "login.html").read_text()
catalogue = json.loads((directory / "territorios.json").read_text())
assert isinstance(catalogue, dict) and catalogue.get("datos"), "Catálogo vacío."
PY
docker exec "$web" test ! -e /var/www/geosur/web/.env
docker exec "$web" test ! -d /var/www/geosur/referencias
docker exec "$web" php -r 'if (!extension_loaded("pdo_pgsql") || !extension_loaded("gd")) exit(1);'
docker exec "$web" sh -c 'printf "persistencia49\n" > storage/app/private/prueba49.txt'
docker rm -f "$web" >/dev/null
start_web
docker exec "$web" grep -qx persistencia49 storage/app/private/prueba49.txt
docker exec "$web" php artisan migrate:status
echo "Construcción, PostgreSQL, salud, login, catálogo y persistencia comprobados."
