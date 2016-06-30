<?php

add_action( 'wp_footer', function(){
	if ( ! empty( $_GET['redis_debug'] ) ) {
		$GLOBALS['wp_object_cache']->stats();
	}
});
