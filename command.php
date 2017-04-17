<?php

use WP_CLI\Utils;

/**
 * Check that the WordPress install is still hosted at its internal domain.
 */
class Host_Check_Command {

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
	public function __invoke() {
		global $wpdb;

		$path = WP_CLI::get_config( 'path' );
		WP_CLI::log( 'Loading: ' . $path );

		// See how far we can get with loading WordPress
		$status = false;
		$wp_version = $next_check = '';
		if ( ! self::wp_exists() ) {
			$status = 'no-wp-exists';
		}
		if ( false === $status ) {
			$wp_version = self::get_wp_version();
			WP_CLI::log( 'WordPress version: ' . $wp_version );
			$wp_config_path = Utils\locate_wp_config();
			if ( ! $wp_config_path ) {
				$status = 'no-wp-config';
			}
		}

		if ( false === $status ) {
			self::load_wordpress_lite();
			if ( ! empty( $wpdb->error ) ) {
				$status = 'error-db-connect';
			} else {
				// Remaining bootstrap steps
				wp_set_wpdb_vars();
				wp_start_object_cache();
				wp_plugin_directory_constants();
			}
		}

		if ( false === $status ) {
			// Check to see when the next version check would've been
			$next_check = self::get_next_check();
			$status = self::get_host_status();
			if ( 'yes' === $status ) {
				$status .= '-' . self::get_login_status();
			}
		}
		WP_CLI::log( "Summary: {$path}, {$status}, {$wp_version}, {$next_check}" );
	}

	private static function wp_exists() {
		return is_readable( ABSPATH . 'wp-includes/version.php' );
	}

	private static function get_wp_version() {
		global $wp_version;
		include ABSPATH . 'wp-includes/version.php';
		return $wp_version;
	}

	private static function get_wp_config_code() {
		$wp_config_path = Utils\locate_wp_config();
		$wp_config_code = explode( "\n", file_get_contents( $wp_config_path ) );
		$found_wp_settings = false;
		$lines_to_run = array();
		foreach ( $wp_config_code as $line ) {
			if ( preg_match( '/^\s*require.+wp-settings\.php/', $line ) ) {
				$found_wp_settings = true;
				continue;
			}
			$lines_to_run[] = $line;
		}
		if ( !$found_wp_settings ) {
			WP_CLI::error( 'Strange wp-config.php file: wp-settings.php is not loaded directly.' );
		}
		$source = implode( "\n", $lines_to_run );
		$source = Utils\replace_path_consts( $source, $wp_config_path );
		return preg_replace( '|^\s*\<\?php\s*|', '', $source );
	}

	private static function load_wordpress_lite() {
		// Globals not explicitly globalized in WordPress
		global $site_id, $wpdb, $public, $current_site, $current_blog, $path, $shortcode_tags;

		// Load wp-config.php code, in the global scope
		$wp_cli_original_defined_vars = get_defined_vars();
		eval( self::get_wp_config_code() );
		foreach( get_defined_vars() as $key => $var ) {
			if ( array_key_exists( $key, $wp_cli_original_defined_vars ) || 'wp_cli_original_defined_vars' === $key ) {
				continue;
			}
			global $$key;
			$$key = $var;
		}

		self::maybe_update_url_from_domain_constant();

		define( 'COMMAND_ABSPATH', dirname( __FILE__ ) . '/wp/' );
		require COMMAND_ABSPATH . '/wp-settings.php';
	}

	private static function maybe_update_url_from_domain_constant() {
		if ( defined( 'DOMAIN_CURRENT_SITE' ) ) {
			$url = DOMAIN_CURRENT_SITE;
			if ( defined( 'PATH_CURRENT_SITE' ) ) {
				$url .= PATH_CURRENT_SITE;
			}
			\WP_CLI::set_url( $url );
		}
	}

	private static function get_next_check() {
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
		return $next_check;
	}

	private static function get_host_status() {
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
		$response = self::http_request( 'GET', $upload_dir['baseurl'] . '/' . $test_file );
		$status_code = ! empty( $response->status_code ) ? $response->status_code : 'NA';
		if ( ! empty( $response ) && $uuid === $response->body ) {
			$status = 'yes';
			WP_CLI::log( "Yes: WordPress install is hosted here (HTTP code {$status_code})" );
		} else {
			$status = 'no';
			WP_CLI::log( "No: WordPress install isn't hosted here (HTTP code {$status_code})" );
		}
		// Don't need the test file anymore
		$ret = unlink( $test_file_path );
		if ( ! $ret ) {
			WP_CLI::error( "Couldn't delete test file: {$test_file_path}" );
		}
		return $status;
	}

	private static function get_login_status() {
		$response = self::http_request( 'GET', wp_login_url() );
		$status_code = ! empty( $response->status_code ) ? $response->status_code : 'NA';
		if ( false !== strpos( $response->body, 'name="log"' ) ) {
			$status = 'valid-login';
			WP_CLI::log( "Yes: wp-login loads as expected (HTTP code {$status_code})" );
		} else {
			$status = 'broken-login';
			WP_CLI::log( "No: wp-login is missing name=\"log\" (HTTP code {$status_code})" );
		}
		return $status;
	}

	private static function http_request( $method, $url, $data = null, $headers = array(), $options = array() ) {
		$cert_path = '/rmccue/requests/library/Requests/Transport/cacert.pem';
		if ( Utils\inside_phar() ) {
			// cURL can't read Phar archives
			$options['verify'] = Utils\extract_from_phar(
			WP_CLI_ROOT . '/vendor' . $cert_path );
		} else {
			foreach( Utils\get_vendor_paths() as $vendor_path ) {
				if ( file_exists( $vendor_path . $cert_path ) ) {
					$options['verify'] = $vendor_path . $cert_path;
					break;
				}
			}
			if ( empty( $options['verify'] ) ){
				\WP_CLI::warning( "Cannot find SSL certificate." );
				return false;
			}
		}

		try {
			$request = \Requests::request( $url, $headers, $data, $method, $options );
			return $request;
		} catch( \Requests_Exception $ex ) {
			// Handle SSL certificate issues gracefully
			\WP_CLI::warning( $ex->getMessage() );
			$options['verify'] = false;
			try {
				return \Requests::request( $url, $headers, $data, $method, $options );
			} catch( \Requests_Exception $ex ) {
				\WP_CLI::warning( $ex->getMessage() );
				return false;
			}
		}
}

}

WP_CLI::add_command( 'host-check', 'Host_Check_Command' );
