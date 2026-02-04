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
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_widget' ] );
	}

	/**
	 * Check if widget should be shown
	 *
	 * @return bool
	 */
	private function should_show_widget() {
		$settings = Plugin::get_settings();

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
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'isLoggedIn'     => is_user_logged_in(),
			'userEmail'      => $user_email,
			'showEmailField' => ! is_user_logged_in() || ! empty( $settings['allow_anonymous'] ),
			'strings'       => [
				'buttonTitle'           => __( 'Report a bug', 'agoodbug' ),
				'choiceTitle'           => __( 'How would you like to report?', 'agoodbug' ),
				'generalFeedback'       => __( 'General feedback', 'agoodbug' ),
				'generalFeedbackDesc'   => __( 'Write a comment without screenshot', 'agoodbug' ),
				'screenshotFeedback'    => __( 'Take a screenshot', 'agoodbug' ),
				'screenshotFeedbackDesc' => __( 'Mark an area on the page', 'agoodbug' ),
				'overlayHint'           => __( 'Click and drag to select an area', 'agoodbug' ),
				'modalTitle'            => __( 'Report a Bug', 'agoodbug' ),
				'emailLabel'            => __( 'Your email', 'agoodbug' ),
				'emailPlaceholder'      => __( 'your@email.com', 'agoodbug' ),
				'commentLabel'          => __( 'Describe the problem', 'agoodbug' ),
				'commentPlaceholder'    => __( 'What went wrong? What did you expect to happen?', 'agoodbug' ),
				'submitButton'          => __( 'Send Report', 'agoodbug' ),
				'cancelButton'          => __( 'Cancel', 'agoodbug' ),
				'sending'               => __( 'Sending...', 'agoodbug' ),
				'success'               => __( 'Thank you! Your feedback has been received.', 'agoodbug' ),
				'error'                 => __( 'Something went wrong. Please try again.', 'agoodbug' ),
				'retryButton'           => __( 'Try Again', 'agoodbug' ),
				'closeButton'           => __( 'Close', 'agoodbug' ),
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
