<?php
/**
 * AGoodApp Integration
 *
 * @package AGoodBug
 */

namespace AGoodBug\Integrations;

use AGoodBug\Plugin;

class AGoodApp {

	/**
	 * Send feedback to AGoodApp
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @param int    $post_id        Post ID.
	 * @return string|false Issue ID or false on failure.
	 */
	public function send( $data, $screenshot_url, $post_id ) {
		$settings = Plugin::get_settings();

		$api_url = rtrim( $settings['agoodapp_url'] ?? '', '/' );
		$token   = $settings['agoodapp_token'] ?? '';
		$org_id  = $settings['agoodapp_org_id'] ?? '';

		if ( empty( $api_url ) || empty( $token ) ) {
			return false;
		}

		$user = wp_get_current_user();

		// Handle reporter info for both logged-in and anonymous users
		if ( $user->ID > 0 ) {
			$reporter_name  = $user->display_name;
			$reporter_email = $user->user_email;
		} elseif ( ! empty( $data['email'] ) ) {
			$reporter_name  = $data['email'];
			$reporter_email = $data['email'];
		} else {
			$reporter_name  = __( 'Anonymous', 'agoodbug' );
			$reporter_email = '';
		}

		// Build issue data
		$issue_data = [
			'title'          => sprintf(
				/* translators: %s: page path */
				__( 'Bug Report: %s', 'agoodbug' ),
				wp_parse_url( $data['url'], PHP_URL_PATH ) ?: '/'
			),
			'description'    => $this->build_description( $data, $screenshot_url ),
			'category'       => 'tekniskt',
			'priority'       => 'medel',
			'reporter_name'  => $reporter_name,
			'reporter_email' => $reporter_email,
			'tags'           => [ 'agoodbug', 'frontend' ],
		];

		if ( ! empty( $org_id ) ) {
			$issue_data['organization_id'] = $org_id;
		}

		$response = wp_remote_post( $api_url . '/api/issues', [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $issue_data ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'AGoodBug - AGoodApp error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && ! empty( $body['id'] ) ) {
			return $body['id'];
		}

		error_log( 'AGoodBug - AGoodApp error: ' . wp_json_encode( $body ) );
		return false;
	}

	/**
	 * Build issue description
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @return string
	 */
	private function build_description( $data, $screenshot_url ) {
		$parts = [];

		$parts[] = '## ' . __( 'Description', 'agoodbug' );
		$parts[] = $data['comment'];
		$parts[] = '';

		$parts[] = '## ' . __( 'Details', 'agoodbug' );
		$parts[] = '- **URL:** ' . $data['url'];
		$parts[] = '- **Viewport:** ' . ( $data['viewport'] ?? 'N/A' );
		$parts[] = '- **Browser:** ' . ( $data['browser'] ?? 'N/A' );
		$parts[] = '';

		if ( $screenshot_url ) {
			$parts[] = '## ' . __( 'Screenshot', 'agoodbug' );
			$parts[] = '![Screenshot](' . $screenshot_url . ')';
		}

		return implode( "\n", $parts );
	}
}
