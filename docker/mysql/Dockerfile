# MySQL 5.1.53 compile depuis les sources (archive MySQL)
ARG MYSQL_VERSION=5.1.53

FROM ubuntu:22.04 AS builder
ARG MYSQL_VERSION
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        build-essential libncurses5-dev bison wget ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd -r mysql && useradd -r -g mysql mysql

COPY compile_mysql51.sh /root/compile_mysql51.sh
RUN sed -i 's/\r$//' /root/compile_mysql51.sh \
    && chmod +x /root/compile_mysql51.sh \
    && bash /root/compile_mysql51.sh "${MYSQL_VERSION}"

FROM ubuntu:22.04
ARG MYSQL_VERSION
LABEL org.opencontainers.image.title="suivi-reseau-mysql" \
      org.opencontainers.image.description="MySQL ${MYSQL_VERSION} pour stack legacy WAMP"

ENV DEBIAN_FRONTEND=noninteractive \
    MYSQL_VERSION=${MYSQL_VERSION} \
    HOSTNAME=db \
    PATH=/usr/local/mysql/bin:$PATH

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        locales tzdata wget ca-certificates \
    && locale-gen en_US.UTF-8 \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd -r mysql && useradd -r -g mysql mysql

COPY --from=builder /usr/local/mysql /usr/local/mysql
COPY --from=builder /etc/my.cnf /etc/my.cnf
COPY start.sh /root/start.sh

RUN sed -i 's/\r$//' /root/start.sh \
    && chmod +x /root/start.sh \
    && mkdir -p /db /docker-entrypoint-initdb.d \
    && chown -R mysql:mysql /db

VOLUME ["/db"]
EXPOSE 3306

ENTRYPOINT ["/root/start.sh"]
CMD ["mysqld_safe"]
