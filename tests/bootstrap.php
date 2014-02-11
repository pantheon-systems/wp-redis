<?php

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

$_core_dir = getenv( 'WP_CORE_DIR' );
if ( ! $_core_dir ) {
	$_core_dir = '/tmp/wordpress';
}

// Easiest way to get this to where WordPress will load it
copy( dirname( __FILE__ ) . '/../object-cache.php', $_core_dir . '/wp-content/object-cache.php' );

require $_tests_dir . '/includes/bootstrap.php';
