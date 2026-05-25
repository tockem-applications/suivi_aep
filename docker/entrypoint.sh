#!/bin/bash
set -e

mkdir -p /var/www/html/backups

echo "=== Stack legacy Docker ==="
/opt/apache-2.2/bin/httpd -v 2>/dev/null | head -1 || true
/opt/php/bin/php -v 2>/dev/null | head -1 || true
echo "==========================="

exec "$@"
