#!/bin/bash

###
# Prepare a Pantheon site environment for the Behat test suite, by installing
# and configuring the plugin for the environment. This script is architected
# such that it can be run a second time if a step fails.
###

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
# Add the copy of this plugin itself to the environment
###
rm -rf $PREPARE_DIR/wp-content/plugins/wp-redis
rm -rf $PREPARE_DIR/wp-content/object-cache.php
cd $BASH_DIR/..
rsync -av --exclude='vendor/' --exclude='node_modules/' --exclude='tests/' ./* $PREPARE_DIR/wp-content/plugins/wp-redis
rm -rf $PREPARE_DIR/wp-content/plugins/wp-redis/.git
cp object-cache.php $PREPARE_DIR/wp-content/object-cache.php

###
# Add the debugging plugin to the environment
###
rm -rf $PREPARE_DIR/wp-content/mu-plugins/redis-debug.php
cp $BASH_DIR/fixtures/redis-debug.php $PREPARE_DIR/wp-content/mu-plugins/redis-debug.php

###
# Push files to the environment
###
cd $PREPARE_DIR
git add wp-content
git config user.email "wp-redis@getpantheon.com"
git config user.name "Pantheon"
git commit -m "Include WP Redis and its configuration files"
git push

###
# Set up WordPress, theme, and plugins for the test run
###
terminus wp "core install --title=$TERMINUS_ENV-$TERMINUS_SITE --url=$PANTHEON_SITE_URL --admin_user=pantheon --admin_email=wp-redis@getpantheon.com --admin_password=pantheon"
terminus wp "cache flush"
terminus wp "plugin activate wp-redis"

###
# Download the Pantheon WordPress Upstream tests
###
cd $BASH_DIR/..
rm -rf pantheon-wordpress-upstream-master tests/pantheon-wordpress-upstream
wget https://github.com/pantheon-systems/pantheon-wordpress-upstream/archive/master.zip
unzip master.zip
mv pantheon-wordpress-upstream-master/features tests/pantheon-wordpress-upstream
rm tests/pantheon-wordpress-upstream/0-install.feature
rm -rf pantheon-wordpress-upstream-master
rm master.zip
