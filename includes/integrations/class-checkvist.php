<?php
/**
 * Checkvist Integration
 *
 * @package AGoodBug
 */

namespace AGoodBug\Integrations;

use AGoodBug\Plugin;

class Checkvist {

	/**
	 * API base URL
	 */
	const API_URL = 'https://checkvist.com/checklists/';

	/**
	 * Send feedback to Checkvist
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @param int    $post_id        Post ID.
	 * @return string|false Task ID or false on failure.
	 */
	public function send( $data, $screenshot_url, $post_id ) {
		$settings = Plugin::get_settings();

		$api_key = $settings['checkvist_api_key'] ?? '';
		$list_id = $settings['checkvist_list_id'] ?? '';

		if ( empty( $api_key ) || empty( $list_id ) ) {
			return false;
		}

		$user = wp_get_current_user();

		// Build task content with notes
		$content = sprintf(
			/* translators: %s: page path */
			__( 'Bug Report: %s', 'agoodbug' ),
			wp_parse_url( $data['url'], PHP_URL_PATH ) ?: '/'
		);

		$notes = $this->build_notes( $data, $screenshot_url, $user );

		// Create task
		$response = wp_remote_post( self::API_URL . $list_id . '/tasks.json', [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( [
				'api_key' => $api_key,
				'task'    => [
					'content' => $content,
					'notes'   => $notes,
				],
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'AGoodBug - Checkvist error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && ! empty( $body['id'] ) ) {
			return (string) $body['id'];
		}

		error_log( 'AGoodBug - Checkvist error: ' . wp_json_encode( $body ) );
		return false;
	}

	/**
	 * Build task notes
	 *
	 * @param array    $data           Feedback data.
	 * @param string   $screenshot_url Screenshot URL.
	 * @param \WP_User $user           User object.
	 * @return string
	 */
	private function build_notes( $data, $screenshot_url, $user ) {
		$lines = [];

		$lines[] = '**' . __( 'Description', 'agoodbug' ) . ':**';
		$lines[] = $data['comment'];
		$lines[] = '';
		$lines[] = '**' . __( 'Details', 'agoodbug' ) . ':**';
		$lines[] = '- URL: ' . $data['url'];
		$lines[] = '- Viewport: ' . ( $data['viewport'] ?? 'N/A' );
		$lines[] = '- Browser: ' . ( $data['browser'] ?? 'N/A' );
		$lines[] = '- Reporter: ' . $user->display_name . ' (' . $user->user_email . ')';

		if ( $screenshot_url ) {
			$lines[] = '';
			$lines[] = '**' . __( 'Screenshot', 'agoodbug' ) . ':**';
			$lines[] = $screenshot_url;
		}

		return implode( "\n", $lines );
	}
}
