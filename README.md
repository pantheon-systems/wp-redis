# WP Redis <img align="right" src="https://travis-ci.org/pantheon-systems/wp-redis.png?branch=master" />

WordPress Object Cache using Redis. By Pantheon and Alley Interactive.

Pre-requisites
--------------

* [redis](http://redis.io/)
* [phpredis](https://github.com/nicolasff/phpredis)

or

* [pantheon](https://www.getpantheon.com)

Setup
-----

1. Install `object-cache.php` to the `wp-content/object-cache.php`.
2. In your `wp-config.php` file, add your server credentials:

        $redis_server = array( 'host' => '127.0.0.1', 'port' => 6379, 'auth' => '12345' );

   On Pantheon this setting is not necessary.
3. Engage thrusters: you are now backing WP's object cache with Redis.


