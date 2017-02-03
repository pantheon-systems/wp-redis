#!/bin/bash

###
# Prepare a Pantheon site environment for the Behat test suite, by installing
# and configuring the plugin for the environment. This script is architected
# such that it can be run a second time if a step fails.
###

terminus whoami > /dev/null
if [ $? -ne 0 ]; then
	echo "Terminus unauthenticated; assuming unauthenticated build"
	exit 0
fi

if [ -z "$TERMINUS_SITE" ] || [ -z "$TERMINUS_ENV" ]; then
	echo "TERMINUS_SITE and TERMINUS_ENV environment variables must be set"
	exit 1
fi

if [ -z "$WORDPRESS_ADMIN_USERNAME" ] || [ -z "$WORDPRESS_ADMIN_PASSWORD" ]; then
	echo "WORDPRESS_ADMIN_USERNAME and WORDPRESS_ADMIN_PASSWORD environment variables must be set"
	exit 1
fi

set -ex

###
# Create a new environment for this particular test run.
###
terminus env:create  $TERMINUS_SITE.dev $TERMINUS_ENV
terminus env:wipe $SITE_ENV --yes

###
# Get all necessary environment details.
###
PANTHEON_GIT_URL=$(terminus connection:info $SITE_ENV --field=git_url)
PANTHEON_SITE_URL="$TERMINUS_ENV-$TERMINUS_SITE.pantheonsite.io"
PREPARE_DIR="/tmp/$TERMINUS_ENV-$TERMINUS_SITE"
BASH_DIR="$( cd -P "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

###
# Switch to git mode for pushing the files up
###
terminus connection:set $SITE_ENV git
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
# Silence output so as not to show the password.
{
  terminus wp $SITE_ENV -- core install --title=$TERMINUS_ENV-$TERMINUS_SITE --url=$PANTHEON_SITE_URL --admin_user=$WORDPRESS_ADMIN_USERNAME --admin_email=wp-redis@getpantheon.com --admin_password=$WORDPRESS_ADMIN_PASSWORD
} &> /dev/null
terminus wp $SITE_ENV -- cache flush
terminus wp $SITE_ENV -- plugin activate wp-redis
