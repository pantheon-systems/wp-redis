<?php

/**
 * Various WP Redis utility commands.
 */
class WP_Redis_CLI_Command {

	/**
	 * Launch redis-cli using Redis configuration for WordPress
	 */
	public function cli() {
		global $redis_server;

		if ( empty( $redis_server ) ) {
			# Attempt to automatically load Pantheon's Redis config from the env.
			if ( isset( $_SERVER['CACHE_HOST'] ) ) {
				$redis_server = array(
					'host'     => $_SERVER['CACHE_HOST'],
					'port'     => $_SERVER['CACHE_PORT'],
					'auth'     => $_SERVER['CACHE_PASSWORD'],
					'database' => isset( $_SERVER['CACHE_DB'] ) ? $_SERVER['CACHE_DB'] : 0,
				);
			} else {
				$redis_server = array(
					'host'     => '127.0.0.1',
					'port'     => 6379,
					'auth'     => '',
					'database' => 0,
				);
			}
		}

		if ( ! isset( $redis_server['database'] ) ) {
			$redis_server['database'] = 0;
		}

		$cmd     = WP_CLI\Utils\esc_cmd( 'redis-cli -h %s -p %s -a %s -n %s', $redis_server['host'], $redis_server['port'], $redis_server['auth'], $redis_server['database'] );
		$process = WP_CLI\Utils\proc_open_compat( $cmd, array( STDIN, STDOUT, STDERR ), $pipes );
		$r       = proc_close( $process );
		exit( (int) $r );
	}

	/**
	 * Debug object cache hit / miss ratio for any page URL.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : Execute a request against a specified URL. Defaults to home_url( '/' ).
	 *
	 * [--format=<format>]
	 * : Render the results in a particular format.
	 *
	 * @when before_wp_load
	 */
	public function debug( $_, $assoc_args ) {
		global $wp_object_cache;
		$this->load_wordpress_with_template();
		$data = array(
			'cache_hits'   => $wp_object_cache->cache_hits,
			'cache_misses' => $wp_object_cache->cache_misses,
			'redis_calls'  => $wp_object_cache->redis_calls,
		);
		WP_CLI::print_value( $data, $assoc_args );
	}

	/**
	 * Enable WP Redis by creating the symlink for object-cache.php
	 */
	public function enable() {
		if ( defined( 'WP_REDIS_OBJECT_CACHE' ) && WP_REDIS_OBJECT_CACHE ) {
			WP_CLI::success( 'WP Redis is already enabled.' );
			return;
		}
		$drop_in = WP_CONTENT_DIR . '/object-cache.php';
		if ( file_exists( $drop_in ) ) {
			WP_CLI::error( 'Unknown wp-content/object-cache.php already exists.' );
		}
		$object_cache = dirname( __FILE__ ) . '/object-cache.php';
		$target       = self::get_relative_path( $drop_in, $object_cache );
		chdir( WP_CONTENT_DIR );
		// @codingStandardsIgnoreStart
		if ( symlink( $target, 'object-cache.php' ) ) {
			// @codingStandardsIgnoreEnd
			WP_CLI::success( 'Enabled WP Redis by creating wp-content/object-cache.php symlink.' );
		} else {
			WP_CLI::error( 'Failed create wp-content/object-cache.php symlink and enable WP Redis.' );
		}
	}

	/**
	 * Provide details on the Redis connection.
	 *
	 * ## OPTIONS
	 *
	 * [--reset]
	 * : Reset Redis stats. Only affects `lifetime_hitrate` currently.
	 *
	 * [--field=<field>]
	 * : Get the value of a particular field.
	 *
	 * [--format=<format>]
	 * : Render results in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp redis info
	 *     +-------------------+-----------+
	 *     | Field             | Value     |
	 *     +-------------------+-----------+
	 *     | status            | connected |
	 *     | used_memory       | 529.25K   |
	 *     | uptime            | 0 days    |
	 *     | key_count         | 20        |
	 *     | instantaneous_ops | 9/sec     |
	 *     | lifetime_hitrate  | 53.42%    |
	 *     | redis_host        | 127.0.0.1 |
	 *     | redis_port        | 6379      |
	 *     | redis_auth        |           |
	 *     | redis_database    | 0         |
	 *     +-------------------+-----------+
	 *
	 *     $ wp redis info --field=used_memory
	 *     529.38K
	 *
	 *     $ wp redis info --reset
	 *     Success: Redis stats reset.
	 */
	public function info( $_, $assoc_args ) {
		global $wp_object_cache, $redis_server;

		if ( ! defined( 'WP_REDIS_OBJECT_CACHE' ) || ! WP_REDIS_OBJECT_CACHE ) {
			WP_CLI::error( 'WP Redis object-cache.php file is missing from the wp-content/ directory.' );
		}

		if ( $wp_object_cache->is_redis_connected && WP_CLI\Utils\get_flag_value( $assoc_args, 'reset' ) ) {
			// Redis::resetStat() isn't functional, see https://github.com/phpredis/phpredis/issues/928
			if ( $wp_object_cache->redis->eval( "return redis.call('CONFIG','RESETSTAT')" ) ) {
				WP_CLI::success( 'Redis stats reset.' );
			} else {
				WP_CLI::error( "Couldn't reset Redis stats." );
			}
		} else {
			$data = wp_redis_get_info();
			if ( is_wp_error( $data ) ) {
				WP_CLI::error( $data );
			}
			$formatter = new \WP_CLI\Formatter( $assoc_args, array_keys( $data ) );
			$formatter->display_item( $data );
		}
	}

	/**
	 * Runs through the entirety of the WP bootstrap process
	 */
	private function load_wordpress_with_template() {
		global $wp_query;

		WP_CLI::get_runner()->load_wordpress();

		// Set up the main WordPress query.
		wp();

		$interpreted = array();
		foreach ( $wp_query as $key => $value ) {
			if ( 0 === stripos( $key, 'is_' ) && $value ) {
				$interpreted[] = $key;
			}
		}
		WP_CLI::debug( 'Main WP_Query: ' . implode( ', ', $interpreted ), 'redis-debug' );

		define( 'WP_USE_THEMES', true );

		add_filter(
			'template_include',
			function( $template ) {
				$display_template = str_replace( dirname( get_template_directory() ) . '/', '', $template );
				WP_CLI::debug( "Theme template: {$display_template}", 'redis-debug' );
				return $template;
			},
			999
		);

		// Template is normally loaded in global scope, so we need to replicate
		foreach ( $GLOBALS as $key => $value ) {
			// phpcs:ignore PHPCompatibility.Variables.ForbiddenGlobalVariableVariable.NonBareVariableFound
			global $$key;
		}

		// Load the theme template.
		ob_start();
		require_once( ABSPATH . WPINC . '/template-loader.php' );
		ob_get_clean();
	}

	/**
	 * Get the relative path between two files
	 *
	 * @see http://stackoverflow.com/questions/2637945/getting-relative-path-from-absolute-path-in-php
	 */
	private static function get_relative_path( $from, $to ) {
		// some compatibility fixes for Windows paths
		$from = is_dir( $from ) ? rtrim( $from, '\/' ) . '/' : $from;
		$to   = is_dir( $to ) ? rtrim( $to, '\/' ) . '/' : $to;
		$from = str_replace( '\\', '/', $from );
		$to   = str_replace( '\\', '/', $to );

		$from     = explode( '/', $from );
		$to       = explode( '/', $to );
		$rel_path = $to;

		foreach ( $from as $depth => $dir ) {
			// find first non-matching dir
			if ( $dir === $to[ $depth ] ) {
				// ignore this directory
				array_shift( $rel_path );
			} else {
				// get number of remaining dirs to $from
				$remaining = count( $from ) - $depth;
				if ( $remaining > 1 ) {
					// add traversals up to first matching dir
					$pad_length = ( count( $rel_path ) + $remaining - 1 ) * -1;
					$rel_path   = array_pad( $rel_path, $pad_length, '..' );
					break;
				} else {
					$rel_path[0] = './' . $rel_path[0];
				}
			}
		}
		return implode( '/', $rel_path );
	}

}

WP_CLI::add_command( 'redis', 'WP_Redis_CLI_Command' );
