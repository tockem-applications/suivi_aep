#!/bin/bash
set -e

mkdir -p /var/www/html/backups
# Apache (kochanup) tourne sous "daemon", pas root — rendre backups inscriptible
APACHE_USER="daemon"
if id "$APACHE_USER" >/dev/null 2>&1; then
    chown -R "$APACHE_USER:$APACHE_USER" /var/www/html/backups
fi
chmod 0777 /var/www/html/backups

DEV_INI="/opt/php/lib/php.d/docker-dev.ini"
if [ -f "$DEV_INI" ] && ! grep -q "docker-dev.ini loaded" /opt/php/lib/php.ini 2>/dev/null; then
    echo "; docker-dev.ini loaded" >> /opt/php/lib/php.ini
    cat "$DEV_INI" >> /opt/php/lib/php.ini
fi

echo "=== Stack legacy Docker ==="
/opt/apache-2.2/bin/httpd -v 2>/dev/null | head -1 || true
/opt/php/bin/php -v 2>/dev/null | head -1 || true
echo "==========================="

exec "$@"
