#!/bin/bash

set -e

DIRNAME=$(dirname "$0")

bash "${DIRNAME}/install-wp-tests.sh" wordpress_test root root 127.0.0.1 latest
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
