#!/bin/sh
set -eu

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
MYSQL_SERVICE="${MYSQL_SERVICE:-mysql}"
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
ROOT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
SQL_FILE="$ROOT_DIR/docker/mysql/fix-existing-volume-auth.sql"

if [ ! -f "$SQL_FILE" ]; then
    echo "No se encontro el archivo SQL: $SQL_FILE" >&2
    exit 1
fi

if ! docker compose -f "$COMPOSE_FILE" ps "$MYSQL_SERVICE" >/dev/null 2>&1; then
    echo "No se pudo encontrar el servicio $MYSQL_SERVICE en $COMPOSE_FILE" >&2
    exit 1
fi

echo "Aplicando compatibilidad mysql_native_password en $MYSQL_SERVICE usando $COMPOSE_FILE..."
docker compose -f "$COMPOSE_FILE" exec -T "$MYSQL_SERVICE" sh -lc 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' < "$SQL_FILE"
echo "Correccion aplicada correctamente."
