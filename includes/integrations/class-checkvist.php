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

	/** Parse a list ID, list URL or task URL, never an arbitrary host/path. */
	public static function parse_destination( $value ) {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		if ( preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			return [ 'list_id' => $value, 'parent_id' => '' ];
		}
		if ( preg_match( '~^https://checkvist\.com/checklists/([1-9][0-9]*)(?:/tasks/([1-9][0-9]*))?/?(?:[?#][^\s]*)?$~iD', $value, $match ) ) {
			return [ 'list_id' => $match[1], 'parent_id' => $match[2] ?? '' ];
		}
		return null;
	}

	/** Keep the parent in saved settings; list-only destinations retain their numeric ID. */
	public static function normalize_destination( $value ) {
		$destination = self::parse_destination( $value );
		if ( ! $destination ) {
			return '';
		}
		return $destination['parent_id']
			? self::API_URL . $destination['list_id'] . '/tasks/' . $destination['parent_id']
			: $destination['list_id'];
	}

	/** Extract just the list ID for callers that do not need a parent. */
	public static function normalize_list_id( $value ) {
		$destination = self::parse_destination( $value );
		return $destination['list_id'] ?? '';
	}

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

		$username    = $settings['checkvist_username'] ?? '';
		$api_key     = $settings['checkvist_api_key'] ?? '';
		$destination = self::parse_destination( $settings['checkvist_list_id'] ?? '' );
		$list_id     = $destination['list_id'] ?? '';

		error_log( 'AGoodBug - Checkvist: Starting send. Username: ' . ( $username ? 'set' : 'empty' ) . ', API key: ' . ( $api_key ? 'set' : 'empty' ) . ', List ID: ' . $list_id );

		if ( empty( $username ) || empty( $api_key ) || empty( $list_id ) ) {
			error_log( 'AGoodBug - Checkvist: Missing credentials' );
			return false;
		}

		$user = wp_get_current_user();

		$api_url = self::API_URL . $list_id . '/tasks.json';
		$task    = [
			'content'  => $data['comment'],
			// Checkvist expects comma-separated tag names without #.
			'tags'     => 'todo,agoodbug',
			// Set the date via the API field, without adding smart syntax to the text.
			'due_date' => 'today',
		];
		if ( ! empty( $destination['parent_id'] ) ) {
			$task['parent_id'] = $destination['parent_id'];
		}

		// Create task
		$response = wp_remote_post( $api_url, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $username . ':' . $api_key ),
			],
			'body'    => wp_json_encode( [
				'task' => $task,
			] ),
			'timeout'     => 30,
			'redirection' => 0,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'AGoodBug - Checkvist WP error: ' . $response->get_error_message() );
			return false;
		}

		$code         = wp_remote_retrieve_response_code( $response );
		$body_content = wp_remote_retrieve_body( $response );

		error_log( 'AGoodBug - Checkvist response code: ' . $code );

		$result = json_decode( $body_content, true );

		if ( $code >= 200 && $code < 300 && ! empty( $result['id'] ) ) {
			$task_id = (string) $result['id'];
			error_log( 'AGoodBug - Checkvist: Task created with ID: ' . $task_id );

			// Add note with metadata and screenshot URL
			$note = $this->build_note( $data, $user, $screenshot_url );
			$this->add_comment( $list_id, $task_id, $note, $username, $api_key );

			return $task_id;
		}

		error_log( 'AGoodBug - Checkvist: Failed to create task. Code: ' . $code );
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
		$api_url = self::API_URL . $list_id . '/tasks/' . $task_id . '/comments.json';

		$response = wp_remote_post( $api_url, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $username . ':' . $api_key ),
			],
			'body'    => wp_json_encode( [
				'comment' => [
					'comment' => $comment,
				],
			] ),
			'timeout'     => 30,
			'redirection' => 0,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'AGoodBug - Checkvist comment error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		error_log( 'AGoodBug - Checkvist comment response code: ' . $code );

		return $code >= 200 && $code < 300;
	}

	/**
	 * Build note with metadata
	 *
	 * @param array    $data           Feedback data.
	 * @param \WP_User $user           User object.
	 * @param string   $screenshot_url Screenshot URL.
	 * @return string
	 */
	private function build_note( $data, $user, $screenshot_url ) {
		$lines = [];

		$lines[] = 'URL: ' . $data['url'];

		// Device info
		$device_info = [];
		if ( ! empty( $data['device_type'] ) ) {
			$device_info[] = ucfirst( $data['device_type'] );
		}
		if ( ! empty( $data['touch_enabled'] ) ) {
			$device_info[] = 'touch';
		}
		if ( ! empty( $device_info ) ) {
			$lines[] = 'Enhet: ' . implode( ', ', $device_info );
		}

		// Screen info
		$screen_info = [];
		if ( ! empty( $data['screen_resolution'] ) ) {
			$screen_info[] = $data['screen_resolution'];
		}
		if ( ! empty( $data['viewport'] ) && ( empty( $data['screen_resolution'] ) || $data['screen_resolution'] !== $data['viewport'] ) ) {
			$screen_info[] = 'viewport ' . $data['viewport'];
		}
		if ( ! empty( $data['pixel_ratio'] ) && $data['pixel_ratio'] > 1 ) {
			$screen_info[] = '@' . $data['pixel_ratio'] . 'x';
		}
		if ( ! empty( $screen_info ) ) {
			$lines[] = 'Skärm: ' . implode( ', ', $screen_info );
		}

		// Browser
		if ( ! empty( $data['browser'] ) ) {
			$lines[] = 'Browser: ' . $data['browser'];
		}

		// Color scheme
		if ( ! empty( $data['color_scheme'] ) ) {
			$lines[] = 'Tema: ' . ucfirst( $data['color_scheme'] );
		}

		// Locale
		$locale_info = [];
		if ( ! empty( $data['language'] ) ) {
			$locale_info[] = $data['language'];
		}
		if ( ! empty( $data['timezone'] ) ) {
			$locale_info[] = $data['timezone'];
		}
		if ( ! empty( $locale_info ) ) {
			$lines[] = 'Språk/Zon: ' . implode( ' / ', $locale_info );
		}

		$lines[] = '';

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

		// Add screenshot URL if available
		if ( $screenshot_url ) {
			$lines[] = '';
			$lines[] = __( 'Screenshot:', 'agoodbug' ) . ' ' . $screenshot_url;
		}

		return implode( "\n", $lines );
	}
}
