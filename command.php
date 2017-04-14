<?php

/**
 * Check that the WordPress install is still hosted at its internal domain.
 *
 * Run with `wp --require=host-check.php host-check --path=<path-to-wp>`
 *
 * Disables WP cron to prevent 'wp_version_check' from being run.
 *
 * ## OPTIONS
 *
 * --path=<path>
 * : Path to the WordPress install
 *
 * @when before_wp_load
 */
WP_CLI::add_command( 'host-check', function(){

	$start = microtime( true );
	$path = WP_CLI::get_config( 'path' );
	WP_CLI::log( 'Loading: ' . $path );
	// Disable WP Cron so the update check doesn't run
	define( 'ALTERNATE_WP_CRON', true );
	WP_CLI::get_runner()->load_wordpress();
	$time = microtime( true ) - $start;
	$load_time = round( $time, 3 ) . 's';
	WP_CLI::log( 'WordPress load time: ' . $load_time );

	// Check to see when the next version check would've been
	$cron = get_option( 'cron', array() );
	$next_check = '';
	if ( is_array( $cron ) ) {
		foreach( $cron as $timestamp => $task ) {
			if ( ! is_array( $task ) ) {
				continue;
			}
			if ( isset( $task['wp_version_check'] ) ) {
				$next_check = date( 'Y-m-d H:i:s', $timestamp );
				WP_CLI::log( 'Next scheduled wp_version_check: ' . $next_check );
				break;
			}
		}
	}
	WP_CLI::log( 'WordPress version: ' . $GLOBALS['wp_version'] );

	// Generate a test file to fetch and check
	$uuid = md5( mt_rand( 0, 100000 ) );
	$upload_dir = wp_upload_dir();
	$test_file = $uuid . '.txt';
	$test_file_path = $upload_dir['basedir'] . '/' . $test_file;
	$ret = file_put_contents( $test_file_path, $uuid );
	if ( ! $ret ) {
		WP_CLI::error( "Couldn't write test file to path: {$test_file_path}" );
	}

	// Fetch and check the test file
	$response = wp_remote_get( $upload_dir['baseurl'] . '/' . $test_file );
	if ( is_wp_error( $response ) ) {
		WP_CLI::error( $response );
	}
	$response_code = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );
	if ( $uuid === $response_body ) {
		$status = 'yes';
		WP_CLI::log( "Yes: WordPress install is hosted here (HTTP code {$response_code})" );
	} else {
		$status = 'no';
		WP_CLI::log( "No: WordPress install isn't hosted here (HTTP code {$response_code})" );
	}

	// Don't need the test file anymore
	$ret = unlink( $test_file_path );
	if ( ! $ret ) {
		WP_CLI::error( "Couldn't delete test file: {$test_file_path}" );
	}
	WP_CLI::log( "Summary: {$path}, {$load_time}, {$GLOBALS['wp_version']}, {$next_check}, {$status}" );
});
