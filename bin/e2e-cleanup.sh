#!/bin/bash

###
# Delete the Pantheon site environment after the end-to-end test suite has run.
###

if [ -z "$TERMINUS_SITE" ] || [ -z "$TERMINUS_ENV" ]; then
	echo "TERMINUS_SITE and TERMINUS_ENV environment variables must be set"
	exit 1
fi

if [ -z "$WORDPRESS_ADMIN_USERNAME" ] || [ -z "$WORDPRESS_ADMIN_PASSWORD" ]; then
	echo "WORDPRESS_ADMIN_USERNAME and WORDPRESS_ADMIN_PASSWORD environment variables must be set"
	exit 1
fi

# Derived rather than passed in, so the two checks above cover every terminus call below.
SITE_ENV="${TERMINUS_SITE}.${TERMINUS_ENV}"

set -x

###
# Delete the environment used for this test run.
###
if ! terminus env:info $SITE_ENV > /dev/null 2>&1; then
	echo "Environment $SITE_ENV does not exist; nothing to clean up."
	exit 0
fi

if ! terminus multidev:delete $SITE_ENV --delete-branch --yes; then
	echo "::warning::Failed to delete multidev $SITE_ENV. Check the environment in the Pantheon dashboard: https://dashboard.pantheon.io/sites/${TERMINUS_SITE}#${TERMINUS_ENV}/code"
fi
