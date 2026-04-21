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
	 * @param string $screenshot_url Screenshot URL (WP attachment URL).
	 * @param int    $post_id        Post ID.
	 * @return string|false Task number or false on failure.
	 */
	public function send( $data, $screenshot_url, $post_id ) {
		$settings = Plugin::get_settings();

		$api_url    = self::API_URL;
		$api_key    = $settings['agoodmember_token'] ?? '';
		$project_id = $settings['agoodmember_project_id'] ?? '';

		if ( empty( $api_key ) ) {
			$this->log_error( $post_id, 'Missing API key' );
			return false;
		}

		// Build title from comment
		$feedback_type = $data['feedback_type'] ?? 'screenshot';
		$title_prefix  = $feedback_type === 'general' ? 'Feedback' : 'Bug report';
		$comment       = trim( $data['comment'] ?? '' );

		if ( ! empty( $comment ) ) {
			$title = sprintf( '%s: %s', $title_prefix, wp_trim_words( $comment, 12, '…' ) );
		} else {
			$title = sprintf( '%s: %s', $title_prefix, wp_parse_url( $data['url'], PHP_URL_PATH ) ?: '/' );
		}

		// Build notes HTML
		$notes_html = $this->build_notes_html( $data, $screenshot_url );

		// Create task with notes in a single POST
		$task_data = [
			'title'       => $title,
			'description' => sprintf( 'Källa: %s', $data['url'] ?? '' ),
			'notes'       => $notes_html,
			'type'        => 'bug',
			'status'      => 'ska göras',
			'priority'    => 'medel',
			'due_date'    => wp_date( 'Y-m-d' ),
			'tags'        => [ 'agoodbug', 'frontend' ],
			'source'      => 'agoodbug',
			'source_url'  => $data['url'] ?? '',
		];

		if ( ! empty( $project_id ) ) {
			$task_data['project_id'] = $project_id;
		}

		$response = wp_remote_post( $api_url . '/api/external/tasks', [
			'headers' => [
				'X-API-Key'    => $api_key,
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $task_data ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( $post_id, 'Request failed: ' . $response->get_error_message() );
			return false;
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$body     = json_decode( $raw_body, true );

		if ( $code !== 201 || empty( $body['task']['task_number'] ) ) {
			$error_msg = 'HTTP ' . $code;
			if ( ! empty( $body['error'] ) ) {
				$error_msg .= ': ' . $body['error'];
			} elseif ( ! empty( $body['message'] ) ) {
				$error_msg .= ': ' . $body['message'];
			} else {
				$error_msg .= ': ' . substr( $raw_body, 0, 300 );
			}
			$this->log_error( $post_id, $error_msg );
			return false;
		}

		$task_number = $body['task']['task_number'];
		error_log( 'AGoodBug - AGoodMember: Task created #' . $task_number . ' with notes' );

		return '#' . $task_number;
	}

	/**
	 * Build HTML notes for Tiptap rich text editor
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @return string HTML string.
	 */
	private function build_notes_html( $data, $screenshot_url ) {
		$parts = [];

		$parts[] = '<p>🐛 <strong>Bug report från AGoodBug</strong></p>';
		$parts[] = '<p><strong>Sida:</strong> <a href="' . esc_url( $data['url'] ) . '">' . esc_html( $data['url'] ) . '</a></p>';
		$parts[] = '<p><strong>Datum:</strong> ' . esc_html( current_time( 'Y-m-d H:i' ) ) . '</p>';
		$parts[] = '<p><strong>Kommentar:</strong> ' . esc_html( $data['comment'] ?? '' ) . '</p>';

		// Screenshot
		if ( $screenshot_url ) {
			$parts[] = '<h2>Skärmbild</h2>';
			$parts[] = '<img src="' . esc_url( $screenshot_url ) . '" />';
		}

		// Environment
		$parts[] = '<h2>Miljö</h2>';
		$parts[] = '<ul>';
		$parts[] = '<li>Webbläsare: ' . esc_html( $data['browser'] ?? 'N/A' ) . '</li>';
		$parts[] = '<li>Enhet: ' . esc_html( ucfirst( $data['device_type'] ?? 'unknown' ) ) . '</li>';
		$parts[] = '<li>Skärm: ' . esc_html( $data['screen_resolution'] ?? 'N/A' ) . '</li>';
		$parts[] = '<li>Viewport: ' . esc_html( $data['viewport'] ?? 'N/A' ) . '</li>';

		if ( ! empty( $data['color_scheme'] ) ) {
			$parts[] = '<li>Tema: ' . esc_html( ucfirst( $data['color_scheme'] ) ) . '</li>';
		}

		$parts[] = '</ul>';

		// Reporter
		$user = wp_get_current_user();
		if ( $user->ID > 0 ) {
			$parts[] = '<p><strong>Rapporterat av:</strong> ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')</p>';
		} elseif ( ! empty( $data['email'] ) ) {
			$parts[] = '<p><strong>Rapporterat av:</strong> ' . esc_html( $data['email'] ) . '</p>';
		}

		$parts[] = '<hr>';
		$parts[] = '<p><em>Skickat via AGoodBug</em></p>';

		return implode( "\n", $parts );
	}

	/**
	 * Log error to post meta and error_log
	 *
	 * @param int    $post_id Post ID.
	 * @param string $message Error message.
	 */
	private function log_error( $post_id, $message ) {
		error_log( 'AGoodBug - AGoodMember: ' . $message );
		if ( $post_id ) {
			update_post_meta( $post_id, '_agoodmember_error', $message );
		}
	}
}
