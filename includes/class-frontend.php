<?php
/**
 * Frontend Widget Handler
 *
 * @package AGoodBug
 */

namespace AGoodBug;

class Frontend {

	/**
	 * Initialize
	 */
	public function init() {
		// Frontend
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_widget' ] );

		// Admin pages
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_footer', [ $this, 'render_widget' ] );
	}

	/**
	 * Check if widget should be shown
	 *
	 * @return bool
	 */
	private function should_show_widget() {
		$settings = Plugin::get_settings();

		// Don't show in admin unless enabled (default true if setting hasn't been saved yet)
		$show_in_admin = $settings['show_in_admin'] ?? true;
		if ( is_admin() && ! $show_in_admin ) {
			return false;
		}

		// Check if anonymous users are allowed
		if ( ! empty( $settings['allow_anonymous'] ) ) {
			return true;
		}

		// Otherwise check logged-in user permissions
		return Plugin::user_can_report();
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_assets() {
		if ( ! $this->should_show_widget() ) {
			return;
		}

		// html2canvas library
		wp_enqueue_script(
			'html2canvas',
			AGOODBUG_PLUGIN_URL . 'vendor/html2canvas.min.js',
			[],
			'1.4.1',
			true
		);

		// Main script
		wp_enqueue_script(
			'agoodbug',
			AGOODBUG_PLUGIN_URL . 'public/js/agoodbug.js',
			[ 'html2canvas' ],
			AGOODBUG_VERSION,
			true
		);

		$settings = Plugin::get_settings();

		// Get user email for pre-fill
		$user_email = '';
		if ( is_user_logged_in() ) {
			$user       = wp_get_current_user();
			$user_email = $user->user_email;
		}

		// Localize script
		wp_localize_script( 'agoodbug', 'agoodbugConfig', [
			'apiUrl'         => rest_url( 'agoodbug/v1/feedback' ),
			'proxyUrl'       => rest_url( 'agoodbug/v1/proxy' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'isLoggedIn'     => is_user_logged_in(),
			'userEmail'      => $user_email,
			'showEmailField' => ! is_user_logged_in() || ! empty( $settings['allow_anonymous'] ),
			'strings'       => [
				'buttonTitle'            => __( 'Rapportera', 'agoodbug' ),
				'choiceTitle'            => __( 'Hur vill du rapportera?', 'agoodbug' ),
				'generalFeedback'        => __( 'Generell feedback', 'agoodbug' ),
				'generalFeedbackDesc'    => __( 'Skriv en kommentar utan skärmbild', 'agoodbug' ),
				'screenshotFeedback'     => __( 'Ta en skärmbild', 'agoodbug' ),
				'screenshotFeedbackDesc' => __( 'Markera ett område på sidan', 'agoodbug' ),
				'overlayHint'            => __( 'Klicka och dra för att markera', 'agoodbug' ),
				'modalTitle'             => __( 'Rapportera ett problem', 'agoodbug' ),
				'emailLabel'             => __( 'Din e-post', 'agoodbug' ),
				'emailPlaceholder'       => __( 'din@epost.se', 'agoodbug' ),
				'commentLabel'           => __( 'Beskriv problemet', 'agoodbug' ),
				'commentPlaceholder'     => __( 'Vad gick fel? Vad förväntade du dig?', 'agoodbug' ),
				'submitButton'           => __( 'Skicka', 'agoodbug' ),
				'cancelButton'           => __( 'Avbryt', 'agoodbug' ),
				'sending'                => __( 'Skickar...', 'agoodbug' ),
				'success'                => __( 'Tack! Din feedback har tagits emot.', 'agoodbug' ),
				'error'                  => __( 'Något gick fel. Försök igen.', 'agoodbug' ),
				'retryButton'            => __( 'Försök igen', 'agoodbug' ),
				'closeButton'            => __( 'Stäng', 'agoodbug' ),
			],
		] );

		// Styles
		wp_enqueue_style(
			'agoodbug',
			AGOODBUG_PLUGIN_URL . 'public/css/agoodbug.css',
			[],
			AGOODBUG_VERSION
		);
	}

	/**
	 * Render the widget container
	 */
	public function render_widget() {
		if ( ! $this->should_show_widget() ) {
			return;
		}

		echo '<div id="agoodbug-widget"></div>';
	}
}
