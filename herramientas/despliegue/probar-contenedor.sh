#!/usr/bin/env bash
set -euo pipefail
umask 077

image="geosur-web:revision"
if [ "$#" -gt 0 ]; then image="$1"; fi
identifier="geosur49-$RANDOM-$$"
network="$identifier-network"
volume="$identifier-storage"
database_volume="$identifier-database"
postgres="$identifier-postgres"
web="$identifier-web"
recovered_volume="$identifier-recovered-storage"
recovered_database_volume="$identifier-recovered-database"
recovered_postgres="$identifier-recovered-postgres"
export POSTGRES_PASSWORD DB_PASSWORD APP_KEY
POSTGRES_PASSWORD="$(openssl rand -hex 24)"
DB_PASSWORD="$POSTGRES_PASSWORD"
APP_KEY="base64:$(openssl rand -base64 32)"
temporary="$(mktemp -d)"

cleanup() {
    docker rm -f "$web" "$identifier-postgres" "$recovered_postgres" >/dev/null 2>&1 || true
    docker volume rm "$identifier-storage" "$identifier-database" \
        "$recovered_volume" "$recovered_database_volume" >/dev/null 2>&1 || true
    docker network rm "$network" >/dev/null 2>&1 || true
    rm -rf "$temporary"
}
trap cleanup EXIT

docker network create "$network" >/dev/null
docker volume create "$volume" >/dev/null
docker volume create "$database_volume" >/dev/null

start_postgres() {
    docker run -d --name "$postgres" --network "$network" --network-alias postgres \
        --env POSTGRES_PASSWORD --env POSTGRES_DB=geosur_revision \
        --mount "source=$database_volume,target=/var/lib/postgresql/data" \
        postgres:16-bookworm >/dev/null
    for attempt in {1..30}; do
        if docker exec "$postgres" pg_isready -h 127.0.0.1 -U postgres -d geosur_revision >/dev/null 2>&1; then
            return
        fi
        sleep 1
    done
    echo "PostgreSQL no inició dentro del tiempo de la prueba." >&2
    exit 1
}

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

check_http() {
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
}

start_postgres
start_web
docker exec "$web" php artisan migrate --force
check_http
docker exec "$web" test ! -e /var/www/geosur/web/.env
docker exec "$web" test ! -d /var/www/geosur/referencias
docker exec "$web" php -r 'if (!extension_loaded("pdo_pgsql") || !extension_loaded("gd")) exit(1);'
docker exec "$web" sh -c 'printf "persistencia49\n" > storage/app/private/prueba49.txt'
docker exec -i "$postgres" psql -U postgres -d geosur_revision -v ON_ERROR_STOP=1 <<'SQL'
CREATE TABLE revision_respaldo49 (id integer PRIMARY KEY, mensaje text NOT NULL);
INSERT INTO revision_respaldo49 VALUES (1, 'recuperacion49');
SQL

# Confirma que la APP_KEY conservada puede leer un archivo cifrado tras recuperar.
docker exec -i "$web" php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents(storage_path('app/private/cifrado49.txt'),
    Illuminate\Support\Facades\Crypt::encryptString('clave-conservada49'));
PHP

docker rm -f "$web" >/dev/null
start_web
docker exec "$web" grep -qx persistencia49 storage/app/private/prueba49.txt
docker exec "$web" php artisan migrate:status
echo "Reemplazo del contenedor con el mismo volumen comprobado."

# Detiene al único escritor antes de copiar base y archivos de esta prueba.
docker stop "$web" >/dev/null
original_cluster="$(docker exec "$postgres" psql -U postgres -d geosur_revision -tAc 'SELECT system_identifier FROM pg_control_system()')"
docker exec "$postgres" pg_dump -U postgres -d geosur_revision \
    --format=custom --no-owner --no-acl > "$temporary/base.dump"
docker run --rm --network none \
    --mount "source=$volume,target=/respaldo,readonly" \
    "$image" tar -C /respaldo -czf - . > "$temporary/storage.tar.gz"
test -s "$temporary/base.dump"
test -s "$temporary/storage.tar.gz"
(
    cd "$temporary"
    sha256sum base.dump storage.tar.gz > SHA256SUMS
    sha256sum --check SHA256SUMS
)

# No se reutilizan ni la base ni los volúmenes originales al recuperar.
docker rm -f "$web" "$postgres" >/dev/null
docker volume rm "$volume" "$database_volume" >/dev/null
postgres="$recovered_postgres"
volume="$recovered_volume"
database_volume="$recovered_database_volume"
docker volume create "$volume" >/dev/null
docker volume create "$database_volume" >/dev/null
start_postgres
new_cluster="$(docker exec "$postgres" psql -U postgres -d geosur_revision -tAc 'SELECT system_identifier FROM pg_control_system()')"
test "$new_cluster" != "$original_cluster"
test "$(docker exec "$postgres" psql -U postgres -d geosur_revision -tAc "SELECT to_regclass('public.revision_respaldo49') IS NULL")" = t

docker exec -i "$postgres" pg_restore -U postgres -d geosur_revision \
    --exit-on-error --single-transaction --no-owner --no-acl < "$temporary/base.dump"
docker run --rm -i --network none \
    --mount "source=$volume,target=/recuperacion" \
    "$image" tar -C /recuperacion -xzf - < "$temporary/storage.tar.gz"
test "$(docker exec "$postgres" psql -U postgres -d geosur_revision -tAc 'SELECT mensaje FROM revision_respaldo49 WHERE id = 1')" = recuperacion49
start_web
check_http
docker exec "$web" grep -qx persistencia49 storage/app/private/prueba49.txt
docker exec "$web" php artisan migrate:status
docker exec -i "$web" php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$contenido = file_get_contents(storage_path('app/private/cifrado49.txt'));
if (Illuminate\Support\Facades\Crypt::decryptString($contenido) !== 'clave-conservada49') {
    throw new RuntimeException('No se recuperó el archivo con la clave original.');
}
PHP
echo "Copia y recuperación en PostgreSQL y volúmenes nuevos comprobadas."
echo "Salud, login, catálogo, migraciones, registro y archivo cifrado recuperados."
