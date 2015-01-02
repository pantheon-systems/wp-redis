=== WP Redis ===
Contributors: getpantheon, danielbachhuber, mboynes, Outlandish Josh
Tags: cache, plugin
Requires at least: 3.0.1
Tested up to: 4.1
Compatible up to: 4.1
Stable tag: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Back your WP Object cache with Redis, a high-performance in-memory cache.

== Description ==

For sites concerned with high traffic and speed for logged in users or dynamic pageloads, a performant object cache is a must. You also need something that can scale across multiple instances of your application, so a local file cache and APC are out.

Redis is a great answer, and one we bundle on the Pantheon platform. This is our plugin for integrating with the cache, but you can use it on any self-hosted WordPress site if you have Redis.

Go forth and make awesome!

== Installation ==

This assumes you have a PHP environment with the required Redis library and a working Redis server (e.g. Pantheon).

1. Install `object-cache.php` to the `wp-content/object-cache.php`.
2. If you're not running on Pantheon, edit wp-config to add your cache credentials:

        $redis_server = array( 'host' => '127.0.0.1',
                               'port' => 6379,
                               'auth' => '12345' );

3. Engage thrusters: you are now backing WP's object cache with Redis.

== Frequently Asked Questions ==

= Why would I want to use this plugin? =

If you are concerned with the speed of your site, backing it with a high-performance object cache can have a big effect. It takes load off your database, and is faster for loading all the data objects WordPress needs to run.

= How does this work with other caching plugins? =

This plugin is for the internal application object cache. It doesn't have anything to do with page caches. On Pantheon you do not need attitional page cacheing, but if you are self-hosting you can use your favorite page cache plugins in conjunction with wp-redis.

== Screenshots ==

Coming soon.

== Changelog ==

= 0.1 =
* Initial commit of working code for the benefit of all.

== Upgrade Notice ==
