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
	 * Enqueue frontend assets
	 */
	public function enqueue_assets() {
		if ( ! Plugin::user_can_report() ) {
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

		// Localize script
		wp_localize_script( 'agoodbug', 'agoodbugConfig', [
			'apiUrl'   => rest_url( 'agoodbug/v1/feedback' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'strings'  => [
				'buttonTitle'      => __( 'Report a bug', 'agoodbug' ),
				'overlayHint'      => __( 'Click and drag to select an area', 'agoodbug' ),
				'modalTitle'       => __( 'Report a Bug', 'agoodbug' ),
				'commentLabel'     => __( 'Describe the problem', 'agoodbug' ),
				'commentPlaceholder' => __( 'What went wrong? What did you expect to happen?', 'agoodbug' ),
				'submitButton'     => __( 'Send Report', 'agoodbug' ),
				'cancelButton'     => __( 'Cancel', 'agoodbug' ),
				'sending'          => __( 'Sending...', 'agoodbug' ),
				'success'          => __( 'Thank you! Your feedback has been received.', 'agoodbug' ),
				'error'            => __( 'Something went wrong. Please try again.', 'agoodbug' ),
				'retryButton'      => __( 'Try Again', 'agoodbug' ),
				'closeButton'      => __( 'Close', 'agoodbug' ),
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
		if ( ! Plugin::user_can_report() ) {
			return;
		}

		echo '<div id="agoodbug-widget"></div>';
	}
}
