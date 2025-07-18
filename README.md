# WP Redis #
[![Actively Maintained](https://img.shields.io/badge/Pantheon-Actively_Maintained-yellow?logo=pantheon&color=FFDC28)](https://pantheon.io/docs/oss-support-levels#actively-maintained)

**Contributors:** [getpantheon](https://profiles.wordpress.org/getpantheon), [danielbachhuber](https://profiles.wordpress.org/danielbachhuber), [mboynes](https://profiles.wordpress.org/mboynes), [Outlandish Josh](https://profiles.wordpress.org/outlandish-josh) [jspellman](https://profiles.wordpress.org/jspellman/) [jazzs3quence](https://profiles.wordpress.org/jazzs3quence/)  
**Tags:** cache, object-cache, redis  
**Requires at least:** 3.0.1  
**Tested up to:** 6.8.1  
**Requires PHP:** 7.4  
**Stable tag:** 1.4.7-dev  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html

Back your WP Object Cache with Redis, a high-performance in-memory storage backend.

## Description ##

[![CircleCI](https://circleci.com/gh/pantheon-systems/wp-redis/tree/release.svg?style=svg)](https://circleci.com/gh/pantheon-systems/wp-redis/tree/release)

For sites concerned with high traffic, speed for logged-in users, or dynamic pageloads, a high-speed and persistent object cache is a must. You also need something that can scale across multiple instances of your application, so using local file caches or APC are out.

Redis is a great answer, and one we bundle on the Pantheon platform. This is our plugin for integrating with the cache, but you can use it on any self-hosted WordPress site if you have Redis. Install from [WordPress.org](https://wordpress.org/plugins/wp-redis/) or [Github](https://github.com/pantheon-systems/wp-redis).

It's important to note that a persistent object cache isn't a panacea - a page load with 2,000 Redis calls can be 2 full seconds of object cache transactions. Make sure you use the object cache wisely: keep to a sensible number of keys, don't store a huge amount of data on each key, and avoid stampeding frontend writes and deletes.

Go forth and make awesome! And, once you've built something great, [send us feature requests (or bug reports)](https://github.com/pantheon-systems/wp-redis/issues). Take a look at the wiki for [useful code snippets and other tips](https://github.com/pantheon-systems/wp-redis/wiki).

## Installation ##

This assumes you have a PHP environment with the [required PhpRedis extension](https://github.com/phpredis/phpredis) and a working Redis server (e.g. Pantheon). WP Redis also works with Predis via [humanmade/wp-redis-predis-client](https://github.com/humanmade/wp-redis-predis-client).

1. Install `object-cache.php` to `wp-content/object-cache.php` with a symlink or by copying the file.
2. If you're not running on Pantheon, edit wp-config.php to add your cache credentials, e.g.:

        $redis_server = array(
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'auth'     => '12345', // ['user', 'password'] if you use Redis ACL
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
9. (Optional) To use [Relay](https://relaycache.com) instead of PhpRedis as the client define the `WP_REDIS_USE_RELAY` constant to `true`. For support requests, please use [Relay's GitHub discussions](https://github.com/cachewerk/relay/discussions).

## WP-CLI Commands ##

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

## Contributing ##

See [CONTRIBUTING.md](https://github.com/pantheon-systems/wp-redis/blob/default/CONTRIBUTING.md) for information on contributing.

## Security Policy ##
### Reporting Security Bugs
Please report security bugs found in the WP Redis plugin's source code through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/wp-redis). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin.

## Frequently Asked Questions ##

### Why would I want to use this plugin? ###

If you are concerned with the speed of your site, backing it with a high-performance, persistent object cache can have a huge impact. It takes load off your database, and is faster for loading all the data objects WordPress needs to run.

### How does this work with other caching plugins? ###

This plugin is for the internal application object cache. It doesn't have anything to do with page caches. On Pantheon you do not need additional page caching, but if you are self-hosted you can use your favorite page cache plugins in conjunction with WP Redis.

### How do I disable the persistent object cache for a bad actor? ###

A page load with 2,000 Redis calls can be 2 full seconds of object cache transactions. If a plugin you're using is erroneously creating a huge number of cache keys, you might be able to mitigate the problem by disabling cache persistency for the plugin's group:

    wp_cache_add_non_persistent_groups( array( 'bad-actor' ) );

This declaration means use of `wp_cache_set( 'foo', 'bar', 'bad-actor' );` and `wp_cache_get( 'foo', 'bad-actor' );` will not use Redis, and instead fall back to WordPress' default runtime object cache.

### Why does the object cache sometimes get out of sync with the database? ###

There's a known issue with WordPress `alloptions` cache design. Specifically, a race condition between two requests can cause the object cache to have stale values. If you think you might be impacted by this, [review this GitHub issue](https://github.com/pantheon-systems/wp-redis/issues/221) for links to more context, including a workaround.

## Changelog ##

### 1.4.7-dev ###


### 1.4.6 (June 17, 2025) ###
* PHP 8.4 compatibility

### 1.4.5 (January 21, 2024) ###
* Support Relay in `check_client_dependencies()` correctly [[#471](https://github.com/pantheon-systems/wp-redis/pull/471)] (props @EarthlingDavey)

### 1.4.4 (November 27, 2023) ###
* Updates Pantheon WP Coding Standards to 2.0 [[#445](https://github.com/pantheon-systems/wp-redis/pull/445)]
* Handle duplicate keys in `get_multiple` function [[#448](https://github.com/pantheon-systems/wp-redis/pull/448)] (props @Souptik2001)

### 1.4.3 (June 26, 2023) ###
* Bug fix: Fixes assumption that CACHE_PORT & CACHE_PASSWORD are Set. [[428](https://github.com/pantheon-systems/wp-redis/pull/428)] (props @timnolte)
* Adds WP.org validation GitHub action [[#435](https://github.com/pantheon-systems/wp-redis/pull/435)]
* Bug fix: Fixes incorrect order of `array_replace_recursive` and other issues [[434](https://github.com/pantheon-systems/wp-redis/pull/434)] (props @timnolte)
* Bug fix: Replace use of wp_strip_all_tags in object-cache.php [[434](https://github.com/pantheon-systems/wp-redis/pull/434)] (props @timnolte)
* Bug fix: Don't strip tags from the cache password. [[434](https://github.com/pantheon-systems/wp-redis/pull/434)] (props @timnolte)

### 1.4.2 (May 15, 2023) ###
* Bug fix: Removes exception loop caused by `esc_html` in `_exception_handler()` [[421](https://github.com/pantheon-systems/wp-redis/pull/421)]

### 1.4.1 (May 11, 2023) ###
* Bug fix: `wp_cache_flush_runtime` should only clear the local cache [[413](https://github.com/pantheon-systems/wp-redis/pull/413)]

### 1.4.0 (May 9, 2023) ###
* Add support for `flush_runtime` and `flush_group` functions [[#405](https://github.com/pantheon-systems/wp-redis/pull/405)]
* Add `pantheon-wp-coding-standards` [[#400](https://github.com/pantheon-systems/wp-redis/pull/400)]
* Update CONTRIBUTING.MD [[#406](https://github.com/pantheon-systems/wp-redis/pull/406)]
* Update Composer dependencies [[#401](https://github.com/pantheon-systems/wp-redis/pull/394)]

### 1.3.5 (April 6, 2023) ###
* Bump tested up to version to 6.2
* Update Composer dependencies [[#394](https://github.com/pantheon-systems/wp-redis/pull/394)]

### 1.3.4 (March 7, 2023) ###
* Set `missing_redis_message` if Redis service is not connected [[#391](https://github.com/pantheon-systems/wp-redis/pull/391)]

### 1.3.3 (February 28, 2023) ###
* Add PHP 8.2 support [[#388](https://github.com/pantheon-systems/wp-redis/pull/388)]
* Remove Grunt, add valid license to Composer file [[#387](https://github.com/pantheon-systems/wp-redis/pull/387)]
* Update Composer dependencies [[#384](https://github.com/pantheon-systems/wp-redis/pull/384)] [[#385](https://github.com/pantheon-systems/wp-redis/pull/385)]

### 1.3.2 (December 5, 2022) ###
* Fix broken `wp_cache_supports` function [[#382](https://github.com/pantheon-systems/wp-redis/pull/382)].

### 1.3.1 (December 2, 2022) ###
* Declare `wp_cache_supports` function and support features. [[#378](https://github.com/pantheon-systems/wp-redis/pull/378)]
* Make dependabot target `develop` branch for PRs. [[#376](https://github.com/pantheon-systems/wp-redis/pull/376)]
* Declare `wp_cache_supports` function and support features. [[#378](https://github.com/pantheon-systems/wp-redis/pull/378)]

### 1.3.0 (November 29, 2022) ###
* Added CONTRIBUTING.MD and GitHub action to automate deployments to wp.org. [[#368](https://github.com/pantheon-systems/wp-redis/pull/368)]

### 1.2.0 (February 17, 2022) ###
* Adds support for Relay via `WP_REDIS_USE_RELAY` constant [[#344](https://github.com/pantheon-systems/wp-redis/pull/344)].

### 1.1.4 (October 21, 2021) ###
* Fixes some faulty logic in `WP_REDIS_IGNORE_GLOBAL_GROUPS` check [[#333](https://github.com/pantheon-systems/wp-redis/pull/333)].

### 1.1.3 (October 21, 2021) ###
* Supports a `WP_REDIS_IGNORE_GLOBAL_GROUPS` constant to prevent groups from being added to global caching group [[#331](https://github.com/pantheon-systems/wp-redis/pull/331)].

### 1.1.2 (March 24, 2021) ###
* Applies logic used elsewhere to fall back to `$_SERVER` in `wp_redis_get_info()` [[#316](https://github.com/pantheon-systems/wp-redis/pull/316)].

### 1.1.1 (August 17, 2020) ###
* Returns cache data in correct order when using `wp_cache_get_multiple()` and internal cache is already primed [[#292](https://github.com/pantheon-systems/wp-redis/pull/292)].

### 1.1.0 (July 13, 2020) ###
* Implements `wp_cache_get_multiple()` for WordPress 5.5 [[#287](https://github.com/pantheon-systems/wp-redis/pull/287)].
* Bails early when connecting to Redis throws an Exception to avoid fatal error [[285](https://github.com/pantheon-systems/wp-redis/pull/285)].

### 1.0.1 (April 14, 2020) ###
* Adds support for specifying Redis database number from environment/server variables [[#273](https://github.com/pantheon-systems/wp-redis/pull/273)].

### 1.0.0 (March 2, 2020) ###
* Plugin is stable.

### 0.8.3 (February 24, 2020) ###
* Fixes `wp redis cli` by using `proc_open()` directly, instead of `WP_CLI::launch()` [[#268](https://github.com/pantheon-systems/wp-redis/pull/268)].

### 0.8.2 (January 15, 2020) ###
* Catches exceptions when trying to connect to Redis [[#265](https://github.com/pantheon-systems/wp-redis/pull/265)].

### 0.8.1 (January 10, 2020) ###
* Adds `WP_REDIS_DEFAULT_EXPIRE_SECONDS` constant to set default cache expire value [[#264](https://github.com/pantheon-systems/wp-redis/pull/264)].

### 0.8.0 (January 6, 2020) ###
* Uses `flushdb` instead of `flushAll` to avoid flushing the entire Redis instance [[#259](https://github.com/pantheon-systems/wp-redis/pull/259)].

### 0.7.1 (December 14, 2018) ###
* Better support in `wp_cache_init()` for drop-ins like LudicrousDB [[#231](https://github.com/pantheon-systems/wp-redis/pull/231)].
* Cleans up PHPCS issues.

### 0.7.0 (August 22, 2017) ###
* Adds filterable connection methods to permit use of Predis. See [humanmade/wp-redis-predis-client](https://github.com/humanmade/wp-redis-predis-client) for more details.

### 0.6.2 (June 5, 2017) ###
* Bug fix: Preserves null values in internal cache.
* Bug fix: Converts numeric values to their true type when getting.

### 0.6.1 (February 23, 2017) ###
* Bug fix: correctly passes an empty password to `redis-cli`.
* Variety of improvements to the test suite.

### 0.6.0 (September 21, 2016) ###
* Introduces three new WP-CLI commands: `wp redis debug` to display cache hit/miss ratio for any URL; `wp redis info` to display high-level Redis statistics; `wp redis enable` to create the `object-cache.php` symlink.
* Permits a Redis database to be defined with `$redis_server['database']`.
* Introduces `wp_cache_add_redis_hash_groups()`, which permits registering specific groups to use Redis hashes, and is more precise than our existing `WP_REDIS_USE_CACHE_GROUPS` constant.

### 0.5.0 (April 27, 2016) ###

* Performance boost! Removes redundant `exists` call from `wp_cache_get()`, which easily halves the number of Redis calls.
* Uses `add_action()` and `$wpdb` in a safer manner for compatibility with Batcache, which loads the object cache before aforementioned APIs are available.
* For debugging purposes, tracks number of calls to Redis, and includes breakdown of call types.
* Adds a slew of more explicit test coverage against existing features.
* For consistency with the actual Redis call, calls `del` instead of `delete`.
* Bug fix: If a group isn't persistent, don't ever make an `exists` call against Redis.

### 0.4.0 (March 23, 2016) ###

* Introduces `wp redis-cli`, a WP-CLI command to launch redis-cli with WordPress' Redis credentials.
* Bug fix: Ensures fail back mechanism works as expected on multisite, by writing to sitemeta table instead of the active site's options table.
* Bug fix: Uses 'default' as the default cache group, mirroring WordPress core, such that `$wp_object_cache->add( 'foo', 'bar' )` === `wp_cache_add( 'foo', 'bar' )`.

### 0.3.0 (February 11, 2016) ###

* Introduces opt-in support for Redis cache groups. Enable with `define( 'WP_REDIS_USE_CACHE_GROUPS', true );`. When enabled, WP Redis persists cache groups in a structured manner, instead of hashing the cache key and group together.
* Uses PHP_CodeSniffer and [WordPress Coding Standards sniffs](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) to ensure WP Redis adheres to WordPress coding standards.
* Bug fix: Permits use of a Unix socket in `$redis_server['host']` by ensuring the supplied `$port` is null.

### 0.2.2 (November 24, 2015) ###

* Bug fix: use `INSERT IGNORE INTO` instead of `INSERT INTO` to prevent SQL errors when two concurrent processes attempt to write failback flag at the same time.
* Bug fix: use `E_USER_WARNING` with `trigger_error()`.
* Bug fix: catch Exceptions thrown during authentication to permit failing back to internal object cache.

### 0.2.1 (November 17, 2015) ###

* Bug fix: prevent SQL error when `$wpdb->options` isn't yet initialized on multisite.

### 0.2.0 (November 17, 2015) ###

* Gracefully fails back to the WordPress object cache when Redis is unavailable or intermittent. Previously, WP Redis would hard fatal.
* Triggers a PHP error if Redis goes away mid-request, for you to monitor in your logs. Attempts one reconnect based on specific error messages.
* Forces a flushAll on Redis when Redis comes back after failing. This behavior can be disabled with the `WP_REDIS_DISABLE_FAILBACK_FLUSH` constant.
* Show an admin notice when Redis is unavailable but is expected to be.

### 0.1 ###
* Initial commit of working code for the benefit of all.

## Upgrade Notice ##

### 1.4.0 (May 9, 2023) ###
WP Redis 1.4.0 adds support for the `flush_runtime` and `flush_group` functions. If you've copied `object-cache.php` and made your own changes, be sure to copy these additions over as well.
