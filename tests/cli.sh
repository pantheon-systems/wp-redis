#!/bin/bash
set -euo pipefail
IFS=$'\n\t'

##
# (pwt) 
# TODO: In the future I would like this to be a Behat test built over WP-CLI's behat test suite
#       but getting that in place was a lot of work to reproduce what I was looking for at the time.
##

SELF_DIRNAME="$(dirname -- "$0")"
readonly TEST_SITE_URL="https://redistest.com"

# shellcheck disable=SC2155
WP_WORKSPACE=$(mktemp -d /tmp/wpworkspace_XXXXXXX)

# Trap any exit and print the temporary workspace path back to the terminal
trap 'echo "

Working Directory: ${WP_WORKSPACE}"' EXIT

##
# Create an empty WordPress install
##
setup_wp_workspace(){
	wp core download --path="${WP_WORKSPACE}"
	create_wp_config "$@"
	install_wordpress
}

##
# Create a wp-config.php file for testing.
##
create_wp_config(){
	if [ $# -lt 3 ]; then
		echo "usage: $0 <db-name> <db-user> <db-pass> [db-host]"
		exit 1
	fi
	local DB_NAME="${1:-}"
	local DB_USER="${2:-}"
	local DB_PASS="${3:-}"
	local DB_HOST="${4-localhost}"

	if [[ -f "${WP_WORKSPACE}/wp-config.php" ]];then
		rm "${WP_WORKSPACE}/wp-config.php"
	fi
	wp config create --dbname="${DB_NAME}" \
		--path="${WP_WORKSPACE}" \
		--dbuser="${DB_USER}" \
		--dbpass="${DB_PASS}" \
		--dbhost="${DB_HOST}"
}

##
# Drops the test database and installs WordPress
##
install_wordpress() {
	wp db reset --yes --path="${WP_WORKSPACE}"
	wp core install \
		--path="${WP_WORKSPACE}" \
		--url="${TEST_SITE_URL}" \
		--title="Search Replace Test" \
		--admin_user=admin \
		--admin_email=admin@admin.com \
		--admin_password=password \
		--skip-email
}

main(){
	local DB_NAME="${1:-}"
	local DB_USER="${2:-}"
	local DB_PASS="${3:-}"
	local DB_HOST="${4-localhost}"
	if [[ "${DB_NAME}" == "" || "${DB_USER}" == "" ]]; then
		echo "usage: $0 <db-name> <db-user> <db-pass> [db-host]"
		exit 1
	fi


	echo "Working Directory: ${WP_WORKSPACE}"
	echo "Installing WordPress"
	setup_wp_workspace "${DB_NAME}" "${DB_USER}" "${DB_PASS}" "${DB_HOST}"

	mkdir -p "${WP_WORKSPACE}/wp-content/plugins/wp-redis"
	rsync -ar "${SELF_DIRNAME}/.." "${WP_WORKSPACE}/wp-content/plugins/wp-redis"

	cd "${WP_WORKSPACE}"
	wp plugin list
	wp plugin activate wp-redis
	wp redis enable 
	wp redis info
	wp cache flush
}

main "$@"