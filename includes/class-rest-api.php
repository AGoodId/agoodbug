<?php
/**
 * REST API Handler
 *
 * @package AGoodBug
 */

namespace AGoodBug;

class REST_API {

	/**
	 * API namespace
	 */
	const NAMESPACE = 'agoodbug/v1';

	/**
	 * Rate limit transient prefix
	 */
	const RATE_LIMIT_PREFIX = 'agoodbug_rate_';

	/**
	 * Initialize
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes
	 */
	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/feedback', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'submit_feedback' ],
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'screenshot' => [
					'type'              => 'string',
					'required'          => true,
				],
				'url' => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'esc_url_raw',
				],
				'comment' => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_textarea_field',
				],
				'email' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_email',
				],
				'selection' => [
					'type'     => 'string',
					'required' => false,
				],
				'viewport' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'browser' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		register_rest_route( self::NAMESPACE, '/settings', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_settings' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );
	}

	/**
	 * Check if user has permission
	 *
	 * @return bool|\WP_Error
	 */
	public function check_permissions() {
		// Check if anonymous users are allowed
		$settings = Plugin::get_settings();
		if ( ! empty( $settings['allow_anonymous'] ) ) {
			return true;
		}

		if ( ! Plugin::user_can_report() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to submit feedback.', 'agoodbug' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Check rate limit
	 *
	 * @return bool|\WP_Error
	 */
	private function check_rate_limit() {
		$user_id  = get_current_user_id();
		$settings = Plugin::get_settings();
		$limit    = $settings['rate_limit'] ?? 10;

		$transient_key = self::RATE_LIMIT_PREFIX . $user_id;
		$count         = get_transient( $transient_key );

		if ( $count === false ) {
			set_transient( $transient_key, 1, HOUR_IN_SECONDS );
			return true;
		}

		if ( $count >= $limit ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: rate limit number */
					__( 'Rate limit exceeded. Maximum %d reports per hour.', 'agoodbug' ),
					$limit
				),
				[ 'status' => 429 ]
			);
		}

		set_transient( $transient_key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Submit feedback
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_feedback( $request ) {
		try {
			// Check rate limit
			$rate_check = $this->check_rate_limit();
			if ( is_wp_error( $rate_check ) ) {
				return $rate_check;
			}

			$data = [
				'screenshot' => $request->get_param( 'screenshot' ),
				'url'        => $request->get_param( 'url' ),
				'comment'    => $request->get_param( 'comment' ),
				'email'      => $request->get_param( 'email' ),
				'selection'  => $request->get_param( 'selection' ),
				'viewport'   => $request->get_param( 'viewport' ),
				'browser'    => $request->get_param( 'browser' ),
			];

			// Validate screenshot data
			if ( strpos( $data['screenshot'], 'data:image/' ) !== 0 ) {
				return new \WP_Error(
					'invalid_screenshot',
					__( 'Invalid screenshot format.', 'agoodbug' ),
					[ 'status' => 400 ]
				);
			}

			$settings     = Plugin::get_settings();
			$destinations = $settings['destinations'] ?? [ 'cpt' ];
			$results      = [];

			// Always save to CPT first
			$post_id = Feedback_CPT::create_feedback( $data );

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			$results['cpt'] = true;

			// Get screenshot URL for integrations
			$screenshot_id  = get_post_meta( $post_id, '_screenshot_id', true );
			$screenshot_url = $screenshot_id ? wp_get_attachment_url( $screenshot_id ) : '';

			// Send to email (wrapped in try-catch)
			if ( in_array( 'email', $destinations, true ) ) {
				try {
					$email = new Integrations\Email();
					$results['email'] = $email->send( $data, $screenshot_url, $post_id );
				} catch ( \Exception $e ) {
					$results['email'] = false;
					error_log( 'AGoodBug - Email error: ' . $e->getMessage() );
				}
			}

			// Send to AGoodApp (wrapped in try-catch)
			if ( in_array( 'agoodapp', $destinations, true ) && ! empty( $settings['agoodapp_enabled'] ) ) {
				try {
					$agoodapp = new Integrations\AGoodApp();
					$results['agoodapp'] = $agoodapp->send( $data, $screenshot_url, $post_id );
				} catch ( \Exception $e ) {
					$results['agoodapp'] = false;
					error_log( 'AGoodBug - AGoodApp error: ' . $e->getMessage() );
				}
			}

			// Send to Checkvist (wrapped in try-catch)
			if ( in_array( 'checkvist', $destinations, true ) && ! empty( $settings['checkvist_enabled'] ) ) {
				try {
					$checkvist = new Integrations\Checkvist();
					$results['checkvist'] = $checkvist->send( $data, $screenshot_url, $post_id );
				} catch ( \Exception $e ) {
					$results['checkvist'] = false;
					error_log( 'AGoodBug - Checkvist error: ' . $e->getMessage() );
				}
			}

			// Send to GitHub (wrapped in try-catch)
			if ( in_array( 'github', $destinations, true ) && ! empty( $settings['github_enabled'] ) ) {
				try {
					$github = new Integrations\GitHub();
					$results['github'] = $github->send( $data, $screenshot_url, $post_id );
				} catch ( \Exception $e ) {
					$results['github'] = false;
					error_log( 'AGoodBug - GitHub error: ' . $e->getMessage() );
				}
			}

			// Save destination results
			update_post_meta( $post_id, '_destination_results', wp_json_encode( $results ) );

			return rest_ensure_response( [
				'success'      => true,
				'feedback_id'  => $post_id,
				'destinations' => $results,
			] );
		} catch ( \Exception $e ) {
			error_log( 'AGoodBug - Submit feedback error: ' . $e->getMessage() );
			return new \WP_Error(
				'submit_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		} catch ( \Error $e ) {
			error_log( 'AGoodBug - Submit feedback fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			return new \WP_Error(
				'fatal_error',
				'A fatal error occurred: ' . $e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Get settings for frontend
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		$settings = Plugin::get_settings();

		// Only return safe settings for frontend
		return rest_ensure_response( [
			'enabled' => ! empty( $settings['enabled'] ),
		] );
	}
}
