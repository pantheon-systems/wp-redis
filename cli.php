<?php

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
					'host' => $_SERVER['CACHE_HOST'],
					'port' => $_SERVER['CACHE_PORT'],
					'auth' => $_SERVER['CACHE_PASSWORD'],
				);
			} else {
				$redis_server = array(
					'host' => '127.0.0.1',
					'port' => 6379,
				);
			}
		}

		$cmd = WP_CLI\Utils\esc_cmd( 'redis-cli -h %s -p %s -a %s', $redis_server['host'], $redis_server['port'], $redis_server['auth'] );
		WP_CLI::launch( $cmd );

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
			'cache_hits'      => $wp_object_cache->cache_hits,
			'cache_misses'    => $wp_object_cache->cache_misses,
			'redis_calls'     => $wp_object_cache->redis_calls,
		);
		WP_CLI::print_value( $data, $assoc_args );
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
			if ( $wp_object_cache->redis->eval("return redis.call('CONFIG','RESETSTAT')") ) {
				WP_CLI::success( 'Redis stats reset.' );
			} else {
				WP_CLI::error( "Couldn't reset Redis stats." );
			}
		} else if ( $wp_object_cache->is_redis_connected ) {
			$info = $wp_object_cache->redis->info();
			$uptime_in_days = $info['uptime_in_days'];
			if ( 1 === $info['uptime_in_days'] ) {
				$uptime_in_days .= ' day';
			} else {
				$uptime_in_days .= ' days';
			}
			$database = ! empty( $redis_server['database'] ) ? $redis_server['database'] : 0;
			$key_count = 0;
			if ( preg_match( '#keys=([\d]+)#', $info[ 'db' . $database ], $matches ) ) {
				$key_count = $matches[1];
			}
			$data = array(
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
			$formatter = new \WP_CLI\Formatter( $assoc_args, array_keys( $data ) );
			$formatter->display_item( $data );
		} else {
			WP_CLI::error( $wp_object_cache->missing_redis_message );
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

		add_filter( 'template_include', function( $template ) {
			$display_template = str_replace( dirname( get_template_directory() ) . '/', '', $template );
			WP_CLI::debug( "Theme template: {$display_template}", 'redis-debug' );
			return $template;
		}, 999 );

		// Template is normally loaded in global scope, so we need to replicate
		foreach ( $GLOBALS as $key => $value ) {
			global $$key;
		}

		// Load the theme template.
		ob_start();
		require_once( ABSPATH . WPINC . '/template-loader.php' );
		ob_get_clean();
	}

}

WP_CLI::add_command( 'redis', 'WP_Redis_CLI_Command' );
