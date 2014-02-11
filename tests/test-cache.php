<?php

class CacheTest extends WP_UnitTestCase {

	function testSample() {
		// replace this with some actual testing code
		$this->assertTrue( true );
	}

	/**
	 * Remove the object-cache.php from the place we've dropped it
	 */
	function tearDown() {

		unlink( ABSPATH . 'wp-content/object-cache.php' );
	}
}
