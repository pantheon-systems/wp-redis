<?php

/**
 * Test the persistent object cache using core's cache tests
 */
class CacheTest extends WP_UnitTestCase {

	private $cache;

	public function setUp() {
		parent::setUp();
		$GLOBALS['redis_server'] = array(
			'host'    => '127.0.0.1',
			'port'    => 6379,
		);
		// create two cache objects with a shared cache dir
		// this simulates a typical cache situation, two separate requests interacting
		$this->cache =& $this->init_cache();
		$this->cache->cache_hits = $this->cache->cache_misses = 0;
	}

	public function &init_cache() {
		$cache = new WP_Object_Cache();
		$cache->add_global_groups( array( 'global-cache-test', 'users', 'userlogins', 'usermeta', 'user_meta', 'site-transient', 'site-options', 'site-lookup', 'blog-lookup', 'blog-details', 'rss', 'global-posts', 'blog-id-cache' ) );
		return $cache;
	}

	public function test_loaded() {
		$this->assertTrue( WP_REDIS_OBJECT_CACHE );
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
		$this->assertEquals( 'WP Redis: Connection closed', $this->cache->last_triggered_error );
		$this->assertEquals( 'banana', $this->cache->get( 'foo' ) );
		$this->assertTrue( $this->cache->is_redis_connected );
		$this->assertTrue( $this->cache->redis->IsConnected() );
	}

	public function test_redis_reload_force_cache_flush() {
		global $wpdb, $redis_server;
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}

		if ( is_multisite() ) {
			$table = $wpdb->sitemeta;
			$col1 = 'meta_key';
			$col2 = 'meta_value';
		} else {
			$table = $wpdb->options;
			$col1 = 'option_name';
			$col2 = 'option_value';
		}

		// @codingStandardsIgnoreStart
		$this->assertFalse( (bool) $wpdb->get_results( "SELECT {$col2} FROM {$table} WHERE {$col1}='wp_redis_do_redis_failback_flush'" ) );
		// @codingStandardsIgnoreEnd
		$this->cache->set( 'foo', 'burrito' );
		// Force a bad connection
		$redis_server['host'] = '127.0.0.1';
		$redis_server['port'] = 9999;
		$this->cache->redis->connect( $redis_server['host'], $redis_server['port'], 1, null, 100 );
		// Setting cache value when redis connection fails saves wakeup flush
		$this->cache->set( 'foo', 'bar' );
		$this->assertEquals( 'WP Redis: Redis server went away', $this->cache->last_triggered_error );
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
		$this->cache = $this->init_cache();
		$this->assertFalse( $this->cache->do_redis_failback_flush );
		$this->assertEquals( "DELETE FROM {$table} WHERE {$col1}='wp_redis_do_redis_failback_flush'", $wpdb->last_query );
		$this->assertEquals( null, $this->cache->get( 'foo' ) );
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
		$redis_server['host'] = '127.0.0.1';
		$redis_server['port'] = 9999;
		$redis_server['auth'] = 'foobar';
		$cache = new WP_Object_Cache;
		$this->assertEquals( 'WP Redis: Redis server went away', $cache->last_triggered_error );
		$this->assertFalse( $cache->is_redis_connected );
		// Fails back to the internal object cache
		$cache->set( 'foo', 'bar' );
		$this->assertEquals( 'bar', $cache->get( 'foo' ) );
	}

	public function test_miss() {
		$this->assertEquals( null, $this->cache->get( rand_str() ) );
		$this->assertEquals( 0, $this->cache->cache_hits );
		$this->assertEquals( 1, $this->cache->cache_misses );
	}

	public function test_add_get() {
		$key = rand_str();
		$val = rand_str();

		$this->cache->add( $key, $val );
		$this->assertEquals( $val, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
	}

	public function test_add_get_0() {
		$key = rand_str();
		$val = 0;

		// you can store zero in the cache
		$this->cache->add( $key, $val );
		$this->assertEquals( $val, $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
	}

	public function test_add_get_null() {
		$key = rand_str();
		$val = null;

		$this->assertTrue( $this->cache->add( $key, $val ) );
		// null is converted to empty string
		$this->assertEquals( '', $this->cache->get( $key ) );
		$this->assertEquals( 1, $this->cache->cache_hits );
		$this->assertEquals( 0, $this->cache->cache_misses );
	}

	public function test_add() {
		$key = rand_str();
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
	}

	public function test_replace() {
		$key = rand_str();
		$val = rand_str();
		$val2 = rand_str();

		// memcached rejects replace() if the key does not exist
		$this->assertFalse( $this->cache->replace( $key, $val ) );
		$this->assertFalse( $this->cache->get( $key ) );
		$this->assertTrue( $this->cache->add( $key, $val ) );
		$this->assertEquals( $val, $this->cache->get( $key ) );
		$this->assertTrue( $this->cache->replace( $key, $val2 ) );
		$this->assertEquals( $val2, $this->cache->get( $key ) );
	}

	public function test_set() {
		$key = rand_str();
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
	}

	public function test_flush() {
		global $_wp_using_ext_object_cache;

		if ( $_wp_using_ext_object_cache ) {
			return;
		}

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
	}

	// Make sure objects are cloned going to and from the cache
	public function test_object_refs() {
		$key = rand_str();
		$object_a = new stdClass;
		$object_a->foo = 'alpha';
		$this->cache->set( $key, $object_a );
		$object_a->foo = 'bravo';
		$object_b = $this->cache->get( $key );
		$this->assertEquals( 'alpha', $object_b->foo );
		$object_b->foo = 'charlie';
		$this->assertEquals( 'bravo', $object_a->foo );

		$key = rand_str();
		$object_a = new stdClass;
		$object_a->foo = 'alpha';
		$this->cache->add( $key, $object_a );
		$object_a->foo = 'bravo';
		$object_b = $this->cache->get( $key );
		$this->assertEquals( 'alpha', $object_b->foo );
		$object_b->foo = 'charlie';
		$this->assertEquals( 'bravo', $object_a->foo );
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
	}

	public function test_incr_non_persistent_never_below_zero() {
		$key = rand_str();
		$this->cache->add_non_persistent_groups( array( 'nonpersistent' ) );
		$this->cache->set( $key, 1, 'nonpersistent' );
		$this->assertEquals( 1, $this->cache->get( $key, 'nonpersistent' ) );
		$this->cache->incr( $key, -2, 'nonpersistent' );
		$this->assertEquals( 0, $this->cache->get( $key, 'nonpersistent' ) );
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
	}

	public function test_decr_non_persistent_never_below_zero() {
		$key = rand_str();
		$this->cache->add_non_persistent_groups( array( 'nonpersistent' ) );
		$this->cache->set( $key, 1, 'nonpersistent' );
		$this->assertEquals( 1, $this->cache->get( $key, 'nonpersistent' ) );
		$this->cache->decr( $key, 2, 'nonpersistent' );
		$this->assertEquals( 0, $this->cache->get( $key, 'nonpersistent' ) );
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
		if ( ! defined( 'WP_REDIS_USE_CACHE_GROUPS' ) || ! WP_REDIS_USE_CACHE_GROUPS ) {
			$this->markTestSkipped( 'Cache groups not enabled.' );
		}
		$key1 = rand_str();
		$val1 = rand_str();
		$key2 = rand_str();
		$val2 = rand_str();
		$key3 = rand_str();
		$val3 = rand_str();
		$group = 'foo';
		$group2 = 'bar';

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
		if ( ! defined( 'WP_REDIS_USE_CACHE_GROUPS' ) || ! WP_REDIS_USE_CACHE_GROUPS ) {
			$this->markTestSkipped( 'Cache groups not enabled.' );
		}
		$key1 = rand_str();
		$val1 = rand_str();
		$key2 = rand_str();
		$val2 = rand_str();
		$key3 = rand_str();
		$val3 = rand_str();
		$group = 'foo';
		$group2 = 'bar';
		$this->cache->add_non_persistent_groups( array( $group, $group2 ) );

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
		if ( ! defined( 'WP_REDIS_USE_CACHE_GROUPS' ) || ! WP_REDIS_USE_CACHE_GROUPS ) {
			$this->markTestSkipped( 'Cache groups not enabled.' );
		}
		$key1 = rand_str();
		$val1 = rand_str();
		$key2 = rand_str();
		$val2 = rand_str();
		$key3 = rand_str();
		$val3 = rand_str();
		$group = 'foo';
		$group2 = 'bar';

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

		$key = rand_str();
		$val = rand_str();
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
