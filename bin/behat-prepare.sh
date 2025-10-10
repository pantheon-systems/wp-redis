#!/bin/bash

###
# Prepare a Pantheon site environment for the Behat test suite, by installing
# and configuring the plugin for the environment. This script is architected
# such that it can be run a second time if a step fails.
###

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
# NOTE: The 'composer install' command was removed from this script.
# It is already being run in the main workflow file, so running it here
# is redundant and slows down the pipeline.
###

###
# Create a new environment for this particular test run.
###
terminus env:create "${TERMINUS_SITE}.dev" "$TERMINUS_ENV"
terminus env:wipe "$SITE_ENV" --yes

###
# Get all necessary environment details.
###
PANTHEON_GIT_URL=$(terminus connection:info "$SITE_ENV" --field=git_url)
PANTHEON_SITE_URL="$TERMINUS_ENV-$TERMINUS_SITE.pantheonsite.io"
PREPARE_DIR="/tmp/$TERMINUS_ENV-$TERMINUS_SITE"
BASH_DIR="$( cd -P "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

PHP_VERSION="$(terminus env:info "$SITE_ENV" --field=php_version)"
echo "PHP Version: $PHP_VERSION"

###
# Switch to git mode for pushing the files up
###
terminus connection:set "$SITE_ENV" git
rm -rf "$PREPARE_DIR"
git clone -b "$TERMINUS_ENV" "$PANTHEON_GIT_URL" "$PREPARE_DIR"

###
# Add the copy of this plugin itself to the environment
###
rm -rf "$PREPARE_DIR"/wp-content/plugins/wp-redis
cd "$BASH_DIR"/..
rsync -av --exclude='vendor/' --exclude='node_modules/' --exclude='tests/' ./* "$PREPARE_DIR"/wp-content/plugins/wp-redis
rm -rf "$PREPARE_DIR"/wp-content/plugins/wp-redis/.git

###
# Add the debugging plugin to the environment
###
# FIXED: Changed debug file name to "redis-debug.php"
rm -rf "$PREPARE_DIR"/wp-content/mu-plugins/redis-debug.php
cp "$BASH_DIR"/fixtures/redis-debug.php "$PREPARE_DIR"/wp-content/mu-plugins/redis-debug.php

###
# Push files to the environment
###
cd "$PREPARE_DIR"
git add wp-content
git config user.email "wp-redis@getpantheon.com"
git config user.name "Pantheon"
git commit -m "Include WP Redis and its configuration files"
git push

###
# Wait for the filesystem to refresh on Pantheon.
###
# FIXED: Replaced outdated 'build:workflow:wait' with a reliable polling loop.
echo "Waiting for code to sync on Pantheon..."
max_retries=10
retry_interval=15
for (( i=1; i<=max_retries; i++ )); do
  if terminus ssh "$SITE_ENV" -- "test -f code/wp-content/plugins/wp-redis/wp-redis.php"; then
    echo "Code sync confirmed."
    break
  fi
  if [ "$i" -eq "$max_retries" ]; then
    echo "Code sync timed out."
    exit 1
  fi
  echo "Attempt $i/$max_retries: Code not synced yet. Waiting ${retry_interval}s..."
  sleep "$retry_interval"
done

###
# Set up WordPress, theme, and plugins for the test run
###
# Silence output so as not to show the password.
{
  # FIXED: Updated admin email address
  terminus wp "$SITE_ENV" -- core install --title="$TERMINUS_ENV"-"$TERMINUS_SITE" --url="$PANTHEON_SITE_URL" --admin_user="$WORDPRESS_ADMIN_USERNAME" --admin_email=ci@getpantheon.com --admin_password="$WORDPRESS_ADMIN_PASSWORD"
} &> /dev/null
# FIXED: Changed plugin name to "wp-redis"
terminus wp "$SITE_ENV" -- plugin activate wp-redis
terminus wp "$SITE_ENV" -- theme activate twentytwentythree
terminus wp "$SITE_ENV" -- rewrite structure '/%year%/%monthnum%/%day%/%postname%/'
