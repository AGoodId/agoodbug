<?php
/**
 * AGoodMember Integration
 *
 * @package AGoodBug
 */

namespace AGoodBug\Integrations;

use AGoodBug\Plugin;

class AGoodMember {

	/**
	 * AGoodMember API URL (hardcoded)
	 */
	const API_URL = 'https://www.agoodsport.se';

	/**
	 * Send feedback to AGoodMember as a task
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @param int    $post_id        Post ID.
	 * @return string|false Task number or false on failure.
	 */
	public function send( $data, $screenshot_url, $post_id ) {
		$settings = Plugin::get_settings();

		$api_url    = self::API_URL;
		$api_key    = $settings['agoodmember_token'] ?? '';
		$project_id = $settings['agoodmember_project_id'] ?? '';

		if ( empty( $api_key ) ) {
			error_log( 'AGoodBug - AGoodMember: Missing API key' );
			return false;
		}

		// Determine feedback type for title prefix
		$feedback_type = $data['feedback_type'] ?? 'screenshot';
		$title_prefix  = $feedback_type === 'general' ? 'Feedback' : 'Buggrapport';

		// Build task data for external API
		$task_data = [
			'title'       => sprintf(
				'%s: %s',
				$title_prefix,
				wp_parse_url( $data['url'], PHP_URL_PATH ) ?: '/'
			),
			'description' => $this->build_description( $data, $screenshot_url ),
			'type'        => 'bug',
			'status'      => 'ska göras',
			'priority'    => 'medel',
			'due_date'    => wp_date( 'Y-m-d' ),
			'tags'        => [ 'agoodbug', 'frontend' ],
			'source'      => 'agoodbug',
			'source_url'  => $data['url'] ?? '',
		];

		// Add project if configured
		if ( ! empty( $project_id ) ) {
			$task_data['project_id'] = $project_id;
		}

		error_log( 'AGoodBug - AGoodMember: Creating task via external API' );

		// Use the external tasks API with API key authentication
		$response = wp_remote_post( $api_url . '/api/external/tasks', [
			'headers' => [
				'X-API-Key'    => $api_key,
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $task_data ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'AGoodBug - AGoodMember error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		error_log( 'AGoodBug - AGoodMember response code: ' . $code );

		if ( $code === 201 && ! empty( $body['task']['task_number'] ) ) {
			error_log( 'AGoodBug - AGoodMember: Task created #' . $body['task']['task_number'] );
			return '#' . $body['task']['task_number'];
		}

		if ( ! empty( $body['error'] ) ) {
			error_log( 'AGoodBug - AGoodMember API error: ' . $body['error'] );
		}

		return false;
	}

	/**
	 * Build task description in Markdown
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @return string
	 */
	private function build_description( $data, $screenshot_url ) {
		$parts = [];

		// Description
		$parts[] = '## Beskrivning';
		$parts[] = $data['comment'];
		$parts[] = '';

		// Environment details
		$parts[] = '## Miljö';
		$parts[] = '| Egenskap | Värde |';
		$parts[] = '|----------|-------|';
		$parts[] = '| **URL** | ' . $data['url'] . ' |';
		$parts[] = '| **Enhet** | ' . ucfirst( $data['device_type'] ?? 'unknown' ) . ( ! empty( $data['touch_enabled'] ) ? ' (touch)' : '' ) . ' |';
		$parts[] = '| **Skärm** | ' . ( $data['screen_resolution'] ?? 'N/A' ) . ( ! empty( $data['pixel_ratio'] ) && $data['pixel_ratio'] > 1 ? ' @' . $data['pixel_ratio'] . 'x' : '' ) . ' |';
		$parts[] = '| **Viewport** | ' . ( $data['viewport'] ?? 'N/A' ) . ' |';
		$parts[] = '| **Browser** | ' . ( $data['browser'] ?? 'N/A' ) . ' |';

		if ( ! empty( $data['color_scheme'] ) ) {
			$parts[] = '| **Tema** | ' . ucfirst( $data['color_scheme'] ) . ' |';
		}
		if ( ! empty( $data['language'] ) ) {
			$parts[] = '| **Språk** | ' . $data['language'] . ' |';
		}
		if ( ! empty( $data['timezone'] ) ) {
			$parts[] = '| **Tidszon** | ' . $data['timezone'] . ' |';
		}

		$parts[] = '';

		// Reporter info
		$user = wp_get_current_user();
		if ( $user->ID > 0 ) {
			$parts[] = '**Rapporterat av:** ' . $user->display_name . ' (' . $user->user_email . ')';
		} elseif ( ! empty( $data['email'] ) ) {
			$parts[] = '**Rapporterat av:** ' . $data['email'];
		}
		$parts[] = '';

		// Screenshot
		if ( $screenshot_url ) {
			$parts[] = '## Skärmbild';
			$parts[] = '![Screenshot](' . $screenshot_url . ')';
		}

		$parts[] = '';
		$parts[] = '---';
		$parts[] = '*Skickat via AGoodBug*';

		return implode( "\n", $parts );
	}
}
