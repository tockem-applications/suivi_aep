#!/bin/sh
# Import App_vide.sql si la table aep est absente (image vsamov n'utilise pas docker-entrypoint-initdb.d)
set -e

HOST="${DB_INIT_HOST:-db}"
ROOT_PW="${MYSQL_ROOT_PASSWORD:-root}"
DB_NAME="${DB_NAME:-suivi_aep_fokoue}"
SCHEMA="${DB_INIT_SCHEMA:-/schema/App_vide.sql}"

if [ ! -f "$SCHEMA" ]; then
    echo "[db-init] ERREUR: fichier introuvable: $SCHEMA"
    echo "[db-init] Placez app-setup/App_vide.sql dans le projet."
    exit 1
fi

echo "[db-init] Attente MySQL ($HOST)..."
i=0
while [ "$i" -lt 60 ]; do
    if mysql -h "$HOST" -uroot -p"$ROOT_PW" -e "SELECT 1" >/dev/null 2>&1; then
        break
    fi
    i=$((i + 1))
    sleep 2
done

if ! mysql -h "$HOST" -uroot -p"$ROOT_PW" -e "SELECT 1" >/dev/null 2>&1; then
    echo "[db-init] ERREUR: MySQL injoignable sur $HOST"
    exit 1
fi

COUNT=$(mysql -h "$HOST" -uroot -p"$ROOT_PW" -N -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='aep';" 2>/dev/null || echo "0")

FORCE="${DB_INIT_FORCE:-0}"
if [ "$COUNT" = "1" ] && [ "$FORCE" != "1" ]; then
    echo "[db-init] Schema deja present (table aep). Rien a faire."
    exit 0
fi

echo "[db-init] Import de $SCHEMA dans ${DB_NAME}..."
mysql -h "$HOST" -uroot -p"$ROOT_PW" < "$SCHEMA"
echo "[db-init] Import termine."
