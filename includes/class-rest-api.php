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
					'sanitize_callback' => 'sanitize_text_field',
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
				'selection' => [
					'type'     => 'object',
					'required' => false,
					'default'  => [],
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
		// Check rate limit
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$data = [
			'screenshot' => $request->get_param( 'screenshot' ),
			'url'        => $request->get_param( 'url' ),
			'comment'    => $request->get_param( 'comment' ),
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

		// Send to email
		if ( in_array( 'email', $destinations, true ) ) {
			$email = new Integrations\Email();
			$results['email'] = $email->send( $data, $screenshot_url, $post_id );
		}

		// Send to AGoodApp
		if ( in_array( 'agoodapp', $destinations, true ) && ! empty( $settings['agoodapp_enabled'] ) ) {
			$agoodapp = new Integrations\AGoodApp();
			$results['agoodapp'] = $agoodapp->send( $data, $screenshot_url, $post_id );
		}

		// Send to Checkvist
		if ( in_array( 'checkvist', $destinations, true ) && ! empty( $settings['checkvist_enabled'] ) ) {
			$checkvist = new Integrations\Checkvist();
			$results['checkvist'] = $checkvist->send( $data, $screenshot_url, $post_id );
		}

		// Send to GitHub
		if ( in_array( 'github', $destinations, true ) && ! empty( $settings['github_enabled'] ) ) {
			$github = new Integrations\GitHub();
			$results['github'] = $github->send( $data, $screenshot_url, $post_id );
		}

		// Save destination results
		update_post_meta( $post_id, '_destination_results', wp_json_encode( $results ) );

		return rest_ensure_response( [
			'success'      => true,
			'feedback_id'  => $post_id,
			'destinations' => $results,
		] );
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
