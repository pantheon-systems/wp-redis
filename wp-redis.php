<?php
/**
 * Plugin Name: WP Redis
 * Plugin URI: http://github.com/pantheon-systems/wp-redis/
 * Description: WordPress Object Cache using Redis. Requires the PhpRedis extension (https://github.com/phpredis/phpredis).
 * Version: 0.6.0-alpha
 * Author: Pantheon, Josh Koenig, Matthew Boynes, Daniel Bachhuber Alley Interactive
 * Author URI: https://pantheon.io/
 */
/*  This program is free software; you can redistribute it and/or modify
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

namespace WPRedis;

if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'WP_Redis_CLI_Command' ) ) {
	require_once dirname( __FILE__ ) . '/cli.php';
}

/**
 * On activation: Check symlink status on plugin activation, disable the plugin if the file exists already
 * and is not symlinked properly
 */
function activation() {

	if ( file_exists( $db = WP_CONTENT_DIR . '/object-cache.php' ) ) {

		$status = symlink_status();

		if ( false === $status ) {

			$error = sprintf(
				/* translators: %s: path to object-cache.php file */
				esc_html__( 'The symlink at %s is no longer pointing to the correct location. Please remove the symlink, then reactivate wp-redis plugin.', 'wp-redis' ),
				'<code>' . esc_html( WP_CONTENT_DIR . '/object-cache.php' ) . '</code>'
			);

			deactivate_plugins( __FILE__ );
			wp_die( wp_kses( $error, array( 'code' => array() ) ) );

		} elseif ( -1 === $status ) {

			$error = sprintf(
				/* translators: %s: path to object-cache.php file */
				esc_html__( 'The file at %s is not a symlink. Please remove the file, then reactivate wp-redis plugin again to create the symlink.', 'wp-redis' ),
				'<code>' . esc_html( WP_CONTENT_DIR . '/object-cache.php' ) . '</code>'
			);

			deactivate_plugins( __FILE__ );
			wp_die( wp_kses( $error, array( 'code' => array() ) ) );

		}
	} else {

		if ( function_exists( 'symlink' ) ) {
			// @codingStandardsIgnoreStart
			@symlink( plugin_dir_path( __FILE__ ) . 'object-cache.php', $db );
			// @codingStandardsIgnoreEnd
		}
	}
}

/**
 * On deactivation: remove the symlinked file if found and is pointing to the correct file
 */
function deactivation() {
	// @codingStandardsIgnoreStart
	if ( true === symlink_status() ) {
		unlink( WP_CONTENT_DIR . '/object-cache.php' );
	}
	// @codingStandardsIgnoreEnd
}

/**
 * Check if the object-cache file is symlinked correctly, warn if not
 *
 * @action admin_notices
 */
function admin_check() {

	if ( ! defined( 'WP_REDIS_OBJECT_CACHE' ) && ! file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {

		printf(
			'<div class="updated error"><p>%s</p></div>',
			sprintf(
				/* translators: %s: path to object-cache.php file */
				esc_html__( 'No file/symlink was found at %s. Please deactivate and reactivate wp-redis plugin to install the required drop-in.', 'wp-redis' ),
				'<code>' . esc_html( WP_CONTENT_DIR . '/object-cache.php' ) . '</code>'
			)
		);

	} elseif ( false === symlink_status() ) {

		printf(
			'<div class="updated error"><p>%s</p></div>',
			sprintf(
				/* translators: %s: path to object-cache.php file */
				esc_html__( 'The symlink at %s is no longer pointing to the correct location. Please remove the symlink, then deactivate and reactivate wp-redis plugin.', 'wp-redis' ),
				'<code>' . esc_html( WP_CONTENT_DIR . '/object-cache.php' ) . '</code>'
			)
		);

	}
}

/**
 * Check symlink status of the object-cache.php file
 *
 *
 * @return bool|int Returns -1 if the file is not symlinked, false if symlink is broken, true if linked correctly
 */
function symlink_status() {

	$dest = WP_CONTENT_DIR . '/object-cache.php';
	$src  = plugin_dir_path( __FILE__ ) . 'object-cache.php';

	if ( ! is_link( $dest ) ) {

		return -1;

	} elseif ( readlink( $dest ) === $src ) {

		return true;

	} else {

		return false;

	}
}

/**
 * Get helpful details on the Redis connection. Used by the WP-CLI command.
 *
 * @return array
 */
function wp_redis_get_info() {
	global $wp_object_cache, $redis_server;

	if ( ! defined( 'WP_REDIS_OBJECT_CACHE' ) || ! WP_REDIS_OBJECT_CACHE ) {
		return new WP_Error( 'wp-redis', 'WP Redis object-cache.php file is missing from the wp-content/ directory.' );
	}

	if ( ! $wp_object_cache->is_redis_connected ) {
		return new WP_Error( 'wp-redis', $wp_object_cache->missing_redis_message );
	}

	$info = $wp_object_cache->redis->info();
	$uptime_in_days = $info['uptime_in_days'];
	if ( 1 === $info['uptime_in_days'] ) {
		$uptime_in_days .= ' day';
	} else {
		$uptime_in_days .= ' days';
	}
	$database = ! empty( $redis_server['database'] ) ? $redis_server['database'] : 0;
	$key_count = 0;
	if ( isset( $info[ 'db' . $database ] ) && preg_match( '#keys=([\d]+)#', $info[ 'db' . $database ], $matches ) ) {
		$key_count = $matches[1];
	}
	return array(
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
	);
}

if ( ! defined( 'WP_REDIS_NO_SYMLINK' ) ) {
	register_activation_hook( __FILE__, __NAMESPACE__ . '\\activation' );
	register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivation' );
	add_action( 'admin_notices', __NAMESPACE__ . '\\admin_check' );
}
