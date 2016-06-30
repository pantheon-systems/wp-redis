#!/bin/bash

set -ex

if [ -z "$TERMINUS_SITE" ] || [ -z "$TERMINUS_ENV" ]; then
	echo "TERMINUS_SITE and TERMINUS_ENV environment variables must be set"
	exit 1
fi

###
# Create a new environment for this particular test run.
###
terminus site create-env --to-env=$TERMINUS_ENV --from-env=dev
yes | terminus site wipe

###
# Get all necessary environment details.
###
PANTHEON_GIT_URL=$(terminus site connection-info --field=git_url)
PANTHEON_SITE_URL="$TERMINUS_ENV-$TERMINUS_SITE.pantheonsite.io"
PREPARE_DIR="/tmp/$TERMINUS_ENV-$TERMINUS_SITE"
BASH_DIR="$( cd -P "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

###
# Switch to git mode for pushing the files up
###
terminus site set-connection-mode --mode=git
rm -rf $PREPARE_DIR
git clone -b $TERMINUS_ENV $PANTHEON_GIT_URL $PREPARE_DIR

###
# Add the copy of object-cache.php to the environment
###
cd $BASH_DIR/..
rm -rf $PREPARE_DIR/wp-content/object-cache.php
cp object-cache.php $PREPARE_DIR/wp-content/object-cache.php

###
# Push files to the environment
###
cd $PREPARE_DIR
git add wp-content/object-cache.php
git config user.email "wp-redis@getpantheon.com"
git config user.name "Pantheon"
git commit -m "Include WP Redis and its configuration files"
git push

###
# Set up WordPress, theme, and plugins for the test run
###
terminus wp "core install --title=$TERMINUS_ENV-$TERMINUS_SITE --url=$PANTHEON_SITE_URL --admin_user=pantheon --admin_email=wp-redis@getpantheon.com --admin_password=pantheon"
