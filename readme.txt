=== WP Redis ===
Contributors: getpantheon, danielbachhuber, mboynes, Outlandish Josh
Tags: cache, plugin, redis
Requires at least: 3.0.1
Tested up to: 5.9
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Back your WP Object Cache with Redis, a high-performance in-memory storage backend.

== Description ==

[![Travis CI](https://travis-ci.org/pantheon-systems/wp-redis.svg?branch=master)](https://travis-ci.org/pantheon-systems/wp-redis) [![CircleCI](https://circleci.com/gh/pantheon-systems/wp-redis/tree/master.svg?style=svg)](https://circleci.com/gh/pantheon-systems/wp-redis/tree/master)

For sites concerned with high traffic, speed for logged-in users, or dynamic pageloads, a high-speed and persistent object cache is a must. You also need something that can scale across multiple instances of your application, so using local file caches or APC are out.

Redis is a great answer, and one we bundle on the Pantheon platform. This is our plugin for integrating with the cache, but you can use it on any self-hosted WordPress site if you have Redis. Install from [WordPress.org](https://wordpress.org/plugins/wp-redis/) or [Github](https://github.com/pantheon-systems/wp-redis).

It's important to note that a persistent object cache isn't a panacea - a page load with 2,000 Redis calls can be 2 full seconds of object cache transactions. Make sure you use the object cache wisely: keep to a sensible number of keys, don't store a huge amount of data on each key, and avoid stampeding frontend writes and deletes.

Go forth and make awesome! And, once you've built something great, [send us feature requests (or bug reports)](https://github.com/pantheon-systems/wp-redis/issues). Take a look at the wiki for [useful code snippets and other tips](https://github.com/pantheon-systems/wp-redis/wiki).

== Installation ==

This assumes you have a PHP environment with the [required PhpRedis extension](https://github.com/phpredis/phpredis) and a working Redis server (e.g. Pantheon). WP Redis also works with Predis via [humanmade/wp-redis-predis-client](https://github.com/humanmade/wp-redis-predis-client).

1. Install `object-cache.php` to `wp-content/object-cache.php` with a symlink or by copying the file.
2. If you're not running on Pantheon, edit wp-config.php to add your cache credentials, e.g.:

        $redis_server = array(
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'auth'     => '12345',
            'database' => 0, // Optionally use a specific numeric Redis database. Default is 0.
        );

3. If your Redis server is listening through a sockt file instead, set its path on `host` parameter and change the port to `null`:

        $redis_server = array(
            'host'     => '/path/of/redis/socket-file.sock',
            'port'     => null,
            'auth'     => '12345',
            'database' => 0, // Optionally use a specific numeric Redis database. Default is 0.
        );

4. Engage thrusters: you are now backing WP's Object Cache with Redis.
5. (Optional) To use the `wp redis` WP-CLI commands, activate the WP Redis plugin. No activation is necessary if you're solely using the object cache drop-in.
6. (Optional) To use the same Redis server with multiple, discreet WordPress installs, you can use the `WP_CACHE_KEY_SALT` constant to define a unique salt for each install.
7. (Optional) To use true cache groups, with the ability to delete all keys for a given group, register groups with `wp_cache_add_redis_hash_groups()`, or define the `WP_REDIS_USE_CACHE_GROUPS` constant to `true` to enable with all groups. However, when enabled, the expiration value is not respected because expiration on group keys isn't a feature [supported by Redis](https://github.com/redis/redis/issues/6620).
8. (Optional) On an existing site previously using WordPress' transient cache, use WP-CLI to delete all (`%_transient_%`) transients from the options table: `wp transient delete-all`. WP Redis assumes responsibility for the transient cache.
9. (Optional) To use [Relay](https://relaycache.com) instead of PhpRedis as the client define the `WP_REDIS_USE_RELAY` constant to `true`.

== WP-CLI Commands ==

This plugin implements a variety of [WP-CLI](https://wp-cli.org) commands. All commands are grouped into the `wp redis` namespace.

    $ wp help redis

    NAME

      wp redis

    SYNOPSIS

      wp redis <command>

    SUBCOMMANDS

      cli         Launch redis-cli using Redis configuration for WordPress
      debug       Debug object cache hit / miss ratio for any page URL.
      enable      Enable WP Redis by creating the symlink for object-cache.php
      info        Provide details on the Redis connection.

Use `wp help redis <command>` to learn more about each command.

== Contributing ==

The best way to contribute to the development of this plugin is by participating on the GitHub project:

https://github.com/pantheon-systems/wp-redis

Pull requests and issues are welcome!

You may notice there are two sets of tests running, on two different services:

* Travis CI runs the [PHPUnit](https://phpunit.de/) test suite in a variety of environment configurations (e.g. Redis enabled vs. Redis disabled).
* Circle CI runs the [Behat](http://behat.org/) test suite against a Pantheon site, to ensure the plugin's compatibility with the Pantheon platform.

Both of these test suites can be run locally, with a varying amount of setup.

PHPUnit requires the [WordPress PHPUnit test suite](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/), and access to a database with name `wordpress_test`. If you haven't already configured the test suite locally, you can run `bash bin/install-wp-tests.sh wordpress_test root '' localhost`. You'll also need to enable Redis and the PHPRedis extension in order to run the test suite against Redis.

Behat requires a Pantheon site with Redis enabled. Once you've created the site, you'll need [install Terminus](https://github.com/pantheon-systems/terminus#installation), and set the `TERMINUS_TOKEN`, `TERMINUS_SITE`, and `TERMINUS_ENV` environment variables. Then, you can run `./bin/behat-prepare.sh` to prepare the site for the test suite.

== Frequently Asked Questions ==

= Why would I want to use this plugin? =

If you are concerned with the speed of your site, backing it with a high-performance, persistent object cache can have a huge impact. It takes load off your database, and is faster for loading all the data objects WordPress needs to run.

= How does this work with other caching plugins? =

This plugin is for the internal application object cache. It doesn't have anything to do with page caches. On Pantheon you do not need additional page caching, but if you are self-hosted you can use your favorite page cache plugins in conjunction with WP Redis.

= How do I disable the persistent object cache for a bad actor? =

A page load with 2,000 Redis calls can be 2 full seonds of object cache transactions. If a plugin you're using is erroneously creating a huge number of cache keys, you might be able to mitigate the problem by disabling cache persistency for the plugin's group:

    wp_cache_add_non_persistent_groups( array( 'bad-actor' ) );

This declaration means use of `wp_cache_set( 'foo', 'bar', 'bad-actor' );` and `wp_cache_get( 'foo', 'bad-actor' );` will not use Redis, and instead fall back to WordPress' default runtime object cache.

= Why does the object cache sometimes get out of sync with the database? =

There's a known issue with WordPress `alloptions` cache design. Specifically, a race condition between two requests can cause the object cache to have stale values. If you think you might be impacted by this, [review this GitHub issue](https://github.com/pantheon-systems/wp-redis/issues/221) for links to more context, including a workaround.

== Changelog ==

= 1.2.0 (February 17, 2022) =
* Adds support for Relay via `WP_REDIS_USE_RELAY` constant [[#344](https://github.com/pantheon-systems/wp-redis/pull/344)].

= 1.1.4 (October 21, 2021) =
* Fixes some faulty logic in `WP_REDIS_IGNORE_GLOBAL_GROUPS` check [[#333](https://github.com/pantheon-systems/wp-redis/pull/333)].

= 1.1.3 (October 21, 2021) =
* Supports a `WP_REDIS_IGNORE_GLOBAL_GROUPS` constant to prevent groups from being added to global caching group [[#331](https://github.com/pantheon-systems/wp-redis/pull/331)].

= 1.1.2 (March 24, 2021) =
* Applies logic used elsewhere to fall back to `$_SERVER` in `wp_redis_get_info()` [[#316](https://github.com/pantheon-systems/wp-redis/pull/316)].

= 1.1.1 (August 17, 2020) =
* Returns cache data in correct order when using `wp_cache_get_multiple()` and internal cache is already primed [[#292](https://github.com/pantheon-systems/wp-redis/pull/292)].

= 1.1.0 (July 13, 2020) =
* Implements `wp_cache_get_multiple()` for WordPress 5.5 [[#287](https://github.com/pantheon-systems/wp-redis/pull/287)].
* Bails early when connecting to Redis throws an Exception to avoid fatal error [[285](https://github.com/pantheon-systems/wp-redis/pull/285)].

= 1.0.1 (April 14, 2020) =
* Adds support for specifying Redis database number from environment/server variables [[#273](https://github.com/pantheon-systems/wp-redis/pull/273)].

= 1.0.0 (March 2, 2020) =
* Plugin is stable.

= 0.8.3 (February 24, 2020) =
* Fixes `wp redis cli` by using `proc_open()` directly, instead of `WP_CLI::launch()` [[#268](https://github.com/pantheon-systems/wp-redis/pull/268)].

= 0.8.2 (January 15, 2020) =
* Catches exceptions when trying to connect to Redis [[#265](https://github.com/pantheon-systems/wp-redis/pull/265)].

= 0.8.1 (January 10, 2020) =
* Adds `WP_REDIS_DEFAULT_EXPIRE_SECONDS` constant to set default cache expire value [[#264](https://github.com/pantheon-systems/wp-redis/pull/264)].

= 0.8.0 (January 6, 2020) =
* Uses `flushdb` instead of `flushAll` to avoid flushing the entire Redis instance [[#259](https://github.com/pantheon-systems/wp-redis/pull/259)].

= 0.7.1 (December 14, 2018) =
* Better support in `wp_cache_init()` for drop-ins like LudicrousDB [[#231](https://github.com/pantheon-systems/wp-redis/pull/231)].
* Cleans up PHPCS issues.

= 0.7.0 (August 22, 2017) =
* Adds filterable connection methods to permit use of Predis. See [humanmade/wp-redis-predis-client](https://github.com/humanmade/wp-redis-predis-client) for more details.

= 0.6.2 (June 5, 2017) =
* Bug fix: Preserves null values in internal cache.
* Bug fix: Converts numeric values to their true type when getting.

= 0.6.1 (February 23, 2017) =
* Bug fix: correctly passes an empty password to `redis-cli`.
* Variety of improvements to the test suite.

= 0.6.0 (September 21, 2016) =
* Introduces three new WP-CLI commands: `wp redis debug` to display cache hit/miss ratio for any URL; `wp redis info` to display high-level Redis statistics; `wp redis enable` to create the `object-cache.php` symlink.
* Permits a Redis database to be defined with `$redis_server['database']`.
* Introduces `wp_cache_add_redis_hash_groups()`, which permits registering specific groups to use Redis hashes, and is more precise than our existing `WP_REDIS_USE_CACHE_GROUPS` constant.

= 0.5.0 (April 27, 2016) =

* Performance boost! Removes redundant `exists` call from `wp_cache_get()`, which easily halves the number of Redis calls.
* Uses `add_action()` and `$wpdb` in a safer manner for compatibility with Batcache, which loads the object cache before aforementioned APIs are available.
* For debugging purposes, tracks number of calls to Redis, and includes breakdown of call types.
* Adds a slew of more explicit test coverage against existing features.
* For consistency with the actual Redis call, calls `del` instead of `delete`.
* Bug fix: If a group isn't persistent, don't ever make an `exists` call against Redis.

= 0.4.0 (March 23, 2016) =

* Introduces `wp redis-cli`, a WP-CLI command to launch redis-cli with WordPress' Redis credentials.
* Bug fix: Ensures fail back mechanism works as expected on multisite, by writing to sitemeta table instead of the active site's options table.
* Bug fix: Uses 'default' as the default cache group, mirroring WordPress core, such that `$wp_object_cache->add( 'foo', 'bar' )` === `wp_cache_add( 'foo', 'bar' )`.

= 0.3.0 (February 11, 2016) =

* Introduces opt-in support for Redis cache groups. Enable with `define( 'WP_REDIS_USE_CACHE_GROUPS', true );`. When enabled, WP Redis persists cache groups in a structured manner, instead of hashing the cache key and group together.
* Uses PHP_CodeSniffer and [WordPress Coding Standards sniffs](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) to ensure WP Redis adheres to WordPress coding standards.
* Bug fix: Permits use of a Unix socket in `$redis_server['host']` by ensuring the supplied `$port` is null.

= 0.2.2 (November 24, 2015) =

* Bug fix: use `INSERT IGNORE INTO` instead of `INSERT INTO` to prevent SQL errors when two concurrent processes attempt to write failback flag at the same time.
* Bug fix: use `E_USER_WARNING` with `trigger_error()`.
* Bug fix: catch Exceptions thrown during authentication to permit failing back to internal object cache.

= 0.2.1 (November 17, 2015) =

* Bug fix: prevent SQL error when `$wpdb->options` isn't yet initialized on multisite.

= 0.2.0 (November 17, 2015) =

* Gracefully fails back to the WordPress object cache when Redis is unavailable or intermittent. Previously, WP Redis would hard fatal.
* Triggers a PHP error if Redis goes away mid-request, for you to monitor in your logs. Attempts one reconnect based on specific error messages.
* Forces a flushAll on Redis when Redis comes back after failing. This behavior can be disabled with the `WP_REDIS_DISABLE_FAILBACK_FLUSH` constant.
* Show an admin notice when Redis is unavailable but is expected to be.

= 0.1 =
* Initial commit of working code for the benefit of all.
