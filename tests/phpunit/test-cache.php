<?php

/**
 * Test the persistent object cache using core's cache tests
 */
class CacheTest extends WP_UnitTestCase {

	private $cache;

	private static $exists_key;

	private static $get_key;

	private static $set_key;

	private static $incr_by_key;

	private static $decr_by_key;

	private static $delete_key;

	private static $flush_all_key;

	private static $client_parameters = array(
		'host'           => 'localhost',
		'port'           => 6379,
		'timeout'        => 1000,
		'retry_interval' => 100,
	);

	public function setUp() {
		parent::setUp();
		$GLOBALS['redis_server'] = array(
			'host' => '127.0.0.1',
			'port' => 6379,
		);
		// create two cache objects with a shared cache dir
		// this simulates a typical cache situation, two separate requests interacting
		$this->cache               =& $this->init_cache();
		$this->cache->cache_hits   = 0;
		$this->cache->cache_misses = 0;
		$this->cache->redis_calls  = array();

		self::$exists_key  = WP_Object_Cache::USE_GROUPS ? 'hExists' : 'exists';
		self::$get_key     = WP_Object_Cache::USE_GROUPS ? 'hGet' : 'get';
		self::$set_key     = WP_Object_Cache::USE_GROUPS ? 'hSet' : 'set';
		self::$incr_by_key = WP_Object_Cache::USE_GROUPS ? 'hIncrBy' : 'incrBy';
		// 'hIncrBy' isn't a typo here — Redis doesn't support decrBy on groups
		self::$decr_by_key   = WP_Object_Cache::USE_GROUPS ? 'hIncrBy' : 'decrBy';
		self::$delete_key    = WP_Object_Cache::USE_GROUPS ? 'hDel' : 'del';
		self::$flush_all_key = 'flushAll';

	}

	public function &init_cache() {
		$cache = new WP_Object_Cache();
		$cache->add_global_groups( array( 'global-cache-test', 'users', 'userlogins', 'usermeta', 'user_meta', 'site-transient', 'site-options', 'site-lookup', 'blog-lookup', 'blog-details', 'rss', 'global-posts', 'blog-id-cache' ) );
		return $cache;
	}

	public function test_loaded() {
		$this->assertTrue( WP_REDIS_OBJECT_CACHE );
	}

	public function test_connection_details() {
		$redis_server = array(
			'host'      => '127.0.0.1',
			'port'      => 6379,
			'extra'     => true,
			'recursive' => array(
				'child' => true,
			),
		);
		$expected     = array(
			'host'           => '127.0.0.1',
			'port'           => 6379,
			'extra'          => true,
			'recursive'      => array(
				'child' => true,
			),
			'timeout'        => 1000,
			'retry_interval' => 100,
		);
		$actual       = $this->cache->build_client_parameters( $redis_server );
		$this->assertEquals( $expected, $actual );
	}

	public function test_redis_connected() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$this->assertTrue( isset( $this->cache->redis ) );
		$this->assertTrue( $this->cache->redis->IsConnected() );
	}

	public function test_redis_reload_connection_closed() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		if ( version_compare( PHP_VERSION, '7.0.0' ) >= 0 ) {
			$this->markTestSkipped( 'Test fails unexpectedly in PHP 7' );
		}
		// Connection is live
		$this->cache->set( 'foo', 'bar' );
		$this->assertTrue( $this->cache->redis->IsConnected() );
		$this->assertTrue( $this->cache->is_redis_connected );
		$this->assertEquals( 'bar', $this->cache->get( 'foo', 'default', true ) );
		// Connection is closed, and refreshed the next time it's requested
		$this->cache->redis->close();
		$this->assertTrue( $this->cache->is_redis_connected );
		$this->assertFalse( $this->cache->redis->IsConnected() );
		// Reload occurs with set()
		$this->cache->set( 'foo', 'banana' );
		$this->assertEquals( 'banana', $this->cache->get( 'foo' ) );
		$this->assertTrue( $this->cache->is_redis_connected );
		$this->assertTrue( $this->cache->redis->IsConnected() );
	}

	public function test_redis_reload_force_cache_flush() {
		global $wpdb, $redis_server;
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}

		if ( version_compare( PHP_VERSION, '7.0.0' ) >= 0 ) {
			$this->markTestSkipped( 'Test fails unexpectedly in PHP 7' );
		}

		if ( is_multisite() ) {
			$table = $wpdb->sitemeta;
			$col1  = 'meta_key';
			$col2  = 'meta_value';
		} else {
			$table = $wpdb->options;
			$col1  = 'option_name';
			$col2  = 'option_value';
		}

		// @codingStandardsIgnoreStart
		$this->assertFalse( (bool) $wpdb->get_results( "SELECT {$col2} FROM {$table} WHERE {$col1}='wp_redis_do_redis_failback_flush'" ) );
		// @codingStandardsIgnoreEnd
		$this->cache->set( 'foo', 'burrito' );
		// Force a bad connection
		$redis_server['host'] = '127.0.0.1';
		$redis_server['port'] = 9999;
		$client_parameters    = $this->cache->build_client_parameters( $redis_server );
		$client_connection    = apply_filters( 'wp_redis_prepare_client_connection_callback', array( $this->cache, 'prepare_client_connection' ) );
		$this->cache->redis   = call_user_func_array( $client_connection, array( $client_parameters ) );
		// Setting cache value when redis connection fails saves wakeup flush
		$this->cache->set( 'foo', 'bar' );
		$this->assertTrue(
			$this->cache->exception_message_matches(
				str_replace( 'WP Redis: ', '', $this->cache->last_triggered_error ),
				$this->cache->retry_exception_messages()
			)
		);
		// @codingStandardsIgnoreStart
		$this->assertEquals( "INSERT IGNORE INTO {$table} ({$col1},{$col2}) VALUES ('wp_redis_do_redis_failback_flush',1)", $wpdb->last_query );
		$this->assertTrue( (bool) $wpdb->get_results( "SELECT {$col2} FROM {$table} WHERE {$col1}='wp_redis_do_redis_failback_flush'" ) );
		// @codingStandardsIgnoreEnd
		$this->assertTrue( $this->cache->do_redis_failback_flush );
		$this->assertEquals( 'bar', $this->cache->get( 'foo' ) );
		// Cache load with bad connection
		$this->cache = $this->init_cache();
		$this->assertTrue( $this->cache->do_redis_failback_flush );
		$this->assertEquals( "SELECT {$col2} FROM {$table} WHERE {$col1}='wp_redis_do_redis_failback_flush'", $wpdb->last_query );
		// Cache load with a restored Redis connection will flush Redis
		$redis_server['port'] = 6379;
		$this->cache          = $this->init_cache();
		$this->assertFalse( $this->cache->do_redis_failback_flush );
		$this->assertEquals( "DELETE FROM {$table} WHERE {$col1}='wp_redis_do_redis_failback_flush'", $wpdb->last_query );
		$this->assertFalse( $this->cache->get( 'foo' ) );
		// Cache load, but Redis shouldn't be flushed again
		$this->cache = $this->init_cache();
		$this->assertFalse( $this->cache->do_redis_failback_flush );
		$this->assertEquals( "SELECT {$col2} FROM {$table} WHERE {$col1}='wp_redis_do_redis_failback_flush'", $wpdb->last_query );
	}

	public function test_redis_bad_authentication() {
		global $redis_server;
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}

		if ( version_compare( PHP_VERSION, '7.0.0' ) >= 0 ) {
			$this->markTestSkipped( 'Test fails unexpectedly in PHP 7' );
		}
		$redis_server['host'] = '127.0.0.1';
		$redis_server['port'] = 9999;
		$redis_server['auth'] = 'foobar';
		$cache                = new WP_Object_Cache;
		$this->assertTrue(
			$cache->exception_message_matches(
				str_replace( 'WP Redis: ', '', $cache->last_triggered_error ),
				$cache->retry_exception_messages()
			)
		);
		$this->assertFalse( $cache->is_redis_connected );
		// Fails back to the internal object cache
		$cache->set( 'foo', 'bar' );
		$this->assertEquals( 'bar', $cache->get( 'foo' ) );
	}

	public function test_miss() {
		$this->assertFalse( $this->cache->get( rand_str() ) );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 1, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$get_key => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_add_get() {
		$key = rand_str();
		$val = rand_str();

		$this->cache->add( $key, $val );
		$this->assertEquals( $val, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key => 1,
					self::$set_key    => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_add_get_0() {
		$key = rand_str();
		$val = 0;

		// you can store zero in the cache
		$this->cache->add( $key, $val );
		$this->assertEquals( $val, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key => 1,
					self::$set_key    => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_add_get_null() {
		$key = rand_str();
		$val = null;

		$this->assertTrue( $this->cache->add( $key, $val ) );
		$this->assertNull( $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key => 1,
					self::$set_key    => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_add() {
		$key  = rand_str();
		$val1 = rand_str();
		$val2 = rand_str();

		// add $key to the cache
		$this->assertTrue( $this->cache->add( $key, $val1 ) );
		$this->assertEquals( $val1, $this->cache->get( $key ) );
		// $key is in the cache, so reject new calls to add()
		$this->assertFalse( $this->cache->add( $key, $val2 ) );
		$this->assertEquals( $val1, $this->cache->get( $key ) );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key => 1,
					self::$set_key    => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_replace() {
		$key  = rand_str();
		$val  = rand_str();
		$val2 = rand_str();

		// memcached rejects replace() if the key does not exist
		$this->assertFalse( $this->cache->replace( $key, $val ) );
		$this->assertFalse( $this->cache->get( $key ) );
		$this->assertTrue( $this->cache->add( $key, $val ) );
		$this->assertEquals( $val, $this->cache->get( $key ) );
		$this->assertTrue( $this->cache->replace( $key, $val2 ) );
		$this->assertEquals( $val2, $this->cache->get( $key ) );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key => 2,
					self::$set_key    => 2,
					self::$get_key    => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_set() {
		$key  = rand_str();
		$val1 = rand_str();
		$val2 = rand_str();

		// memcached accepts set() if the key does not exist
		$this->assertTrue( $this->cache->set( $key, $val1 ) );
		$this->assertEquals( $val1, $this->cache->get( $key ) );
		// Second set() with same key should be allowed
		$this->assertTrue( $this->cache->set( $key, $val2 ) );
		$this->assertEquals( $val2, $this->cache->get( $key ) );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$set_key => 2,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_flush() {

		$key = rand_str();
		$val = rand_str();

		$this->cache->add( $key, $val );
		// item is visible to both cache objects
		$this->assertEquals( $val, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		$this->cache->flush();
		// If there is no value get returns false.
		$this->assertFalse( $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 1, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key    => 1,
					self::$set_key       => 1,
					self::$get_key       => 1,
					self::$flush_all_key => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	// Make sure objects are cloned going to and from the cache
	public function test_object_refs() {
		$key           = rand_str();
		$object_a      = new stdClass;
		$object_a->foo = 'alpha';
		$this->cache->set( $key, $object_a );
		$object_a->foo = 'bravo';
		$object_b      = $this->cache->get( $key );
		$this->assertEquals( 'alpha', $object_b->foo );
		$object_b->foo = 'charlie';
		$this->assertEquals( 'bravo', $object_a->foo );

		$key           = rand_str();
		$object_a      = new stdClass;
		$object_a->foo = 'alpha';
		$this->cache->add( $key, $object_a );
		$object_a->foo = 'bravo';
		$object_b      = $this->cache->get( $key );
		$this->assertEquals( 'alpha', $object_b->foo );
		$object_b->foo = 'charlie';
		$this->assertEquals( 'bravo', $object_a->foo );
	}

	public function test_get_already_exists_internal() {
		$key = rand_str();
		$this->cache->set( $key, 'alpha' );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$set_key => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
		$this->cache->redis_calls = array(); // reset to limit scope of test
		$this->assertEquals( 'alpha', $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		$this->assertEmpty( $this->cache->redis_calls );
	}

	public function test_get_missing_persistent() {
		$key = rand_str();
		$this->cache->get( $key );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 1, $this->cache->cache_misses );
		$this->cache->get( $key );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 2, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$get_key => 2,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_get_non_persistent_group() {
		$key   = rand_str();
		$group = 'nonpersistent';
		$this->cache->add_non_persistent_groups( $group );
		$this->cache->get( $key, $group );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 1, $this->cache->cache_misses );
		$this->assertEmpty( $this->cache->redis_calls );
		$this->cache->get( $key, $group );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 2, $this->cache->cache_misses );
		$this->assertEmpty( $this->cache->redis_calls );
		$this->cache->set( $key, 'alpha', $group );
		$this->cache->get( $key, $group );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 2, $this->cache->cache_misses );
		$this->assertEmpty( $this->cache->redis_calls );
		$this->cache->get( $key, $group );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 2, $this->cache->cache_misses );
		$this->assertEmpty( $this->cache->redis_calls );
	}

	public function test_get_false_value_persistent_cache() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$key = rand_str();
		$this->cache->set( $key, false );
		$this->cache->cache_hits   = 0; // reset everything
		$this->cache->cache_misses = 0; // reset everything
		$this->cache->redis_calls  = array(); // reset everything
		$this->cache->cache        = array(); // reset everything
		$found                     = null;
		$this->assertFalse( $this->cache->get( $key, 'default', false, $found ) );
		$this->assertTrue( $found );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$get_key => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_get_true_value_persistent_cache() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$key = rand_str();
		$this->cache->set( $key, true );
		$this->cache->cache_hits   = 0; // reset everything
		$this->cache->cache_misses = 0; // reset everything
		$this->cache->redis_calls  = array(); // reset everything
		$this->cache->cache        = array(); // reset everything
		$found                     = null;
		$this->assertTrue( $this->cache->get( $key, 'default', false, $found ) );
		$this->assertTrue( $found );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$get_key => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_get_null_value_persistent_cache() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$key = rand_str();
		$this->cache->set( $key, null );
		$this->cache->cache_hits   = 0; // reset everything
		$this->cache->cache_misses = 0; // reset everything
		$this->cache->redis_calls  = array(); // reset everything
		$this->cache->cache        = array(); // reset everything
		$found                     = null;
		$this->assertNull( $this->cache->get( $key, 'default', false, $found ) );
		$this->assertTrue( $found );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$get_key => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_get_int_values_persistent_cache() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$key1 = rand_str();
		$key2 = rand_str();
		$this->cache->set( $key1, 123 );
		$this->cache->set( $key2, 0xf4c3b00c );
		$this->cache->cache_hits   = 0; // reset everything
		$this->cache->cache_misses = 0; // reset everything
		$this->cache->redis_calls  = array(); // reset everything
		$this->cache->cache        = array(); // reset everything
		// Should be upgraded to more strict comparison if change proposed in issue #181 is merged.
		$this->assertSame( 123, $this->cache->get( $key1 ) );
		$this->assertSame( 4106465292, $this->cache->get( $key2 ) );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$get_key => 2,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_get_float_values_persistent_cache() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$key1 = rand_str();
		$key2 = rand_str();
		$this->cache->set( $key1, 123.456 );
		$this->cache->set( $key2, + 0123.45e6 );
		$this->cache->cache_hits   = 0; // reset everything
		$this->cache->cache_misses = 0; // reset everything
		$this->cache->redis_calls  = array(); // reset everything
		$this->cache->cache        = array(); // reset everything
		$this->assertSame( 123.456, $this->cache->get( $key1 ) );
		$this->assertSame( 123450000.0, $this->cache->get( $key2 ) );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$get_key => 2,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_get_string_values_persistent_cache() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$key1 = rand_str();
		$key2 = rand_str();
		$key3 = rand_str();
		$key4 = rand_str();
		$this->cache->set( $key1, 'a plain old string' );
		// To ensure numeric strings are not converted to integers.
		$this->cache->set( $key2, '42' );
		$this->cache->set( $key3, '123.456' );
		$this->cache->set( $key4, '+0123.45e6' );
		$this->cache->cache_hits   = 0; // reset everything
		$this->cache->cache_misses = 0; // reset everything
		$this->cache->redis_calls  = array(); // reset everything
		$this->cache->cache        = array(); // reset everything
		$this->assertEquals( 'a plain old string', $this->cache->get( $key1 ) );
		$this->assertSame( '42', $this->cache->get( $key2 ) );
		$this->assertSame( '123.456', $this->cache->get( $key3 ) );
		$this->assertSame( '+0123.45e6', $this->cache->get( $key4 ) );
		$this->assertEquals( 4, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$get_key => 4,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_get_array_values_persistent_cache() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$key   = rand_str();
		$value = array( 'one', 2, true );
		$this->cache->set( $key, $value );
		$this->cache->cache_hits   = 0; // reset everything
		$this->cache->cache_misses = 0; // reset everything
		$this->cache->redis_calls  = array(); // reset everything
		$this->cache->cache        = array(); // reset everything
		$this->assertEquals( $value, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$get_key => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_get_object_values_persistent_cache() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$key          = rand_str();
		$value        = new stdClass;
		$value->one   = 'two';
		$value->three = 'four';
		$this->cache->set( $key, $value );
		$this->cache->cache_hits   = 0; // reset everything
		$this->cache->cache_misses = 0; // reset everything
		$this->cache->redis_calls  = array(); // reset everything
		$this->cache->cache        = array(); // reset everything
		$this->assertEquals( $value, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$get_key => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_get_force() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}

		$key   = rand_str();
		$group = 'default';
		$this->cache->set( $key, 'alpha', $group );
		$this->assertEquals( 'alpha', $this->cache->get( $key, $group ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		// Duplicate of _set_internal()
		if ( WP_Object_Cache::USE_GROUPS ) {
			$multisite_safe_group = $this->cache->multisite && ! isset( $this->cache->global_groups[ $group ] ) ? $this->cache->blog_prefix . $group : $group;
			if ( ! isset( $this->cache->cache[ $multisite_safe_group ] ) ) {
				$this->cache->cache[ $multisite_safe_group ] = array();
			}
			$this->cache->cache[ $multisite_safe_group ][ $key ] = 'beta';
		} else {
			if ( ! empty( $this->cache->global_groups[ $group ] ) ) {
				$prefix = $this->cache->global_prefix;
			} else {
				$prefix = $this->cache->blog_prefix;
			}

			$true_key                        = preg_replace( '/\s+/', '', WP_CACHE_KEY_SALT . "$prefix$group:$key" );
			$this->cache->cache[ $true_key ] = 'beta';
		}
		$this->assertEquals( 'beta', $this->cache->get( $key, $group ) );
		$this->assertEquals( 'alpha', $this->cache->get( $key, $group, true ) );
		$this->assertEquals( 3, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		$this->assertEquals(
			array(
				self::$get_key => 1,
				self::$set_key => 1,
			),
			$this->cache->redis_calls
		);
	}

	public function test_get_found() {
		$key   = rand_str();
		$found = null;
		$this->cache->get( $key, 'default', false, $found );
		$this->assertFalse( $found );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 1, $this->cache->cache_misses );
		$this->cache->set( $key, 'alpha', 'default' );
		$this->cache->get( $key, 'default', false, $found );
		$this->assertTrue( $found );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 1, $this->cache->cache_misses );
	}

	public function test_incr() {
		$key = rand_str();

		$this->assertFalse( $this->cache->incr( $key ) );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		$this->cache->set( $key, 0 );
		$this->cache->incr( $key );
		$this->assertEquals( 1, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		$this->cache->incr( $key, 2 );
		$this->assertEquals( 3, $this->cache->get( $key ) );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key  => 1,
					self::$set_key     => 1,
					self::$incr_by_key => 2,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_incr_separate_groups() {
		$key    = rand_str();
		$group1 = 'group1';
		$group2 = 'group2';

		$this->assertFalse( $this->cache->incr( $key, 1, $group1 ) );
		$this->assertFalse( $this->cache->incr( $key, 1, $group2 ) );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		$this->cache->set( $key, 0, $group1 );
		$this->cache->incr( $key, 1, $group1 );
		$this->cache->set( $key, 0, $group2 );
		$this->cache->incr( $key, 1, $group2 );
		$this->assertEquals( 1, $this->cache->get( $key, $group1 ) );
		$this->assertEquals( 1, $this->cache->get( $key, $group2 ) );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		$this->cache->incr( $key, 2, $group1 );
		$this->cache->incr( $key, 1, $group2 );
		$this->assertEquals( 3, $this->cache->get( $key, $group1 ) );
		$this->assertEquals( 2, $this->cache->get( $key, $group2 ) );
		$this->assertEquals( 4, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key  => 2,
					self::$set_key     => 2,
					self::$incr_by_key => 4,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_incr_never_below_zero() {
		$key = rand_str();
		$this->cache->set( $key, 1 );
		$this->assertEquals( 1, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		$this->cache->incr( $key, -2 );
		$this->assertEquals( 0, $this->cache->get( $key ) );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$incr_by_key => 1,
					self::$set_key     => 2,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_incr_non_persistent() {
		$key = rand_str();

		$this->cache->add_non_persistent_groups( array( 'nonpersistent' ) );
		$this->assertFalse( $this->cache->incr( $key, 1, 'nonpersistent' ) );

		$this->cache->set( $key, 0, 'nonpersistent' );
		$this->cache->incr( $key, 1, 'nonpersistent' );
		$this->assertEquals( 1, $this->cache->get( $key, 'nonpersistent' ) );

		$this->cache->incr( $key, 2, 'nonpersistent' );
		$this->assertEquals( 3, $this->cache->get( $key, 'nonpersistent' ) );
		$this->assertEmpty( $this->cache->redis_calls );
	}

	public function test_incr_non_persistent_never_below_zero() {
		$key = rand_str();
		$this->cache->add_non_persistent_groups( array( 'nonpersistent' ) );
		$this->cache->set( $key, 1, 'nonpersistent' );
		$this->assertEquals( 1, $this->cache->get( $key, 'nonpersistent' ) );
		$this->cache->incr( $key, -2, 'nonpersistent' );
		$this->assertEquals( 0, $this->cache->get( $key, 'nonpersistent' ) );
		$this->assertEmpty( $this->cache->redis_calls );
	}

	public function test_wp_cache_incr() {
		$key = rand_str();

		$this->assertFalse( wp_cache_incr( $key ) );

		wp_cache_set( $key, 0 );
		wp_cache_incr( $key );
		$this->assertEquals( 1, wp_cache_get( $key ) );

		wp_cache_incr( $key, 2 );
		$this->assertEquals( 3, wp_cache_get( $key ) );
	}

	public function test_decr() {
		$key = rand_str();

		$this->assertFalse( $this->cache->decr( $key ) );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		$this->cache->set( $key, 0 );
		$this->cache->decr( $key );
		$this->assertEquals( 0, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		$this->cache->set( $key, 3 );
		$this->cache->decr( $key );
		$this->assertEquals( 2, $this->cache->get( $key ) );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		$this->cache->decr( $key, 2 );
		$this->assertEquals( 0, $this->cache->get( $key ) );
		$this->assertEquals( 3, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key  => 1,
					self::$set_key     => 3,
					self::$decr_by_key => 3,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_decr_separate_groups() {
		$key    = rand_str();
		$group1 = 'group1';
		$group2 = 'group2';

		$this->assertFalse( $this->cache->decr( $key, 1, $group1 ) );
		$this->assertFalse( $this->cache->decr( $key, 1, $group2 ) );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		$this->cache->set( $key, 0, $group1 );
		$this->cache->decr( $key, 1, $group1 );
		$this->cache->set( $key, 0, $group2 );
		$this->cache->decr( $key, 1, $group2 );
		$this->assertEquals( 0, $this->cache->get( $key, $group1 ) );
		$this->assertEquals( 0, $this->cache->get( $key, $group2 ) );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		$this->cache->set( $key, 3, $group1 );
		$this->cache->decr( $key, 1, $group1 );
		$this->cache->set( $key, 2, $group2 );
		$this->cache->decr( $key, 1, $group2 );
		$this->assertEquals( 2, $this->cache->get( $key, $group1 ) );
		$this->assertEquals( 1, $this->cache->get( $key, $group2 ) );
		$this->assertEquals( 4, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		$this->cache->decr( $key, 2, $group1 );
		$this->cache->decr( $key, 2, $group2 );
		$this->assertEquals( 0, $this->cache->get( $key, $group1 ) );
		$this->assertEquals( 0, $this->cache->get( $key, $group2 ) );
		$this->assertEquals( 6, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key  => 2,
					self::$set_key     => 7,
					self::$decr_by_key => 6,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_decr_never_below_zero() {
		$key = rand_str();
		$this->cache->set( $key, 1 );
		$this->assertEquals( 1, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		$this->cache->decr( $key, 2 );
		$this->assertEquals( 0, $this->cache->get( $key ) );
		$this->assertEquals( 2, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$decr_by_key => 1,
					self::$set_key     => 2,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_decr_non_persistent() {
		$key = rand_str();

		$this->cache->add_non_persistent_groups( array( 'nonpersistent' ) );
		$this->assertFalse( $this->cache->decr( $key, 1, 'nonpersistent' ) );

		$this->cache->set( $key, 0, 'nonpersistent' );
		$this->cache->decr( $key, 1, 'nonpersistent' );
		$this->assertEquals( 0, $this->cache->get( $key, 'nonpersistent' ) );

		$this->cache->set( $key, 3, 'nonpersistent' );
		$this->cache->decr( $key, 1, 'nonpersistent' );
		$this->assertEquals( 2, $this->cache->get( $key, 'nonpersistent' ) );

		$this->cache->decr( $key, 2, 'nonpersistent' );
		$this->assertEquals( 0, $this->cache->get( $key, 'nonpersistent' ) );
		$this->assertEmpty( $this->cache->redis_calls );
	}

	public function test_decr_non_persistent_never_below_zero() {
		$key = rand_str();
		$this->cache->add_non_persistent_groups( array( 'nonpersistent' ) );
		$this->cache->set( $key, 1, 'nonpersistent' );
		$this->assertEquals( 1, $this->cache->get( $key, 'nonpersistent' ) );
		$this->cache->decr( $key, 2, 'nonpersistent' );
		$this->assertEquals( 0, $this->cache->get( $key, 'nonpersistent' ) );
		$this->assertEmpty( $this->cache->redis_calls );
	}

	/**
	 * @group 21327
	 */
	public function test_wp_cache_decr() {
		$key = rand_str();

		$this->assertFalse( wp_cache_decr( $key ) );

		wp_cache_set( $key, 0 );
		wp_cache_decr( $key );
		$this->assertEquals( 0, wp_cache_get( $key ) );

		wp_cache_set( $key, 3 );
		wp_cache_decr( $key );
		$this->assertEquals( 2, wp_cache_get( $key ) );

		wp_cache_decr( $key, 2 );
		$this->assertEquals( 0, wp_cache_get( $key ) );
	}

	public function test_delete() {
		$key = rand_str();
		$val = rand_str();

		// Verify set
		$this->assertTrue( $this->cache->set( $key, $val ) );
		$this->assertEquals( $val, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );

		// Verify successful delete
		$this->assertTrue( $this->cache->delete( $key ) );
		$this->assertFalse( $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 1, $this->cache->cache_misses );

		$this->assertFalse( $this->cache->delete( $key, 'default' ) );
		if ( $this->cache->is_redis_connected ) {
			$this->assertEquals(
				array(
					self::$exists_key => 1,
					self::$set_key    => 1,
					self::$delete_key => 1,
					self::$get_key    => 1,
				),
				$this->cache->redis_calls
			);
		} else {
			$this->assertEmpty( $this->cache->redis_calls );
		}
	}

	public function test_wp_cache_delete() {
		$key = rand_str();
		$val = rand_str();

		// Verify set
		$this->assertTrue( wp_cache_set( $key, $val ) );
		$this->assertEquals( $val, wp_cache_get( $key ) );

		// Verify successful delete
		$this->assertTrue( wp_cache_delete( $key ) );
		$this->assertFalse( wp_cache_get( $key ) );

		// wp_cache_delete() does not have a $force method.
		// Delete returns (bool) true when key is not set and $force is true
		// $this->assertTrue( wp_cache_delete( $key, 'default', true ) );

		$this->assertFalse( wp_cache_delete( $key, 'default' ) );
	}

	public function test_delete_group() {
		$key1   = rand_str();
		$val1   = rand_str();
		$key2   = rand_str();
		$val2   = rand_str();
		$key3   = rand_str();
		$val3   = rand_str();
		$group  = 'foo';
		$group2 = 'bar';

		if ( ! defined( 'WP_REDIS_USE_CACHE_GROUPS' ) || ! WP_REDIS_USE_CACHE_GROUPS ) {
			$this->cache->add_redis_hash_groups( array( $group, $group2 ) );
		}

		// Set up the values
		$this->cache->set( $key1, $val1, $group );
		$this->cache->set( $key2, $val2, $group );
		$this->cache->set( $key3, $val3, $group2 );
		$this->assertEquals( $val1, $this->cache->get( $key1, $group ) );
		$this->assertEquals( $val2, $this->cache->get( $key2, $group ) );
		$this->assertEquals( $val3, $this->cache->get( $key3, $group2 ) );

		$this->assertTrue( $this->cache->delete_group( $group ) );

		$this->assertFalse( $this->cache->get( $key1, $group ) );
		$this->assertFalse( $this->cache->get( $key2, $group ) );
		$this->assertEquals( $val3, $this->cache->get( $key3, $group2 ) );

		// _call_redis( 'delete' ) always returns true when Redis isn't available
		if ( class_exists( 'Redis' ) ) {
			$this->assertFalse( $this->cache->delete_group( $group ) );
		} else {
			$this->assertTrue( $this->cache->delete_group( $group ) );
		}
	}

	public function test_delete_group_non_persistent() {
		$key1   = rand_str();
		$val1   = rand_str();
		$key2   = rand_str();
		$val2   = rand_str();
		$key3   = rand_str();
		$val3   = rand_str();
		$group  = 'foo';
		$group2 = 'bar';
		$this->cache->add_non_persistent_groups( array( $group, $group2 ) );

		if ( ! defined( 'WP_REDIS_USE_CACHE_GROUPS' ) || ! WP_REDIS_USE_CACHE_GROUPS ) {
			$this->cache->add_redis_hash_groups( array( $group, $group2 ) );
		}

		// Set up the values
		$this->cache->set( $key1, $val1, $group );
		$this->cache->set( $key2, $val2, $group );
		$this->cache->set( $key3, $val3, $group2 );
		$this->assertEquals( $val1, $this->cache->get( $key1, $group ) );
		$this->assertEquals( $val2, $this->cache->get( $key2, $group ) );
		$this->assertEquals( $val3, $this->cache->get( $key3, $group2 ) );

		$this->assertTrue( $this->cache->delete_group( $group ) );

		$this->assertFalse( $this->cache->get( $key1, $group ) );
		$this->assertFalse( $this->cache->get( $key2, $group ) );
		$this->assertEquals( $val3, $this->cache->get( $key3, $group2 ) );

		$this->assertFalse( $this->cache->delete_group( $group ) );
	}

	public function test_wp_cache_delete_group() {

		$key1   = rand_str();
		$val1   = rand_str();
		$key2   = rand_str();
		$val2   = rand_str();
		$key3   = rand_str();
		$val3   = rand_str();
		$group  = 'foo';
		$group2 = 'bar';

		if ( ! defined( 'WP_REDIS_USE_CACHE_GROUPS' ) || ! WP_REDIS_USE_CACHE_GROUPS ) {
			$GLOBALS['wp_object_cache']->add_redis_hash_groups( array( $group, $group2 ) );
		}

		// Set up the values
		wp_cache_set( $key1, $val1, $group );
		wp_cache_set( $key2, $val2, $group );
		wp_cache_set( $key3, $val3, $group2 );
		$this->assertEquals( $val1, wp_cache_get( $key1, $group ) );
		$this->assertEquals( $val2, wp_cache_get( $key2, $group ) );
		$this->assertEquals( $val3, wp_cache_get( $key3, $group2 ) );

		$this->assertTrue( wp_cache_delete_group( $group ) );

		$this->assertFalse( wp_cache_get( $key1, $group ) );
		$this->assertFalse( wp_cache_get( $key2, $group ) );
		$this->assertEquals( $val3, wp_cache_get( $key3, $group2 ) );

		// _call_redis( 'delete' ) always returns true when Redis isn't available
		if ( class_exists( 'Redis' ) ) {
			$this->assertFalse( wp_cache_delete_group( $group ) );
		} else {
			$this->assertTrue( wp_cache_delete_group( $group ) );
		}
	}

	public function test_switch_to_blog() {
		if ( ! method_exists( $this->cache, 'switch_to_blog' ) ) {
			return;
		}

		$key  = rand_str();
		$val  = rand_str();
		$val2 = rand_str();

		if ( ! is_multisite() ) {
			// Single site ingnores switch_to_blog().
			$this->assertTrue( $this->cache->set( $key, $val ) );
			$this->assertEquals( $val, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( 999 );
			$this->assertEquals( $val, $this->cache->get( $key ) );
			$this->assertTrue( $this->cache->set( $key, $val2 ) );
			$this->assertEquals( $val2, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( get_current_blog_id() );
			$this->assertEquals( $val2, $this->cache->get( $key ) );
		} else {
			// Multisite should have separate per-blog caches
			$this->assertTrue( $this->cache->set( $key, $val ) );
			$this->assertEquals( $val, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( 999 );
			$this->assertFalse( $this->cache->get( $key ) );
			$this->assertTrue( $this->cache->set( $key, $val2 ) );
			$this->assertEquals( $val2, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( get_current_blog_id() );
			$this->assertEquals( $val, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( 999 );
			$this->assertEquals( $val2, $this->cache->get( $key ) );
			$this->cache->switch_to_blog( get_current_blog_id() );
			$this->assertEquals( $val, $this->cache->get( $key ) );
		}

		// Global group
		$this->assertTrue( $this->cache->set( $key, $val, 'global-cache-test' ) );
		$this->assertEquals( $val, $this->cache->get( $key, 'global-cache-test' ) );
		$this->cache->switch_to_blog( 999 );
		$this->assertEquals( $val, $this->cache->get( $key, 'global-cache-test' ) );
		$this->assertTrue( $this->cache->set( $key, $val2, 'global-cache-test' ) );
		$this->assertEquals( $val2, $this->cache->get( $key, 'global-cache-test' ) );
		$this->cache->switch_to_blog( get_current_blog_id() );
		$this->assertEquals( $val2, $this->cache->get( $key, 'global-cache-test' ) );
	}

	public function test_wp_cache_init() {
		$new_blank_cache_object = new WP_Object_Cache();
		wp_cache_init();

		global $wp_object_cache;
		// Differs from core tests because we'll have two different Redis sockets
		$this->assertEquals( $wp_object_cache->cache, $new_blank_cache_object->cache );
	}

	public function test_redis_connect_custom_database() {
		global $redis_server;
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$redis_server['database'] = 2;
		$second_cache             = new WP_Object_Cache;
		$this->cache->set( 'foo', 'bar' );
		$this->assertEquals( 'bar', $this->cache->get( 'foo' ) );
		$this->assertFalse( $second_cache->get( 'foo' ) );
		$second_cache->set( 'foo', 'apple' );
		$this->assertEquals( 'apple', $second_cache->get( 'foo' ) );
		$this->assertEquals( 'bar', $this->cache->get( 'foo' ) );
	}

	public function test_wp_cache_replace() {
		$key  = 'my-key';
		$val1 = 'first-val';
		$val2 = 'second-val';

		$fake_key = 'my-fake-key';

		// Save the first value to cache and verify
		wp_cache_set( $key, $val1 );
		$this->assertEquals( $val1, wp_cache_get( $key ) );

		// Replace the value and verify
		wp_cache_replace( $key, $val2 );
		$this->assertEquals( $val2, wp_cache_get( $key ) );

		// Non-existant key should fail
		$this->assertFalse( wp_cache_replace( $fake_key, $val1 ) );

		// Make sure $fake_key is not stored
		$this->assertFalse( wp_cache_get( $fake_key ) );
	}

	public function test_wp_redis_get_info() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}
		$data = wp_redis_get_info();
		$this->assertEquals( 'connected', $data['status'] );
		$this->assertInternalType( 'int', $data['key_count'] );
		$this->assertRegExp( '/[\d]+\/sec/', $data['instantaneous_ops'] );
		$this->assertRegExp( '/[\d]+\sdays?/', $data['uptime'] );
	}

	public function test_dependencies() {
		$result = $this->cache->check_client_dependencies();
		if ( class_exists( 'Redis' ) ) {
			$this->assertTrue( $result );
		} else {
			$this->assertTrue( is_string( $result ) );
		}
	}

	public function test_redis_client_connection() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}

		$redis = $this->cache->prepare_client_connection( self::$client_parameters );
		$this->assertTrue( $redis->isConnected() );
	}

	public function test_setup_connection() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}

		$redis   = $this->cache->prepare_client_connection( self::$client_parameters );
		$isSetUp = $this->cache->perform_client_connection( $redis, array(), array() );
		$this->assertTrue( $isSetUp );
	}

	public function test_setup_connection_throws_exception() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}

		$redis = $this->getMockBuilder( 'Redis' )->getMock();
		$redis->method( 'select' )
			->will( $this->throwException( new RedisException ) );

		$redis->connect(
			self::$client_parameters['host'],
			self::$client_parameters['port'],
			self::$client_parameters['timeout'],
			null,
			self::$client_parameters['retry_interval']
		);
		$settings     = array(
			'database' => 2,
		);
		$keys_methods = array(
			'database' => 'select',
		);
		$this->setExpectedException( 'Exception' );
		$this->cache->perform_client_connection( $redis, $settings, $keys_methods );
	}

	public function tearDown() {
		parent::tearDown();
		$this->flush_cache();
	}

	/**
	 * Remove the object-cache.php from the place we've dropped it
	 */
	static function tearDownAfterClass() {
		// @codingStandardsIgnoreStart
		unlink( ABSPATH . 'wp-content/object-cache.php' );
		// @codingStandardsIgnoreEnd
	}
}
