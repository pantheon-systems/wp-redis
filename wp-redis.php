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
			wp_die( $error );

		} elseif ( -1 === $status ) {

			$error = sprintf(
				/* translators: %s: path to object-cache.php file */
				esc_html__( 'The file at %s is not a symlink. Please remove the file, then reactivate wp-redis plugin again to create the symlink.', 'wp-redis' ),
				'<code>' . esc_html( WP_CONTENT_DIR . '/object-cache.php' ) . '</code>'
			);

			deactivate_plugins( __FILE__ );
			wp_die( $error );

		}
	} else {

		if ( function_exists( 'symlink' ) ) {
			@symlink( plugin_dir_path( __FILE__ ) . 'object-cache.php', $db );
		}
	}
}

/**
 * On deactivation: remove the symlinked file if found and is pointing to the correct file
 */
function deactivation() {

	if ( true === symlink_status() ) {
		unlink( WP_CONTENT_DIR . '/object-cache.php' );
	}
}

/**
 * Check if the object-cache file is symlinked correctly, warn if not
 *
 * @action admin_notices
 */
function admin_check() {

	if ( ! defined( 'WP_REDIS_OBJECT_CACHE' ) && ! file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {

		$error = sprintf(
		/* translators: %s: path to object-cache.php file */
			esc_html__( 'No file/symlink was found at %s. Please deactivate and reactivate wp-redis plugin to install the required drop-in.', 'wp-redis' ),
			'<code>' . esc_html( WP_CONTENT_DIR . '/object-cache.php' ) . '</code>'
		);

		printf( '<div class="updated error"><p>%s</p></div>', $error );

	} elseif ( false === symlink_status() ) {

		$error = sprintf(
			/* translators: %s: path to object-cache.php file */
			esc_html__( 'The symlink at %s is no longer pointing to the correct location. Please remove the symlink, then deactivate and reactivate wp-redis plugin.', 'wp-redis' ),
			'<code>' . esc_html( WP_CONTENT_DIR . '/object-cache.php' ) . '</code>'
		);

		printf( '<div class="updated error"><p>%s</p></div>', $error );

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

	} elseif ( $src === readlink( $dest ) ) {

		return true;

	} else {

		return false;

	}
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activation' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivation' );
add_action( 'admin_notices', __NAMESPACE__ . '\\admin_check' );
