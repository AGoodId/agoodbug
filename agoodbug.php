<?php
/**
 * Plugin Name: AGoodBug
 * Plugin URI: https://github.com/AGoodId/agoodbug
 * Description: Visual feedback and bug reporting widget with screenshot capture.
 * Version: 1.0.2
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
define( 'AGOODBUG_VERSION', '1.0.2' );
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

	// Convert to file path
	$class_name = strtolower( $class_name );
	$class_name = str_replace( '_', '-', $class_name );
	$class_name = str_replace( '\\', '/', $class_name );

	// Check different locations
	$paths = [
		AGOODBUG_PLUGIN_DIR . 'includes/class-' . $class_name . '.php',
		AGOODBUG_PLUGIN_DIR . 'includes/' . $class_name . '.php',
		AGOODBUG_PLUGIN_DIR . 'admin/class-' . $class_name . '.php',
		AGOODBUG_PLUGIN_DIR . 'public/class-' . $class_name . '.php',
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
		'email_recipients'   => get_option( 'admin_email' ),
		'agoodapp_enabled'   => false,
		'agoodapp_url'       => '',
		'agoodapp_token'     => '',
		'agoodapp_org_id'    => '',
		'checkvist_enabled'  => false,
		'checkvist_api_key'  => '',
		'checkvist_list_id'  => '',
		'github_enabled'     => false,
		'github_token'       => '',
		'github_repo'        => '',
		'rate_limit'         => 10,
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
