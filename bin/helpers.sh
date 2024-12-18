#!/usr/bin/env bash

# Arbitrary download function that uses wget or curl depending on what's available.
download() {
    if which curl &> /dev/null; then  
        curl -s "$1" > "$2";  
    elif which wget &> /dev/null; then  
        wget -nv -O "$2" "$1"  
    else  
        echo "Missing curl or wget" >&2  
        exit 1  
    fi  
}

# Download WordPress with wp-cli. Always forces the download of files to overwrite existing ones.
download_wp() {
	local TMPDIR="/tmp"
	local WP_VERSION="latest"

	for i in "$@"; do
		case $i in
			--version=*)
			WP_VERSION="${i#*=}"
			;;
			--tmpdir=*)
			TMPDIR="${i#*=}"
			;;
			*)
			# unknown option
			echo "Unknown option: $i. Usage: download_wp --version=latest --tmpdir=/tmp"
			exit 1
			;;
		esac
	done

	# Check for WP-CLI. If the wp command does not exist, exit.
	if ! which wp &> /dev/null; then
		echo "WP-CLI is not installed. Exiting."
		exit 1
	fi

	echo "Downloading WordPress version: ${WP_VERSION} to ${TMPDIR}/wordpress"
	wp core download --version="$WP_VERSION" --path="${TMPDIR}/wordpress" --force
}

# Sets up WordPress using wp config create (if a wp-config file doesn't already exist) and wp core install. Expects that WordPress is already downloaded.
setup_wp() {
	# Initialize variables with default values
	local TMPDIR="/tmp"
	local DB_NAME="wordpress_test"
	local DB_USER="root"
	local DB_PASS=""
	local DB_HOST="127.0.0.1"
	local WP_VERSION=${WP_VERSION:-latest}

	# Parse command-line arguments
	for i in "$@"; do
		case $i in
			--dbname=*)
			DB_NAME="${i#*=}"
			;;
			--dbuser=*)
			DB_USER="${i#*=}"
			;;
			--dbpass=*)
			DB_PASS="${i#*=}"
			;;
			--dbhost=*)
			DB_HOST="${i#*=}"
			;;
			--version=*)
			WP_VERSION="${i#*=}"
			;;
			--tmpdir=*)
			TMPDIR="${i#*=}"
			;;
			*)
			# unknown option
			echo "Unknown option: $i. Usage: setup_wp --dbname=wordpress_test --dbuser=root --dbpass=root --dbhost=localhost --version=latest --tmpdir=/tmp"
			exit 1
			;;
		esac
	done

	download http://api.wordpress.org/core/version-check/1.7/ "$TMPDIR"/wp-latest.json

	if [ ! -f "$TMPDIR/wordpress/wp-config.php" ]; then
		echo "Creating wp-config.php"
		wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --dbprefix="wptests_" --path="${TMPDIR}/wordpress"
	fi
		
	wp core install --url=localhost --title=Test --admin_user=admin --admin_password=password --admin_email=test@dev.null --path="${TMPDIR}/wordpress"
}

# Sets up WordPress nightly version. Uses setup_wp to get WordPress, then installs Gutenberg on the recently installed WordPress site.
setup_wp_nightly() {
	# Initialize variables with default values
	local TMPDIR="/tmp"
	local DB_NAME="wordpress_test"
	local DB_USER="root"
	local DB_PASS=""
	local DB_HOST="127.0.0.1"

	# Parse command-line arguments
	for i in "$@"; do
		case $i in
			--dbname=*)
			DB_NAME="${i#*=}"
			;;
			--dbuser=*)
			DB_USER="${i#*=}"
			;;
			--dbpass=*)
			DB_PASS="${i#*=}"
			;;
			--dbhost=*)
			DB_HOST="${i#*=}"
			;;
			--tmpdir=*)
			TMPDIR="${i#*=}"
			;;
			*)
			# unknown option
			echo "Unknown option: $i. Usage: setup_wp --dbname=wordpress_test --dbuser=root --dbpass=root --dbhost=localhost --version=latest --tmpdir=/tmp"
			exit 1
			;;
		esac
	done

	WP_DIR="$TMPDIR/wordpress"

	setup_wp --version="nightly" --tmpdir="$TMPDIR" --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST"

	# If nightly version of WP is installed, install latest Gutenberg plugin and activate it.
	echo "Installing Gutenberg plugin"
	wp plugin install gutenberg --activate --path="$WP_DIR"
}

# Gets the WordPress version number from the JSON file. If the version is "latest", it will get the latest version from the JSON file. If the version is "nightly", it will return "trunk".
get_wp_version_num() {
	local WP_VERSION="latest"
	local TMPDIR="/tmp/wp-latest.json"

	for i in "$@"; do
		case $i in
			--version=*)
			WP_VERSION="${i#*=}"
			;;
			--tmpdir=*)
			TMPDIR="${i#*=}"
			;;
			*)
			# unknown option
			echo "Unknown option: $i. Usage: get_wp_version_num --version=latest --tmpdir=/tmp/wp-latest.json"
			exit 1
			;;
		esac
	done

	# Get latest version from JSON if latest was passed.
	WP_VERSION_JSON="${TMPDIR}/wp-latest.json"
	if [ "$WP_VERSION" == "latest" ]; then
		WP_VERSION=$(grep -o '"version":"[^"]*' "$WP_VERSION_JSON" | cut -d'"' -f4)
	fi

	if [ "$WP_VERSION" == "nightly" ]; then
		WP_VERSION="trunk"
	fi

	echo "$WP_VERSION"
}

# Installs the WordPress test suite over svn. Uses get_wp_version_num to get a version based on what's passed that the svn repository will recognize.
install_test_suite() {
	local WP_VERSION=${1:-"latest"}
	local TMPDIR=${2:-"/tmp"}
	local DB_NAME=${3:-"wordpress_test"}
	local DB_USER=${4:-"root"}
	local DB_PASS=${5:-""}
	local DB_HOST=${6:-"127.0.0.1"}
	local WP_TESTS_DIR=${WP_TESTS_DIR-"$TMPDIR/wordpress-tests-lib"}
	WP_VERSION=$(get_wp_version_num --version="$WP_VERSION" --tmpdir="$TMPDIR")

	# If we're using trunk, there is no tests tag.
	if [ "$WP_VERSION" == "trunk" ]; then
		WP_TESTS_TAG="trunk"
	else
		WP_TESTS_TAG="tags/${WP_VERSION}"
	fi

	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i .bak'
	else
		local ioption='-i'
	fi

	# set up testing suite if it doesn't yet exist
	if [ ! -d "$WP_TESTS_DIR" ]; then
		# set up testing suite
		mkdir -p "$WP_TESTS_DIR"
		svn co --quiet --ignore-externals https://develop.svn.wordpress.org/"${WP_TESTS_TAG}"/tests/phpunit/includes/ "$WP_TESTS_DIR"/includes
		svn co --quiet --ignore-externals https://develop.svn.wordpress.org/"${WP_TESTS_TAG}"/tests/phpunit/data/ "$WP_TESTS_DIR"/data
	fi

	if [ ! -f wp-tests-config.php ]; then
		download https://develop.svn.wordpress.org/"${WP_TESTS_TAG}"/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
		# remove all forward slashes in the end
		WP_CORE_DIR="${WP_CORE_DIR%/}"
		sed "$ioption" "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed "$ioption" "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed "$ioption" "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed "$ioption" "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed "$ioption" "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	fi
}

# Installs the WordPress database. Uses the passed arguments to create a database.
install_db() {
	local DB_NAME=${1:-"wordpress_test"}
	local DB_USER=${2:-"root"}
	local DB_PASS=${3:-""}
	local DB_HOST=${4:-"127.0.0.1"}

	echo "Creating database: $1 on $4..."

	# parse DB_HOST for port or socket references
	IFS=':' read -ra PARTS <<< "${DB_HOST}"
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};			
	local EXTRA=(--user="$DB_USER")

	if [ -n "$DB_HOSTNAME" ] ; then
		if echo "$DB_SOCK_OR_PORT" | grep -qe '^[0-9]\{1,\}$'; then
			EXTRA=("${EXTRA[@]}" --host="$DB_HOSTNAME" --port="$DB_SOCK_OR_PORT" --protocol=tcp)
		elif [ -n "$DB_SOCK_OR_PORT" ] ; then
			EXTRA=("${EXTRA[@]}" --socket="$DB_SOCK_OR_PORT")
		elif [ -n "$DB_HOSTNAME" ] ; then
			EXTRA=("${EXTRA[@]}" --host="$DB_HOSTNAME" --protocol=tcp)
		fi
	fi

	if [ -n "$DB_PASS" ] ; then
		EXTRA=("${EXTRA[@]}" --password="$DB_PASS")
	fi

	mysqladmin create "$DB_NAME" "${EXTRA[@]}"
}

# Deletes all the WordPress files so we can make another pass with a different version. Resets the database to an empty db (but does not drop it).
cleanup() {
	local TMPDIR=${1:-"/tmp"}
	local WPDIR=${2:-"$TMPDIR/wordpress"}
	local WP_TESTS_DIR=${3:-"$TMPDIR/wordpress-tests-lib"}
	local WP_VERSION_JSON=${4:-"$TMPDIR/wp-latest.json"}

	wp db reset --yes --path="$WPDIR"
	rm -rf "$WPDIR"
	rm -rf "$WP_TESTS_DIR"

	# Check if the file exists
	if [ -f "$WP_VERSION_JSON" ]; then
		# Remove the files
		rm -f "$WP_VERSION_JSON"
	fi
}
