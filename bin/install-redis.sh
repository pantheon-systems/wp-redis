#!/usr/bin/env bash

set -ex

# Install redis
install_redis() {

	apt-get install redis-server

}

# Install phpredis
install_phpredis() {

	apt-get install php5-dev

	git clone https://github.com/nicolasff/phpredis.git

	cd phpredis

	phpize
	./configure
	make && make install

}

install_redis
install_phpredis

service php5-fpm restart
