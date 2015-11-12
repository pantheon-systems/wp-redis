# WP Redis #
**Contributors:** getpantheon, danielbachhuber, mboynes, Outlandish Josh  
**Tags:** cache, plugin  
**Requires at least:** 3.0.1  
**Tested up to:** 4.4  
**Compatible up to:** 4.1  
**Stable tag:** 0.2.0  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Back your WP Object Cache with Redis, a high-performance in-memory storage backend.

## Description ##

For sites concerned with high traffic, speed for logged-in usersm or dynamic pageloads, a performant, persistent object cache is a must. You also need something that can scale across multiple instances of your application, so using a local file cache or APC are out.

Redis is a great answer, and one we bundle on the Pantheon platform. This is our plugin for integrating with the cache, but you can use it on any self-hosted WordPress site if you have Redis.

Go forth and make awesome!

## Installation ##

This assumes you have a PHP environment with the required Redis library and a working Redis server (e.g. Pantheon).

1. Install `object-cache.php` to `wp-content/object-cache.php` with a symlink or by copying the file.
2. If you're not running on Pantheon, edit wp-config.php to add your cache credentials:

        $redis_server = array( 'host' => '127.0.0.1',
                               'port' => 6379,
                               'auth' => '12345' );

3. Engage thrusters: you are now backing WP's Object Cache with Redis.

## Frequently Asked Questions ##

### Why would I want to use this plugin? ###

If you are concerned with the speed of your site, backing it with a high-performance, persistent object cache can have a huge impact. It takes load off your database, and is faster for loading all the data objects WordPress needs to run.

### How does this work with other caching plugins? ###

This plugin is for the internal application object cache. It doesn't have anything to do with page caches. On Pantheon you do not need additional page caching, but if you are self-hosted you can use your favorite page cache plugins in conjunction with WP Redis.

## Changelog ##

### 0.2.0 (November 12, 2015) ###

* Gracefully fails back to the WordPress object cache when Redis is unavailable or intermittent. Previously, WP Redis would hard fatal.
* Triggers a PHP error if Redis goes away mid-request, for you to monitor in your logs.
* Forces a flushAll on Redis when Redis comes back after failing. This behavior can be disabled with the `WP_REDIS_DISABLE_FAILBACK_FLUSH` constant.
* Show an admin notice when Redis is unavailable but is expected to be.

### 0.1 ###
* Initial commit of working code for the benefit of all.
