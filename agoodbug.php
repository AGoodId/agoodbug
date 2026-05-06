<?php
/**
 * Plugin Name: AGoodBug
 * Plugin URI: https://github.com/AGoodId/agoodbug
 * Description: Visual feedback and bug reporting widget with screenshot capture.
 * Version: 1.8.28
 * Author: AGoodId
 * Author URI: https://agoodid.se
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: agoodbug
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Network: true
 */

namespace AGoodBug;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'AGOODBUG_VERSION', '1.8.28' );
define( 'AGOODBUG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGOODBUG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AGOODBUG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Auto-update from GitHub releases
require AGOODBUG_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$agoodbugUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/AGoodId/agoodbug/',
	__FILE__,
	'agoodbug'
);
$agoodbugUpdateChecker->getVcsApi()->enableReleaseAssets();

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
 * Default settings for a single site
 */
function get_default_settings() {
	return [
		'enabled'                => true,
		'show_in_admin'          => true,
		'button_style'           => 'button',
		'tab_label'              => 'Tyck till',
		'roles'                  => [ 'administrator', 'editor' ],
		'destinations'           => [ 'cpt', 'email' ],
		'email_recipients'       => get_option( 'admin_email' ),
		'checkvist_enabled'      => false,
		'checkvist_username'     => '',
		'checkvist_api_key'      => '',
		'checkvist_list_id'      => '',
		'agoodmember_enabled'    => false,
		'agoodmember_token'      => '',
		'agoodmember_project_id' => '',
		'rate_limit'             => 10,
		'max_screenshot_size'    => 5 * 1024 * 1024,
	];
}

/**
 * Initialize a single site with default settings
 */
function activate_for_site() {
	if ( ! get_option( 'agoodbug_settings' ) ) {
		add_option( 'agoodbug_settings', get_default_settings() );
	}
	flush_rewrite_rules();
}

/**
 * Activation hook — handles both single-site and network-wide activation
 */
function activate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		foreach ( get_sites( [ 'number' => 0 ] ) as $site ) {
			switch_to_blog( $site->blog_id );
			activate_for_site();
			restore_current_blog();
		}
	} else {
		activate_for_site();
	}
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Initialize settings when a new site is added to the network
 */
add_action( 'wp_initialize_site', function ( $new_site ) {
	if ( is_plugin_active_for_network( AGOODBUG_PLUGIN_BASENAME ) ) {
		switch_to_blog( $new_site->blog_id );
		activate_for_site();
		restore_current_blog();
	}
} );

/**
 * Deactivation hook
 */
function deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
