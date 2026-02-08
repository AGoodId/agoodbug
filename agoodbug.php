<?php
/**
 * Plugin Name: AGoodBug
 * Plugin URI: https://github.com/AGoodId/agoodbug
 * Description: Visual feedback and bug reporting widget with screenshot capture.
 * Version: 1.2.1
 * Author: AGoodId
 * Author URI: https://agoodid.se
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: agoodbug
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

namespace AGoodBug;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'AGOODBUG_VERSION', '1.2.1' );
define( 'AGOODBUG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGOODBUG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AGOODBUG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes
 */
spl_autoload_register( function ( $class ) {
	// Only autoload our namespace
	if ( strpos( $class, 'AGoodBug\\' ) !== 0 ) {
		return;
	}

	// Remove namespace prefix
	$class_name = str_replace( 'AGoodBug\\', '', $class );

	// Split into parts (e.g., Integrations\Email -> ['Integrations', 'Email'])
	$parts = explode( '\\', $class_name );

	// Get the actual class name (last part)
	$file_name = array_pop( $parts );
	$file_name = strtolower( $file_name );
	$file_name = str_replace( '_', '-', $file_name );

	// Get the subdirectory path (remaining parts)
	$subdir = '';
	if ( ! empty( $parts ) ) {
		$subdir = strtolower( implode( '/', $parts ) ) . '/';
	}

	// Check different locations
	$paths = [
		AGOODBUG_PLUGIN_DIR . 'includes/' . $subdir . 'class-' . $file_name . '.php',
		AGOODBUG_PLUGIN_DIR . 'includes/' . $subdir . $file_name . '.php',
		AGOODBUG_PLUGIN_DIR . 'admin/' . $subdir . 'class-' . $file_name . '.php',
		AGOODBUG_PLUGIN_DIR . 'public/' . $subdir . 'class-' . $file_name . '.php',
	];

	foreach ( $paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
} );

/**
 * Initialize the plugin
 */
function init() {
	// Load text domain
	load_plugin_textdomain( 'agoodbug', false, dirname( AGOODBUG_PLUGIN_BASENAME ) . '/languages' );

	// Initialize main plugin class
	$plugin = new Plugin();
	$plugin->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Activation hook
 */
function activate() {
	// Create default options
	$defaults = [
		'enabled'            => true,
		'roles'              => [ 'administrator', 'editor' ],
		'destinations'       => [ 'cpt', 'email' ],
		'email_recipients'       => get_option( 'admin_email' ),
		'checkvist_enabled'      => false,
		'checkvist_username'     => '',
		'checkvist_api_key'      => '',
		'checkvist_list_id'      => '',
		'agoodmember_enabled'    => false,
		'agoodmember_token'      => '',
		'agoodmember_project_id' => '',
		'rate_limit'             => 10,
		'max_screenshot_size' => 5 * 1024 * 1024, // 5MB
	];

	if ( ! get_option( 'agoodbug_settings' ) ) {
		add_option( 'agoodbug_settings', $defaults );
	}

	// Flush rewrite rules for CPT
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivation hook
 */
function deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
