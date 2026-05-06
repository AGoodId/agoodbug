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
	 * Submission dedupe transient prefix.
	 */
	const SUBMISSION_PREFIX = 'agoodbug_submission_';

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
		register_rest_route( self::NAMESPACE, '/proxy', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'proxy_image' ],
			'permission_callback' => '__return_true', // Public: only proxies images, validates content-type
			'args'                => [
				'url' => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'esc_url_raw',
				],
			],
		] );

		register_rest_route( self::NAMESPACE, '/feedback', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'submit_feedback' ],
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'feedback_type' => [
					'type'              => 'string',
					'required'          => false,
					'default'           => 'screenshot',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'screenshot' => [
					'type'              => [ 'string', 'null' ],
					'required'          => false, // Now optional for general feedback
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
					'type'     => [ 'string', 'null' ],
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
				// Extended device info
				'device_type' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'screen_resolution' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'pixel_ratio' => [
					'type'              => 'number',
					'required'          => false,
				],
				'color_depth' => [
					'type'              => 'integer',
					'required'          => false,
				],
				'touch_enabled' => [
					'type'              => 'boolean',
					'required'          => false,
				],
				'color_scheme' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'language' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'timezone' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'referrer' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'esc_url_raw',
				],
				'cookies_enabled' => [
					'type'              => 'boolean',
					'required'          => false,
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
	 * Proxy external images for html2canvas CORS support
	 *
	 * html2canvas sends: GET {proxy}?url={encodedUrl}&responseType=text
	 * and expects a raw base64-encoded string response.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function proxy_image( $request ) {
		$url = $request->get_param( 'url' );

		if ( empty( $url ) ) {
			return new \WP_Error( 'missing_url', 'URL is required.', [ 'status' => 400 ] );
		}

		// Only allow image URLs
		$allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' ];

		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => [
				'Accept' => 'image/*',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'proxy_error', 'Failed to fetch image.', [ 'status' => 502 ] );
		}

		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$mime_type    = trim( strtok( $content_type, ';' ) );

		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return new \WP_Error( 'invalid_type', 'URL is not an image.', [ 'status' => 400 ] );
		}

		$body   = wp_remote_retrieve_body( $response );
		$base64 = base64_encode( $body );

		// html2canvas expects raw base64 text response
		$wp_response = new \WP_REST_Response();
		$wp_response->set_status( 200 );
		$wp_response->set_headers( [
			'Content-Type'                => 'text/plain',
			'Cache-Control'              => 'public, max-age=86400',
			'Access-Control-Allow-Origin' => '*',
		] );

		add_filter( 'rest_pre_serve_request', function ( $served ) use ( $base64 ) {
			header( 'Content-Type: text/plain' );
			header( 'Cache-Control: public, max-age=86400' );
			header( 'Access-Control-Allow-Origin: *' );
			echo $base64; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return true;
		}, 10, 1 );

		return $wp_response;
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
		$settings = Plugin::get_settings();
		$limit    = (int) ( $settings['rate_limit'] ?? 10 );

		// 0 = unlimited
		if ( $limit === 0 ) {
			return true;
		}

		$user_id       = get_current_user_id();
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
		$lock_key    = '';
		$lock_option = '';

		try {
			$data = [
				'feedback_type'     => $request->get_param( 'feedback_type' ) ?: 'screenshot',
				'screenshot'        => $request->get_param( 'screenshot' ),
				'url'               => $request->get_param( 'url' ),
				'comment'           => $request->get_param( 'comment' ),
				'email'             => $request->get_param( 'email' ),
				'selection'         => $request->get_param( 'selection' ),
				'viewport'          => $request->get_param( 'viewport' ),
				'browser'           => $request->get_param( 'browser' ),
				// Extended device info
				'device_type'       => $request->get_param( 'device_type' ),
				'screen_resolution' => $request->get_param( 'screen_resolution' ),
				'pixel_ratio'       => $request->get_param( 'pixel_ratio' ),
				'color_depth'       => $request->get_param( 'color_depth' ),
				'touch_enabled'     => $request->get_param( 'touch_enabled' ),
				'color_scheme'      => $request->get_param( 'color_scheme' ),
				'language'          => $request->get_param( 'language' ),
				'timezone'          => $request->get_param( 'timezone' ),
				'referrer'          => $request->get_param( 'referrer' ),
				'cookies_enabled'   => $request->get_param( 'cookies_enabled' ),
			];

			$fingerprint = $this->build_submission_fingerprint( $data );
			$lock_key    = self::SUBMISSION_PREFIX . $fingerprint;
			$lock_option = '_agoodbug_submission_lock_' . $fingerprint;
			$existing    = get_transient( $lock_key );

			if ( is_array( $existing ) ) {
				return rest_ensure_response( [
					'success'      => true,
					'duplicate'    => true,
					'feedback_id'  => absint( $existing['post_id'] ?? 0 ),
					'destinations' => $existing['destinations'] ?? [],
				] );
			}

			if ( $existing === 'processing' ) {
				return rest_ensure_response( [
					'success'   => true,
					'duplicate' => true,
				] );
			}

			if ( ! add_option( $lock_option, time(), '', 'no' ) ) {
				$locked_at = (int) get_option( $lock_option );
				if ( $locked_at > 0 && $locked_at < time() - ( 10 * MINUTE_IN_SECONDS ) ) {
					delete_option( $lock_option );
					if ( ! add_option( $lock_option, time(), '', 'no' ) ) {
						return rest_ensure_response( [
							'success'   => true,
							'duplicate' => true,
						] );
					}
				} else {
					return rest_ensure_response( [
						'success'   => true,
						'duplicate' => true,
					] );
				}
			}

			// Check rate limit only for new submissions, not duplicate retries.
			$rate_check = $this->check_rate_limit();
			if ( is_wp_error( $rate_check ) ) {
				delete_option( $lock_option );
				return $rate_check;
			}

			set_transient( $lock_key, 'processing', 2 * MINUTE_IN_SECONDS );
			$data['submission_fingerprint'] = $fingerprint;

			// Validate screenshot data only if provided
			if ( ! empty( $data['screenshot'] ) && strpos( $data['screenshot'], 'data:image/' ) !== 0 ) {
				delete_transient( $lock_key );
				delete_option( $lock_option );
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
				delete_transient( $lock_key );
				delete_option( $lock_option );
				return $post_id;
			}

			$results['cpt'] = true;

			// Get screenshot URL for integrations
			$screenshot_url = Feedback_CPT::get_screenshot_url( $post_id );

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

			// Send to Slack (wrapped in try-catch)
			if ( in_array( 'slack', $destinations, true ) && ! empty( $settings['slack_enabled'] ) ) {
				try {
					$slack = new Integrations\Slack();
					$results['slack'] = $slack->send( $data, $screenshot_url, $post_id );
				} catch ( \Exception $e ) {
					$results['slack'] = false;
					error_log( 'AGoodBug - Slack error: ' . $e->getMessage() );
				}
			}

			// Send to AGoodMember (wrapped in try-catch)
			if ( in_array( 'agoodmember', $destinations, true ) && ! empty( $settings['agoodmember_enabled'] ) ) {
				try {
					$agoodmember = new Integrations\AGoodMember();
					$results['agoodmember'] = $agoodmember->send( $data, $screenshot_url, $post_id );
				} catch ( \Exception $e ) {
					$results['agoodmember'] = false;
					error_log( 'AGoodBug - AGoodMember error: ' . $e->getMessage() );
				}
			}

			// Save destination results
			update_post_meta( $post_id, '_destination_results', wp_json_encode( $results ) );
			set_transient( $lock_key, [
				'post_id'      => $post_id,
				'destinations' => $results,
			], 10 * MINUTE_IN_SECONDS );
			delete_option( $lock_option );

			return rest_ensure_response( [
				'success'      => true,
				'feedback_id'  => $post_id,
				'destinations' => $results,
			] );
		} catch ( \Exception $e ) {
			if ( ! empty( $lock_key ) ) {
				delete_transient( $lock_key );
			}
			if ( ! empty( $lock_option ) ) {
				delete_option( $lock_option );
			}
			error_log( 'AGoodBug - Submit feedback error: ' . $e->getMessage() );
			return new \WP_Error(
				'submit_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		} catch ( \Error $e ) {
			if ( ! empty( $lock_key ) ) {
				delete_transient( $lock_key );
			}
			if ( ! empty( $lock_option ) ) {
				delete_option( $lock_option );
			}
			error_log( 'AGoodBug - Submit feedback fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			return new \WP_Error(
				'fatal_error',
				'A fatal error occurred: ' . $e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Build a stable fingerprint for one user submission.
	 *
	 * @param array $data Feedback data.
	 * @return string
	 */
	private function build_submission_fingerprint( $data ) {
		$user_id = get_current_user_id();

		$normalized = [
			'user_id'       => $user_id,
			'email'         => sanitize_email( $data['email'] ?? '' ),
			'feedback_type' => sanitize_text_field( $data['feedback_type'] ?? 'screenshot' ),
			'url'           => esc_url_raw( $data['url'] ?? '' ),
			'comment'       => trim( sanitize_textarea_field( $data['comment'] ?? '' ) ),
			'selection'     => sanitize_text_field( $data['selection'] ?? '' ),
			'viewport'      => sanitize_text_field( $data['viewport'] ?? '' ),
			'browser'       => sanitize_text_field( $data['browser'] ?? '' ),
			'screenshot'    => ! empty( $data['screenshot'] ) ? hash( 'sha256', $data['screenshot'] ) : '',
		];

		return hash( 'sha256', wp_json_encode( $normalized ) );
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
