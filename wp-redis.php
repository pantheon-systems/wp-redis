<?php
/**
 * Plugin Name: WP Redis
 * Plugin URI: http://github.com/pantheon-systems/wp-redis/
 * Description: WordPress Object Cache using Redis. Requires the PhpRedis extension (https://github.com/phpredis/phpredis).
 * Version: 1.4.4
 * Author: Pantheon, Josh Koenig, Matthew Boynes, Daniel Bachhuber, Alley Interactive
 * Author URI: https://pantheon.io/
 */

/*
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'WP_Redis_CLI_Command' ) ) {
	require_once __DIR__ . '/cli.php';
}

/**
 * Get helpful details on the Redis connection. Used by the WP-CLI command.
 *
 * @return array
 */
function wp_redis_get_info() {
	global $wp_object_cache, $redis_server;
	// Default Redis port.
	$port = 6379;
	// Default Redis database number.
	$database = 0;

	if ( empty( $redis_server ) ) {
		// Attempt to automatically load Pantheon's Redis config from the env.
		if ( isset( $_SERVER['CACHE_HOST'] ) ) {
			$redis_server = [
				// Don't use WP methods to sanitize the host due to plugin loading issues with other caching methods.
				// @phpcs:ignore WordPressVIPMinimum.Functions.StripTags.StripTagsOneParameter,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'host' => strip_tags( $_SERVER['CACHE_HOST'] ),
				'port' => ! empty( $_SERVER['CACHE_PORT'] ) ? intval( $_SERVER['CACHE_PORT'] ) : $port,
				// Don't attempt to sanitize passwords as this can break authentication.
				// @phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'auth' => ! empty( $_SERVER['CACHE_PASSWORD'] ) ? $_SERVER['CACHE_PASSWORD'] : null,
				'database' => ! empty( $_SERVER['CACHE_DB'] ) ? intval( $_SERVER['CACHE_DB'] ) : $database,
			];
		} else {
			$redis_server = [
				'host' => '127.0.0.1',
				'port' => $port,
				'database' => $database,
			];
		}
	}

	if ( ! defined( 'WP_REDIS_OBJECT_CACHE' ) || ! WP_REDIS_OBJECT_CACHE ) {
		return new WP_Error( 'wp-redis', __( 'WP Redis object-cache.php file is missing from the wp-content/ directory.', 'wp-redis' ) );
	}

	if ( ! $wp_object_cache->is_redis_connected ) {
		if ( ! isset( $wp_object_cache->missing_redis_message ) ) {
			$wp_object_cache->missing_redis_message = __( 'A Redis service needs to be enabled before the WP Redis object cache will function properly.', 'wp-redis' );
		}

		return new WP_Error( 'wp-redis', $wp_object_cache->missing_redis_message );
	}

	$info           = $wp_object_cache->redis->info();
	$uptime_in_days = $info['uptime_in_days'];
	if ( 1 === $info['uptime_in_days'] ) {
		$uptime_in_days .= ' day';
	} else {
		$uptime_in_days .= ' days';
	}
	if ( ! empty( $redis_server['database'] ) ) {
		$database = $redis_server['database'];
	}
	$key_count = 0;
	if ( isset( $info[ 'db' . $database ] ) && preg_match( '#keys=([\d]+)#', $info[ 'db' . $database ], $matches ) ) {
		$key_count = $matches[1];
	}
	return [
		'status'            => 'connected',
		'used_memory'       => $info['used_memory_human'],
		'uptime'            => $uptime_in_days,
		'key_count'         => $key_count,
		'instantaneous_ops' => $info['instantaneous_ops_per_sec'] . '/sec',
		'lifetime_hitrate'  => round( ( $info['keyspace_hits'] / ( $info['keyspace_hits'] + $info['keyspace_misses'] ) * 100 ), 2 ) . '%',
		'redis_host'        => $redis_server['host'],
		'redis_port'        => ! empty( $redis_server['port'] ) ? $redis_server['port'] : 6379,
		'redis_auth'        => ! empty( $redis_server['auth'] ) ? $redis_server['auth'] : '',
		'redis_database'    => $database,
	];
}
