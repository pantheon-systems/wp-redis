<?php

/**
 * Test the persistent object cache using core's cache tests
 */
class AdapterTest extends WP_UnitTestCase {
	protected $connection_details = array(
		'host' => 'localhost',
		'port' => 6379,
		'timeout' => 1000,
		'retry_interval' => 100,
	);

	public function setUp() {
		parent::setUp();
		$this->adapter = new WP_Redis_Adapter_PHPRedis;
	}

	public function test_dependencies() {
		$result = $this->adapter->check_dependencies();
		if ( class_exists( 'Redis' ) ) {
			$this->assertTrue( $result );
		} else {
			$this->assertFalse( $result );
		}
	}

	public function test_redis_client_connection() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}

		$redis = $this->adapter->redis_connection( $this->connection_details );
		$this->assertTrue( $redis->isConnected() );
	}

	public function test_setup_connection() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}

		$redis = $this->adapter->redis_connection( $this->connection_details );
		$isSetUp = $this->adapter->setup_connection( $redis, [], [] );
		$this->assertTrue( $isSetUp );
	}

	public function test_setup_connection_throws_exception() {
		if ( ! class_exists( 'Redis' ) ) {
			$this->markTestSkipped( 'PHPRedis extension not available.' );
		}

		$redis = $this->getMockBuilder( Redis::class )->getMock();
		$redis->method( 'select' )
			->will( $this->throwException( new RedisException ) );

		$redis->connect(
			$this->connection_details['host'],
			$this->connection_details['port'],
			$this->connection_details['timeout'],
			null,
			$this->connection_details['retry_interval']
		);
		$settings = array(
			'database' => 2,
		);
		$keys_methods = array(
			'database' => 'select',
		);
		$this->setExpectedException( WP_Redis_Connection_Exception::class );
		$this->adapter->setup_connection( $redis, $settings, $keys_methods );
	}
}
