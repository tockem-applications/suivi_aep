#!/bin/bash
set -euo pipefail

VERSION=${1:-5.1.53}
SOURCE=mysql-${VERSION}.tar.gz
URL="https://downloads.mysql.com/archives/get/p/23/file/${SOURCE}"

if [ "${VERSION:0:4}" != "5.1." ]; then
    echo "Version MySQL non supportee (5.1.x requis) : ${VERSION}"
    exit 1
fi

mkdir -p /db /usr/local/src
cd /usr/local/src/

if [ ! -r "$SOURCE" ]; then
    wget -q "$URL" -O "$SOURCE"
fi

tar -xzf "$SOURCE"
cd "mysql-${VERSION}/"
./configure --prefix=/usr/local/mysql --localstatedir=/db --with-mysqld-user=mysql CXXFLAGS="-std=gnu++98"
make
make install
cp support-files/my-medium.cnf /etc/my.cnf
make clean

cd /usr/local/mysql/
chown -R root:mysql .
chown -R mysql:mysql /db
