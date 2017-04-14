<?php
/**
 * Used to set up and fix common variables and include
 * the WordPress procedural and class library.
 *
 * Allows for some configuration in wp-config.php (see default-constants.php)
 *
 * @package WordPress
 */

/**
 * Stores the location of the WordPress directory of functions, classes, and core content.
 *
 * @since 1.0.0
 */
define( 'WPINC', 'wp-includes' );

// Include files required for initialization.
require( COMMAND_ABSPATH . WPINC . '/load.php' );
require( COMMAND_ABSPATH . WPINC . '/default-constants.php' );
require_once( COMMAND_ABSPATH . WPINC . '/plugin.php' );

/*
 * These can't be directly globalized in version.php. When updating,
 * we're including version.php from another install and don't want
 * these values to be overridden if already set.
 */
global $wp_version, $wp_db_version, $tinymce_version, $required_php_version, $required_mysql_version, $wp_local_package;
require( ABSPATH . WPINC . '/version.php' );

/**
 * If not already configured, `$blog_id` will default to 1 in a single site
 * configuration. In multisite, it will be overridden by default in ms-settings.php.
 *
 * @global int $blog_id
 * @since 2.0.0
 */
global $blog_id;

// Set initial default constants including WP_MEMORY_LIMIT, WP_MAX_MEMORY_LIMIT, WP_DEBUG, SCRIPT_DEBUG, WP_CONTENT_DIR and WP_CACHE.
wp_initial_constants();

// Check for the required PHP version and for the MySQL extension or a database drop-in.
wp_check_php_mysql_versions();

// Disable magic quotes at runtime. Magic quotes are added using wpdb later in wp-settings.php.
@ini_set( 'magic_quotes_runtime', 0 );
@ini_set( 'magic_quotes_sybase',  0 );

// WordPress calculates offsets from UTC.
date_default_timezone_set( 'UTC' );

// Turn register_globals off.
wp_unregister_GLOBALS();

// Standardize $_SERVER variables across setups.
wp_fix_server_vars();

// Define WP_LANG_DIR if not set.
wp_set_lang_dir();

// Load early WordPress files.
require( COMMAND_ABSPATH . WPINC . '/compat.php' );
require( COMMAND_ABSPATH . WPINC . '/class-wp-list-util.php' );
require( COMMAND_ABSPATH . WPINC . '/functions.php' );
require( COMMAND_ABSPATH . WPINC . '/class-wp-matchesmapregex.php' );
require( COMMAND_ABSPATH . WPINC . '/class-wp.php' );
require( COMMAND_ABSPATH . WPINC . '/class-wp-error.php' );
require( COMMAND_ABSPATH . WPINC . '/class-phpass.php' );
require( COMMAND_ABSPATH . WPINC . '/formatting.php' );

// Include the wpdb class and, if present, a db.php database drop-in.
global $wpdb;
require_wp_db();
