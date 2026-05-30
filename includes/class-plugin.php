<?php
/**
 * Main Plugin Class
 *
 * @package AGoodBug
 */

namespace AGoodBug;

class Plugin {

	/**
	 * Plugin settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Initialize the plugin
	 */
	public function init() {
		$this->settings = self::get_settings();

		// Initialize components
		$this->init_cpt();
		$this->init_rest_api();
		$this->init_admin();
		$this->init_network_admin();
		$this->init_frontend();
	}

	/**
	 * Initialize Custom Post Type
	 */
	private function init_cpt() {
		$cpt = new Feedback_CPT();
		$cpt->init();
	}

	/**
	 * Initialize REST API
	 */
	private function init_rest_api() {
		$rest_api = new REST_API();
		$rest_api->init();
	}

	/**
	 * Initialize admin
	 */
	private function init_admin() {
		if ( is_admin() && ! is_network_admin() ) {
			$settings = new Settings();
			$settings->init();

			$admin_page = new Admin_Page();
			$admin_page->init();
		}
	}

	/**
	 * Initialize network admin (multisite only)
	 */
	private function init_network_admin() {
		if ( is_multisite() && ( is_network_admin() || $this->is_network_settings_ajax() ) ) {
			$network_settings = new Network_Settings();
			$network_settings->init();
		}
	}

	/**
	 * Check whether the current AJAX request targets the network settings page.
	 *
	 * @return bool
	 */
	private function is_network_settings_ajax() {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return strpos( $action, 'agoodbug_network_' ) === 0;
	}

	/**
	 * Initialize frontend widget
	 */
	private function init_frontend() {
		if ( ! empty( $this->settings['enabled'] ) ) {
			$frontend = new Frontend();
			$frontend->init();
		}
	}

	/**
	 * Get plugin settings — merges network defaults with per-site overrides
	 *
	 * @return array
	 */
	public static function get_settings() {
		$site_settings = get_option( 'agoodbug_settings', [] );

		if ( is_multisite() ) {
			$network_settings = Network_Settings::get_settings();
			$site_defaults    = function_exists( __NAMESPACE__ . '\\get_default_settings' )
				? get_default_settings()
				: [];
			$site_overrides   = [];

			foreach ( $site_settings as $key => $value ) {
				$default = $site_defaults[ $key ] ?? null;
				if ( ! array_key_exists( $key, $site_defaults ) || $value !== $default ) {
					$site_overrides[ $key ] = $value;
				}
			}

			return array_merge( $network_settings, $site_overrides );
		}

		return $site_settings;
	}

	/**
	 * Check if current user can use the feedback widget
	 *
	 * @return bool
	 */
	public static function user_can_report() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$settings = self::get_settings();
		$allowed_roles = $settings['roles'] ?? [ 'administrator', 'editor' ];

		$user = wp_get_current_user();
		$user_roles = $user->roles;

		return ! empty( array_intersect( $allowed_roles, $user_roles ) );
	}
}
