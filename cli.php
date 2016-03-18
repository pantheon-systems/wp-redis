<?php

class WP_Redis_CLI_Command {

	/**
	 * Launch redis-cli using Redis configuration for WordPress
	 */
	public function __invoke() {
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

}

WP_CLI::add_command( 'redis-cli', 'WP_Redis_CLI_Command' );
