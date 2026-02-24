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
		$this->settings = get_option( 'agoodbug_settings', [] );

		// Initialize components
		$this->init_cpt();
		$this->init_rest_api();
		$this->init_admin();
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
		if ( is_admin() ) {
			$settings = new Settings();
			$settings->init();

			$admin_page = new Admin_Page();
			$admin_page->init();
		}
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
	 * Get plugin settings
	 *
	 * @return array
	 */
	public static function get_settings() {
		return get_option( 'agoodbug_settings', [] );
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
