#!/bin/bash

set -e

DIRNAME=$(dirname "$0")

bash "${DIRNAME}/install-wp-tests.sh" wordpress_test root root 127.0.0.1 latest
echo "Running PHPUnit on Single Site"
composer phpunit
rm -rf "$WP_TESTS_DIR" "$WP_CORE_DIR"

bash "${DIRNAME}/install-wp-tests.sh" wordpress_test root root 127.0.0.1 nightly true
echo "Running PHPUnit on Single Site (Nightly WordPress)"
composer phpunit

bash "${DIRNAME}/install-wp-tests.sh" wordpress_test root root 127.0.0.1 latest true
echo "Running PHPUnit on Multisite"
WP_MULTISITE=1 composer phpunit
