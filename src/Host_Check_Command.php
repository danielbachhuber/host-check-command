<?php

use WP_CLI\Utils;

/**
 * Checks hosting status for WordPress installation.
 */
class Host_Check_Command {

	/**
	 * Checks hosting status for WordPress installation.
	 *
	 * Loads the WordPress installation to verify that it's still hosted on this server.
	 *
	 * First, it verifies the WordPress installation loads. Next, it makes a HTTP
	 * request to determine: 1) whether the installation is still on the server,
	 * and 2) whether the installation loads as expected.
	 *
	 * Potential statuses include:
	 *
	 * * no-wp-exists - WordPress doesn't exist at the path.
	 * * no-wp-config - No wp-config.php file was found for the installation.
	 * * error-db-connect - Couldn't connect to the database using defined credentials.
	 * * error-db-select - Connected to the database but couldn't select specific database.
	 * * missing-<http-code> - WordPress installation isn't on the server.
	 * * hosted-maintenance - WordPress installation is hosted but renders maintenance page.
	 * * hosted-php-fatal - WordPress installation is hosted but has a PHP fatal.
	 * * hosted-broken-wp-login - WordPress installation is hosted but the login page is broken.
	 * * hosted-valid-login - WordPress installation is hosted on server and login page loads.
	 *
	 * Disables WP cron to prevent 'wp_version_check' from being run.
	 *
	 * ## OPTIONS
	 *
	 * --path=<path>
	 * : Path to the WordPress installation.
	 *
	 * ## EXAMPLES
	 *
	 *     # Site loads successfully and is hosted on the server.
	 *     $ wp host-check --path=wordpress
	 *     [2018-08-16 13:41:48] Loading: wordpress
	 *     [2018-08-16 13:41:48] WordPress version: 4.9.8
	 *     [2018-08-16 13:41:48] Next scheduled wp_version_check: 2018-08-13 23:31:31
	 *     [2018-08-16 13:41:48] Yes: WordPress install is hosted here (HTTP code 200)
	 *     [2018-08-16 13:41:49] Yes: wp-login loads as expected (HTTP code 200)
	 *     [2018-08-16 13:41:49] Summary: wordpress, hosted-valid-login, 4.9.8
	 *     [2018-08-16 13:41:49] Details: {"wp_version_check":"2018-08-13 23:31:31","active_plugins":["debug-bar\/debug-bar.php"],"active_theme":"wordpress-theme","user_count":3,"post_count":89,"last_post_date":"2018-08-06 13:22:39"}
	 *
	 *     # Error connecting to the database when loading site.
	 *     $ wp host-check --path=wordpress
	 *     [2018-08-16 13:40:03] Loading: wordpress
	 *     [2018-08-16 13:40:03] WordPress version: 4.7.6
	 *     [2018-08-16 13:40:03] Summary: wordpress, error-db-connect, 4.7.6
	 *     [2018-08-16 13:40:03] Details: {"wp_version_check":null,"active_plugins":null,"active_theme":null,"user_count":null,"post_count":null,"last_post_date":null
	 *
	 * @when before_wp_load
	 */
	public function __invoke() {
		global $wpdb;

		$path = WP_CLI::get_config( 'path' );
		self::log( 'Loading: ' . $path );

		// See how far we can get with loading WordPress
		$status     = false;
		$wp_version = '';
		$wp_details = array(
			'wp_version_check' => null,
			'active_plugins'   => null,
			'active_theme'     => null,
			'user_count'       => null,
			'post_count'       => null,
			'last_post_date'   => null,
		);
		if ( ! self::wp_exists() ) {
			$status = 'no-wp-exists';
		}
		if ( false === $status ) {
			$wp_version = self::get_wp_version();
			self::log( 'WordPress version: ' . $wp_version );
			$wp_config_path = Utils\locate_wp_config();
			if ( ! $wp_config_path ) {
				$status = 'no-wp-config';
			}
		}

		if ( false === $status ) {
			self::load_wordpress_lite();
			if ( ! empty( $wpdb->error ) ) {
				$status = 'db_select_fail' === $wpdb->error->get_error_code() ? 'error-db-select' : 'error-db-connect';
			}
		}

		if ( false === $status ) {
			// Check to see when the next version check would've been
			$wp_details['wp_version_check'] = self::get_next_check();
			$status                         = self::get_host_status();
			if ( 'hosted' === $status ) {
				$status .= '-' . self::get_login_status();
			}
			$row                          = $wpdb->get_row( "SELECT option_value FROM {$wpdb->options} WHERE option_name='active_plugins'" );
			$wp_details['active_plugins'] = unserialize( $row->option_value );
			if ( is_array( $wp_details['active_plugins'] ) ) {
				$wp_details['active_plugins'] = array_unique( $wp_details['active_plugins'] );
			}
			$row                          = $wpdb->get_row( "SELECT option_value FROM {$wpdb->options} WHERE option_name='stylesheet'" );
			$wp_details['active_theme']   = $row->option_value;
			$wp_details['user_count']     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
			$wp_details['post_count']     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'" );
			$row                          = $wpdb->get_row( "SELECT post_date_gmt FROM {$wpdb->posts} WHERE post_status='publish' ORDER BY post_date DESC LIMIT 0,1" );
			$wp_details['last_post_date'] = $row->post_date_gmt;
		}
		self::log( "Summary: {$path}, {$status}, {$wp_version}" );
		$wp_details = json_encode( $wp_details );
		self::log( "Details: {$wp_details}" );
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
		$wp_config_path    = Utils\locate_wp_config();
		$wp_config_code    = explode( "\n", file_get_contents( $wp_config_path ) );
		$found_wp_settings = false;
		$lines_to_run      = array();
		foreach ( $wp_config_code as $line ) {
			if ( preg_match( '/^\s*require.+wp-settings\.php/', $line ) ) {
				$found_wp_settings = true;
				continue;
			}
			$lines_to_run[] = $line;
		}
		if ( ! $found_wp_settings ) {
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
		eval( self::get_wp_config_code() ); // phpcs:ignore
		foreach ( get_defined_vars() as $key => $var ) {
			if ( array_key_exists( $key, $wp_cli_original_defined_vars ) || 'wp_cli_original_defined_vars' === $key ) {
				continue;
			}
			global $$key; // phpcs:ignore
			$$key = $var;
		}

		self::maybe_update_url_from_domain_constant();

		define( 'COMMAND_ABSPATH', dirname( __DIR__ ) . '/wp/' );
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
		$cron       = get_option( 'cron', array() );
		$next_check = '';
		if ( is_array( $cron ) ) {
			foreach ( $cron as $timestamp => $task ) {
				if ( ! is_array( $task ) ) {
					continue;
				}
				if ( isset( $task['wp_version_check'] ) ) {
					$next_check = date( 'Y-m-d H:i:s', $timestamp );
					self::log( 'Next scheduled wp_version_check: ' . $next_check );
					break;
				}
			}
		}
		return $next_check;
	}

	private static function get_host_status() {
		// Generate a test file to fetch and check
		$uuid           = md5( mt_rand( 0, 100000 ) );
		$upload_dir     = wp_upload_dir( null, false );
		$test_file      = $uuid . '.txt';
		$test_file_path = $upload_dir['basedir'] . '/' . $test_file;
		$ret            = file_put_contents( $test_file_path, $uuid );
		if ( ! $ret ) {
			WP_CLI::error( "Couldn't write test file to path: {$test_file_path}" );
		}
		// Fetch and check the test file
		$response    = self::http_request( 'GET', $upload_dir['baseurl'] . '/' . $test_file );
		$status_code = ! empty( $response->status_code ) ? $response->status_code : 'NA';
		if ( ! empty( $response ) && $uuid === $response->body ) {
			$status = 'hosted';
			self::log( "Yes: WordPress install is hosted here (HTTP code {$status_code})" );
		} else {
			$status = 'missing-' . $status_code;
			self::log( "Missing: WordPress install isn't hosted here (HTTP code {$status_code})" );
		}
		// Don't need the test file anymore
		$ret = unlink( $test_file_path );
		if ( ! $ret ) {
			WP_CLI::error( "Couldn't delete test file: {$test_file_path}" );
		}
		return $status;
	}

	private static function get_login_status() {
		$response    = self::http_request( 'GET', wp_login_url() );
		$status_code = ! empty( $response->status_code ) ? $response->status_code : 'NA';
		if ( false !== strpos( $response->body, 'name="log"' ) ) {
			$status = 'valid-login';
			self::log( "Yes: wp-login loads as expected (HTTP code {$status_code})" );
		} elseif ( false !== stripos( $response->body, 'Briefly unavailable for scheduled maintenance. Check back in a minute.' ) ) {
			$status = 'maintenance';
			self::log( "No: WordPress is in maintenance mode (HTTP code {$status_code})" );
		} elseif ( false !== stripos( $response->body, 'Fatal error' ) ) {
			$status = 'php-fatal';
			self::log( "No: WordPress has a PHP fatal error (HTTP code {$status_code})" );
		} else {
			$status = 'broken-login';
			self::log( "No: wp-login is missing name=\"log\" (HTTP code {$status_code})" );
		}
		return $status;
	}

	private static function http_request( $method, $url, $data = null, $headers = array(), $options = array() ) {
		$cert_path = '/rmccue/requests/library/Requests/Transport/cacert.pem';
		if ( Utils\inside_phar() ) {
			// cURL can't read Phar archives
			$options['verify'] = Utils\extract_from_phar(
				WP_CLI_ROOT . '/vendor' . $cert_path
			);
		} else {
			foreach ( Utils\get_vendor_paths() as $vendor_path ) {
				if ( file_exists( $vendor_path . $cert_path ) ) {
					$options['verify'] = $vendor_path . $cert_path;
					break;
				}
			}
			if ( empty( $options['verify'] ) ) {
				\WP_CLI::warning( 'Cannot find SSL certificate.' );
				return false;
			}
		}

		try {
			$request = \Requests::request( $url, $headers, $data, $method, $options );
			return $request;
		} catch ( \Requests_Exception $ex ) {
			// Handle SSL certificate issues gracefully
			\WP_CLI::warning( $ex->getMessage() );
			$options['verify'] = false;
			try {
				return \Requests::request( $url, $headers, $data, $method, $options );
			} catch ( \Requests_Exception $ex ) {
				\WP_CLI::warning( $ex->getMessage() );
				return false;
			}
		}
	}

	/**
	 * Log informational message to STDOUT with current timestamp.
	 */
	private static function log( $message ) {
		$timestamp = date( 'Y-m-d H:i:s' );
		WP_CLI::log( sprintf( '[%s] %s', $timestamp, $message ) );
	}

}
