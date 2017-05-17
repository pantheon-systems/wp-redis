<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

if ( getenv( 'WP_CORE_DIR' ) ) {
	$_core_dir = getenv( 'WP_CORE_DIR' );
} elseif ( getenv( 'WP_DEVELOP_DIR' ) ) {
	$_core_dir = getenv( 'WP_DEVELOP_DIR' ) . '/src/';
} else {
	$_core_dir = '/tmp/wordpress';
}

if ( getenv( 'WP_REDIS_USE_CACHE_GROUPS' ) ) {
	define( 'WP_REDIS_USE_CACHE_GROUPS', true );
}

// Easiest way to get this to where WordPress will load it
copy( dirname( dirname( dirname( __FILE__ ) ) ) . '/object-cache.php', $_core_dir . '/wp-content/object-cache.php' );

function _manually_load_plugin() {
	require dirname( dirname( dirname( __FILE__ ) ) ) . '/wp-redis.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

error_log( PHP_EOL );
$phpredis_state = class_exists( 'Redis' ) ? 'enabled' : 'disabled';
error_log( 'PhpRedis: ' . $phpredis_state . PHP_EOL );
$cache_groups_state = defined( 'WP_REDIS_USE_CACHE_GROUPS' ) && WP_REDIS_USE_CACHE_GROUPS ? 'enabled' : 'disabled';
error_log( 'Cache groups: ' . $cache_groups_state . PHP_EOL );
error_log( PHP_EOL );
