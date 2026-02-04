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
	 * Send feedback to AGoodMember as a task
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @param int    $post_id        Post ID.
	 * @return string|false Task number or false on failure.
	 */
	public function send( $data, $screenshot_url, $post_id ) {
		$settings = Plugin::get_settings();

		$api_url       = rtrim( $settings['agoodmember_url'] ?? '', '/' );
		$token         = $settings['agoodmember_token'] ?? '';
		$project_id    = $settings['agoodmember_project_id'] ?? '';
		$assignee_email = $settings['agoodmember_assignee_email'] ?? '';

		if ( empty( $api_url ) || empty( $token ) ) {
			error_log( 'AGoodBug - AGoodMember: Missing API URL or token' );
			return false;
		}

		$user = wp_get_current_user();

		// Look up assignee by email if configured
		$assignee_ids = [];
		if ( ! empty( $assignee_email ) ) {
			$person_id = $this->lookup_person_by_email( $api_url, $token, $assignee_email );
			if ( $person_id ) {
				$assignee_ids[] = $person_id;
			}
		}

		// Determine feedback type for title prefix
		$feedback_type = $data['feedback_type'] ?? 'screenshot';
		$title_prefix  = $feedback_type === 'general' ? 'Feedback' : 'Bug Report';

		// Build task data
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
			'due_date'    => wp_date( 'Y-m-d' ), // Today
			'tags'        => [ 'agoodbug', 'frontend' ],
		];

		// Add project if configured
		if ( ! empty( $project_id ) ) {
			$task_data['project_id'] = $project_id;
		}

		// Add assignees if found
		if ( ! empty( $assignee_ids ) ) {
			$task_data['assignee_ids'] = $assignee_ids;
		}

		error_log( 'AGoodBug - AGoodMember: Creating task with data: ' . wp_json_encode( $task_data ) );

		$response = wp_remote_post( $api_url . '/api/tasks', [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
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
		error_log( 'AGoodBug - AGoodMember response: ' . wp_json_encode( $body ) );

		if ( $code >= 200 && $code < 300 && ! empty( $body['task']['task_number'] ) ) {
			return '#' . $body['task']['task_number'];
		}

		if ( ! empty( $body['error'] ) ) {
			error_log( 'AGoodBug - AGoodMember API error: ' . $body['error'] );
		}

		return false;
	}

	/**
	 * Look up a person ID by email address
	 *
	 * @param string $api_url Base API URL.
	 * @param string $token   Auth token.
	 * @param string $email   Email to search for.
	 * @return string|null Person ID or null if not found.
	 */
	private function lookup_person_by_email( $api_url, $token, $email ) {
		$response = wp_remote_get(
			add_query_arg( 'query', $email, $api_url . '/api/tasks/assignees/search' ),
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'AGoodBug - AGoodMember person lookup error: ' . $response->get_error_message() );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['assignees'] ) && is_array( $body['assignees'] ) ) {
			foreach ( $body['assignees'] as $person ) {
				if ( isset( $person['email'] ) && strtolower( $person['email'] ) === strtolower( $email ) ) {
					error_log( 'AGoodBug - AGoodMember: Found person ' . $person['id'] . ' for email ' . $email );
					return $person['id'];
				}
			}
		}

		error_log( 'AGoodBug - AGoodMember: No person found for email ' . $email );
		return null;
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
