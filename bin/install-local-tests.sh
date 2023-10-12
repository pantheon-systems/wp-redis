#!/bin/bash
set -e

# Initialize variables with default values
TMPDIR="/tmp"
DB_NAME="wordpress_test"
DB_USER="root"
DB_PASS=""
DB_HOST="127.0.0.1"
WP_VERSION="latest"
SKIP_DB=""

# Display usage information
usage() {
  echo "Usage:"
  echo "./install-local-tests.sh [--dbname=wordpress_test] [--dbuser=root] [--dbpass=''] [--dbhost=127.0.0.1] [--wpversion=latest] [--no-db]"
}

# Parse command-line arguments
for i in "$@"
do
case $i in
    --dbname=*)
    DB_NAME="${i#*=}"
    shift
    ;;
    --dbuser=*)
    DB_USER="${i#*=}"
    shift
    ;;
    --dbpass=*)
    DB_PASS="${i#*=}"
    shift
    ;;
    --dbhost=*)
    DB_HOST="${i#*=}"
    shift
    ;;
    --wpversion=*)
    WP_VERSION="${i#*=}"
    shift
    ;;
    --no-db)
    SKIP_DB="true"
    shift
    ;;
    *)
    # unknown option
    usage
    exit 1
    ;;
esac
done

# Run install-wp-tests.sh
echo "Installing local tests into ${TMPDIR}"
bash "$(dirname "$0")/install-wp-tests.sh" "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION" "$SKIP_DB"

# Run PHPUnit
echo "Running PHPUnit"
composer phpunit
