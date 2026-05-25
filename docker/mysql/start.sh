#!/bin/bash
set -e

HOSTNAME=${HOSTNAME:-mysql}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-root}
MYSQL_DATABASE=${MYSQL_DATABASE:-suivi_aep_fokoue}
MYSQL_USER=${MYSQL_USER:-suivi}
MYSQL_PASSWORD=${MYSQL_PASSWORD:-suivi}

if ! grep -q "$HOSTNAME" /etc/hosts; then
    echo "127.0.0.1 $HOSTNAME" >> /etc/hosts
fi

chown -R mysql:mysql /db

if ! ls -1 /db/* 1>/dev/null 2>&1; then
    echo "[mysql] Initialisation du datadir..."
    mysql_install_db --user=mysql

    echo "[mysql] Demarrage temporaire..."
    mysqld_safe --skip-networking &
    sleep 8

    mysql -uroot mysql <<-EOS
DELETE FROM mysql.user WHERE Host IN ('localhost', '${HOSTNAME}');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOS

    if [ -n "$MYSQL_DATABASE" ]; then
        mysql -uroot -e "CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\` CHARACTER SET utf8 COLLATE utf8_general_ci;"
    fi

    if [ -n "$MYSQL_USER" ] && [ -n "$MYSQL_PASSWORD" ]; then
        mysql -uroot -e "GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}'; FLUSH PRIVILEGES;"
    fi

    if [ -d /docker-entrypoint-initdb.d ]; then
        for f in /docker-entrypoint-initdb.d/*.sql; do
            [ -f "$f" ] || continue
            echo "[mysql] Import ${f}..."
            mysql -uroot "$MYSQL_DATABASE" < "$f"
        done
    fi

    mysqladmin -uroot shutdown 2>/dev/null || true
    sleep 5
fi

exec "$@"
