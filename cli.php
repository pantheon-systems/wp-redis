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
