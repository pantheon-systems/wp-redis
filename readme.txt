=== WP Redis ===
Contributors: getpantheon, danielbachhuber, mboynes, Outlandish Josh
Tags: cache, plugin
Requires at least: 3.0.1
Tested up to: 4.5.1
Stable tag: 0.5.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Back your WP Object Cache with Redis, a high-performance in-memory storage backend.

== Description ==

[![Build Status](https://travis-ci.org/pantheon-systems/wp-redis.svg?branch=master)](https://travis-ci.org/pantheon-systems/wp-redis)

For sites concerned with high traffic, speed for logged-in users, or dynamic pageloads, a high-speed and persistent object cache is a must. You also need something that can scale across multiple instances of your application, so using local file caches or APC are out.

Redis is a great answer, and one we bundle on the Pantheon platform. This is our plugin for integrating with the cache, but you can use it on any self-hosted WordPress site if you have Redis. Install from [WordPress.org](https://wordpress.org/plugins/wp-redis/) or [Github](https://github.com/pantheon-systems/wp-redis).

It's important to note that a persistent object cache isn't a panacea - a page load with 2,000 Redis calls can be 2 full seconds of object cache transactions. Make sure you use the object cache wisely: keep to a sensible number of keys, don't store a huge amount of data on each key, and avoid stampeding frontend writes and deletes.

Go forth and make awesome! And, once you've built something great, [send us feature requests (or bug reports)](https://github.com/pantheon-systems/wp-redis/issues).

== Installation ==

This assumes you have a PHP environment with the [required PhpRedis extension](https://github.com/phpredis/phpredis) and a working Redis server (e.g. Pantheon).

1. Install `object-cache.php` to `wp-content/object-cache.php` with a symlink or by copying the file.
2. If you're not running on Pantheon, edit wp-config.php to add your cache credentials, e.g.:

        $redis_server = array(
            'host' => '127.0.0.1',
            'port' => 6379,
            'auth' => '12345',
        );

3. Engage thrusters: you are now backing WP's Object Cache with Redis.
4. (Optional) To use the same Redis server with multiple, discreet WordPress installs, you can use the `WP_CACHE_KEY_SALT` constant to define a unique salt for each install.
5. (Optional) To use true cache groups, with the ability to delete all keys for a given group, define the `WP_REDIS_USE_CACHE_GROUPS` constant to true. However, when enabled, the expiration value is not respected because expiration on group keys isn't a feature supported by Redis.
6. (Optional) On an existing site previously using WordPress' transient cache, use WP-CLI to delete all (`%_transient_%`) transients from the options table: `wp transient delete-all`. WP Redis assumes responsibility for the transient cache.

== Frequently Asked Questions ==

= Why would I want to use this plugin? =

If you are concerned with the speed of your site, backing it with a high-performance, persistent object cache can have a huge impact. It takes load off your database, and is faster for loading all the data objects WordPress needs to run.

= How does this work with other caching plugins? =

This plugin is for the internal application object cache. It doesn't have anything to do with page caches. On Pantheon you do not need additional page caching, but if you are self-hosted you can use your favorite page cache plugins in conjunction with WP Redis.

= How do I disable the persistent object cache for a bad actor? =

A page load with 2,000 Redis calls can be 2 full seonds of object cache transactions. If a plugin you're using is erroneously creating a huge number of cache keys, you might be able to mitigate the problem by disabling cache persistency for the plugin's group:

    wp_cache_add_non_persistent_groups( array( 'bad-actor' ) );

This declaration means use of `wp_cache_set( 'foo', 'bar', 'bad-actor' );` and `wp_cache_get( 'foo', 'bad-actor' );` will not use Redis, and instead fall back to WordPress' default runtime object cache.

= How can I contribute? =

The best way to contribute to the development of this plugin is by participating on the GitHub project:

https://github.com/pantheon-systems/wp-redis

Pull requests and issues are welcome!

== Changelog ==

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
