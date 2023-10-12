#!/bin/bash

set -e

DIRNAME=$(dirname "$0")

WITHDB="true"

function database_exists {
  # Usage: database_exists <database_name> <user> <password> <host>
  local dbname=$1
  local dbuser=$2
  local dbpass=$3
  local dbhost=$4
  echo "SHOW DATABASES LIKE '${dbname}';" | mysql -u"${dbuser}" -p"${dbpass}" -h"${dbhost}" 2>/dev/null | grep "${dbname}" > /dev/null
  return $?
}

# Check if the database exists
if database_exists wordpress_test root root 127.0.0.1; then
  echo "Database wordpress_test already exists, we won't install a new one..."
  WITHDB=""
fi

bash "${DIRNAME}/install-wp-tests.sh" wordpress_test root root 127.0.0.1 latest "$WITHDB"
echo "Running WP Redis PHPUnit Tests on Single Site"
WP_REDIS_USE_CACHE_GROUPS=1 composer phpunit
rm -rf "$WP_TESTS_DIR" "$WP_CORE_DIR"

bash "${DIRNAME}/install-wp-tests.sh" wordpress_test root root 127.0.0.1 nightly true
echo "Running WP Redis PHPUnit Tests on Single Site (Nightly WordPress)"
WP_REDIS_USE_CACHE_GROUPS=1 composer phpunit
composer phpunit

bash "${DIRNAME}/install-wp-tests.sh" wordpress_test root root 127.0.0.1 latest true
echo "Running WP Redis PHPUnit Tests on Multisite"
WP_MULTISITE=1 WP_REDIS_USE_CACHE_GROUPS=1 composer phpunit
