=== WP Redis ===
Contributors: getpantheon, danielbachhuber, mboynes, Outlandish Josh
Tags: cache, plugin
Requires at least: 3.0.1
Tested up to: 3.0
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Back your WP Object cache with Redis, a high-performance in-memory cache.

== Description ==

This is the long description.  No limit, and you can use Markdown (as well as in the following sections).

For backwards compatibility, if this section is missing, the full length of the short description will be used, and
Markdown parsed.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Install `object-cache.php` to the `wp-content/object-cache.php`.
2. If you're not running on Pantheon, edit wp-config to add your cache credentials:

        $redis_server = array( 'host' => '127.0.0.1', 'port' => 6379, 'auth' => '12345' );

3. Engage thrusters: you are now backing WP's object cache with Redis.

== Frequently Asked Questions ==

= Why would I want to use this plugin? =

If you are concerned with the speed of your site, backing it with a high-performance object cache can have a big effect. It takes load off your database, and is faster for loading all the data objects WordPress needs to run.

= How does this work with other caching plugins? =

This plugin is for the internal application object cache. It doesn't have anything to do with page caches. On Pantheon you do not need attitional page cacheing, but if you are self-hosting you can use your favorite page cache plugins in conjunction with wp-redis.

== Screenshots ==

1. Cool Pantheon logo for fun and profit.(png|jpg|jpeg|gif).

== Changelog ==

= 0.1 =
* Initial commit of working code for the benefit of all.

== Upgrade Notice ==
