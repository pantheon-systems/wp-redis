=== WP Redis ===
Contributors: getpantheon, danielbachhuber, mboynes, Outlandish Josh
Tags: cache, plugin
Requires at least: 3.0.1
Tested up to: 4.4
Compatible up to: 4.1
Stable tag: 0.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Back your WP Object Cache with Redis, a high-performance in-memory storage backend.

== Description ==

[![Build Status](https://travis-ci.org/pantheon-systems/wp-redis.svg?branch=master)](https://travis-ci.org/pantheon-systems/wp-redis)

For sites concerned with high traffic, speed for logged-in users, or dynamic pageloads, a high-speed and persistent object cache is a must. You also need something that can scale across multiple instances of your application, so using local file caches or APC are out.

Redis is a great answer, and one we bundle on the Pantheon platform. This is our plugin for integrating with the cache, but you can use it on any self-hosted WordPress site if you have Redis. Install from [WordPress.org](https://wordpress.org/plugins/wp-redis/) or [Github](https://github.com/pantheon-systems/wp-redis).

Go forth and make awesome! And, once you've built something great, [send us feature requests (or bug reports)](https://github.com/pantheon-systems/wp-redis/issues).

== Installation ==

This assumes you have a PHP environment with the [required PhpRedis extension](https://github.com/phpredis/phpredis) and a working Redis server (e.g. Pantheon).

1. Install `object-cache.php` to `wp-content/object-cache.php` with a symlink or by copying the file.
2. If you're not running on Pantheon, edit wp-config.php to add your cache credentials, e.g.:

        $redis_server = array( 'host' => '127.0.0.1',
                               'port' => 6379,
                               'auth' => '12345' );

3. Engage thrusters: you are now backing WP's Object Cache with Redis.
4. (Optional) To use the same Redis server with multiple, discreet WordPress installs, you can use the `WP_CACHE_KEY_SALT` constant to define a unique salt for each install.
5. (Optional) On an existing site previously using WordPress' transient cache, use WP-CLI to delete all (`%_transient_%`) transients from the options table: `wp transient delete-all`. WP Redis assumes responsibility for the transient cache.

== Frequently Asked Questions ==

= Why would I want to use this plugin? =

If you are concerned with the speed of your site, backing it with a high-performance, persistent object cache can have a huge impact. It takes load off your database, and is faster for loading all the data objects WordPress needs to run.

= How does this work with other caching plugins? =

This plugin is for the internal application object cache. It doesn't have anything to do with page caches. On Pantheon you do not need additional page caching, but if you are self-hosted you can use your favorite page cache plugins in conjunction with WP Redis.

= How can I contribute? =

The best way to contribute to the development of this plugin is by participating on the GitHub project:

https://github.com/pantheon-systems/wp-redis

Pull requests and issues are welcome!

== Changelog ==

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
