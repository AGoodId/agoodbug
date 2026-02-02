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

		// Build task content
		$content = sprintf(
			/* translators: %s: page path */
			__( 'Bug Report: %s', 'agoodbug' ),
			wp_parse_url( $data['url'], PHP_URL_PATH ) ?: '/'
		);

		// Build API URL with authentication via query parameters
		$api_url = add_query_arg(
			[
				'username' => $username,
				'api_key'  => $api_key,
			],
			self::API_URL . $list_id . '/tasks.json'
		);

		// Create task
		$response = wp_remote_post( $api_url, [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( [
				'task' => [
					'content' => $content,
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
			$task_id = (string) $body['id'];

			// Add notes as a comment
			$notes = $this->build_notes( $data, $screenshot_url, $user );
			$this->add_comment( $list_id, $task_id, $notes, $username, $api_key );

			return $task_id;
		}

		error_log( 'AGoodBug - Checkvist error (code ' . $code . '): ' . wp_json_encode( $body ) );
		return false;
	}

	/**
	 * Add a comment to a task
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

		// Handle reporter info for both logged-in and anonymous users
		if ( $user->ID > 0 ) {
			$lines[] = '- Reporter: ' . $user->display_name . ' (' . $user->user_email . ')';
		} elseif ( ! empty( $data['email'] ) ) {
			$lines[] = '- Reporter: ' . $data['email'];
		} else {
			$lines[] = '- Reporter: ' . __( 'Anonymous', 'agoodbug' );
		}

		if ( $screenshot_url ) {
			$lines[] = '';
			$lines[] = '**' . __( 'Screenshot', 'agoodbug' ) . ':**';
			$lines[] = $screenshot_url;
		}

		return implode( "\n", $lines );
	}
}
