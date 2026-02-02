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

		$username = $settings['checkvist_username'] ?? '';
		$api_key  = $settings['checkvist_api_key'] ?? '';
		$list_id  = $settings['checkvist_list_id'] ?? '';

		if ( empty( $username ) || empty( $api_key ) || empty( $list_id ) ) {
			error_log( 'AGoodBug - Checkvist: Missing credentials (username, api_key, or list_id)' );
			return false;
		}

		$user = wp_get_current_user();

		// Build task content: user's comment with ^today for due date
		$task_content = $data['comment'] . ' ^today';

		// Get screenshot data for attachment
		$screenshot_data = $data['screenshot'] ?? '';
		$has_screenshot  = ! empty( $screenshot_data ) && strpos( $screenshot_data, 'data:image/' ) === 0;

		// Use import endpoint to create task with attachment
		$api_url = add_query_arg(
			[
				'username' => $username,
				'api_key'  => $api_key,
			],
			self::API_URL . $list_id . '/import.json'
		);

		// Build multipart body
		$boundary = wp_generate_uuid4();
		$body     = '';

		// Add import_content (the task text)
		$body .= '--' . $boundary . "\r\n";
		$body .= 'Content-Disposition: form-data; name="import_content"' . "\r\n\r\n";
		$body .= $task_content . "\r\n";

		// Add parse_tasks to enable smart syntax (^today)
		$body .= '--' . $boundary . "\r\n";
		$body .= 'Content-Disposition: form-data; name="parse_tasks"' . "\r\n\r\n";
		$body .= '1' . "\r\n";

		// Add screenshot as attachment if available
		if ( $has_screenshot ) {
			// Extract mime type and decode base64
			preg_match( '/^data:image\/(\w+);base64,/', $screenshot_data, $matches );
			$extension  = $matches[1] ?? 'png';
			$image_data = base64_decode( preg_replace( '/^data:image\/\w+;base64,/', '', $screenshot_data ) );

			if ( $image_data ) {
				$body .= '--' . $boundary . "\r\n";
				$body .= 'Content-Disposition: form-data; name="add_files[1]"; filename="screenshot.' . $extension . '"' . "\r\n";
				$body .= 'Content-Type: image/' . $extension . "\r\n\r\n";
				$body .= $image_data . "\r\n";
			}
		}

		$body .= '--' . $boundary . '--';

		// Create task via import
		$response = wp_remote_post( $api_url, [
			'headers' => [
				'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
			],
			'body'    => $body,
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'AGoodBug - Checkvist error: ' . $response->get_error_message() );
			return false;
		}

		$code         = wp_remote_retrieve_response_code( $response );
		$body_content = wp_remote_retrieve_body( $response );
		$result       = json_decode( $body_content, true );

		// Import returns array of created tasks
		if ( $code >= 200 && $code < 300 && ! empty( $result ) && is_array( $result ) ) {
			// Get the first created task
			$task = is_array( $result[0] ?? null ) ? $result[0] : $result;
			$task_id = (string) ( $task['id'] ?? '' );

			if ( $task_id ) {
				// Add note with metadata
				$note = $this->build_note( $data, $user );
				$this->add_comment( $list_id, $task_id, $note, $username, $api_key );

				return $task_id;
			}
		}

		error_log( 'AGoodBug - Checkvist error (code ' . $code . '): ' . $body_content );
		return false;
	}

	/**
	 * Add a comment/note to a task
	 *
	 * @param string $list_id  List ID.
	 * @param string $task_id  Task ID.
	 * @param string $comment  Comment text.
	 * @param string $username Username.
	 * @param string $api_key  API key.
	 * @return bool
	 */
	private function add_comment( $list_id, $task_id, $comment, $username, $api_key ) {
		$api_url = add_query_arg(
			[
				'username' => $username,
				'api_key'  => $api_key,
			],
			self::API_URL . $list_id . '/tasks/' . $task_id . '/comments.json'
		);

		$response = wp_remote_post( $api_url, [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( [
				'comment' => [
					'comment' => $comment,
				],
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'AGoodBug - Checkvist comment error: ' . $response->get_error_message() );
			return false;
		}

		return true;
	}

	/**
	 * Build note with metadata
	 *
	 * @param array    $data Feedback data.
	 * @param \WP_User $user User object.
	 * @return string
	 */
	private function build_note( $data, $user ) {
		$lines = [];

		$lines[] = 'URL: ' . $data['url'];

		// Reporter info
		$reporter = '';
		if ( $user->ID > 0 ) {
			$reporter = $user->display_name . ' (' . $user->user_email . ')';
		} elseif ( ! empty( $data['email'] ) ) {
			$reporter = $data['email'];
		} else {
			$reporter = __( 'Anonymous', 'agoodbug' );
		}

		// Format: "Skickat av user@email.com 2026-02-02 kl. 18.21"
		$datetime = wp_date( 'Y-m-d' ) . ' kl. ' . wp_date( 'H.i' );
		$lines[] = sprintf(
			/* translators: 1: reporter name/email, 2: date and time */
			__( 'Skickat av %1$s %2$s', 'agoodbug' ),
			$reporter,
			$datetime
		);

		return implode( "\n", $lines );
	}
}
