<?php
/**
 * GitHub Integration
 *
 * @package AGoodBug
 */

namespace AGoodBug\Integrations;

use AGoodBug\Plugin;

class GitHub {

	/**
	 * API base URL
	 */
	const API_URL = 'https://api.github.com/repos/';

	/**
	 * Send feedback to GitHub as an issue
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @param int    $post_id        Post ID.
	 * @return string|false Issue number or false on failure.
	 */
	public function send( $data, $screenshot_url, $post_id ) {
		$settings = Plugin::get_settings();

		$token = $settings['github_token'] ?? '';
		$repo  = $settings['github_repo'] ?? '';

		if ( empty( $token ) || empty( $repo ) ) {
			return false;
		}

		$user = wp_get_current_user();

		// Build issue
		$title = sprintf(
			/* translators: %s: page path */
			__( '[Bug] %s', 'agoodbug' ),
			wp_parse_url( $data['url'], PHP_URL_PATH ) ?: '/'
		);

		$body = $this->build_issue_body( $data, $screenshot_url, $user );

		$response = wp_remote_post( self::API_URL . $repo . '/issues', [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/vnd.github+json',
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'AGoodBug-WordPress-Plugin',
			],
			'body'    => wp_json_encode( [
				'title'  => $title,
				'body'   => $body,
				'labels' => [ 'bug', 'agoodbug' ],
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'AGoodBug - GitHub error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 201 && ! empty( $body['number'] ) ) {
			return '#' . $body['number'];
		}

		error_log( 'AGoodBug - GitHub error: ' . wp_json_encode( $body ) );
		return false;
	}

	/**
	 * Build GitHub issue body
	 *
	 * @param array    $data           Feedback data.
	 * @param string   $screenshot_url Screenshot URL.
	 * @param \WP_User $user           User object.
	 * @return string
	 */
	private function build_issue_body( $data, $screenshot_url, $user ) {
		$lines = [];

		$lines[] = '## Description';
		$lines[] = '';
		$lines[] = $data['comment'];
		$lines[] = '';

		$lines[] = '## Environment';
		$lines[] = '';
		$lines[] = '| Key | Value |';
		$lines[] = '|-----|-------|';
		$lines[] = '| **URL** | ' . $data['url'] . ' |';
		$lines[] = '| **Device** | ' . ucfirst( $data['device_type'] ?? 'unknown' ) . ( ! empty( $data['touch_enabled'] ) ? ' (touch)' : '' ) . ' |';
		$lines[] = '| **Screen** | ' . ( $data['screen_resolution'] ?? 'N/A' ) . ( ! empty( $data['pixel_ratio'] ) && $data['pixel_ratio'] > 1 ? ' @' . $data['pixel_ratio'] . 'x' : '' ) . ' |';
		$lines[] = '| **Viewport** | ' . ( $data['viewport'] ?? 'N/A' ) . ' |';
		$lines[] = '| **Browser** | ' . ( $data['browser'] ?? 'N/A' ) . ' |';
		if ( ! empty( $data['color_scheme'] ) ) {
			$lines[] = '| **Color Scheme** | ' . ucfirst( $data['color_scheme'] ) . ' |';
		}
		if ( ! empty( $data['language'] ) ) {
			$lines[] = '| **Language** | ' . $data['language'] . ' |';
		}
		if ( ! empty( $data['timezone'] ) ) {
			$lines[] = '| **Timezone** | ' . $data['timezone'] . ' |';
		}

		// Handle reporter info for both logged-in and anonymous users
		if ( $user->ID > 0 ) {
			$lines[] = '| **Reporter** | ' . $user->display_name . ' (' . $user->user_email . ') |';
		} elseif ( ! empty( $data['email'] ) ) {
			$lines[] = '| **Reporter** | ' . $data['email'] . ' |';
		} else {
			$lines[] = '| **Reporter** | ' . __( 'Anonymous', 'agoodbug' ) . ' |';
		}
		$lines[] = '';

		if ( $screenshot_url ) {
			$lines[] = '## Screenshot';
			$lines[] = '';
			$lines[] = '![Screenshot](' . $screenshot_url . ')';
			$lines[] = '';
		}

		$lines[] = '---';
		$lines[] = '*Submitted via [AGoodBug](https://github.com/AGoodId/agoodbug)*';

		return implode( "\n", $lines );
	}
}
