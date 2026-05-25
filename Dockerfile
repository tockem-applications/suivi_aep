# Image eprouvee : Apache 2.2 + PHP 5.3.17 (kochanup)
# Proche de WAMP PHP 5.3.4 / Apache 2.2.17 — build rapide (~1 min)
FROM kochanup/apache-2.2-php-5.3

LABEL org.opencontainers.image.title="suivi-reseau-web" \
      org.opencontainers.image.description="Apache 2.2 + PHP 5.3 (legacy)"

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /var/www/html/backups

WORKDIR /var/www/html
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/opt/apache-2.2/bin/apachectl", "-D", "FOREGROUND"]
