<?php
/**
 * Slack Integration
 *
 * @package AGoodBug
 */

namespace AGoodBug\Integrations;

use AGoodBug\Plugin;

class Slack {

	/**
	 * Send feedback to Slack via webhook
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @param int    $post_id        Post ID.
	 * @return bool
	 */
	public function send( $data, $screenshot_url, $post_id ) {
		$settings    = Plugin::get_settings();
		$webhook_url = $settings['slack_webhook_url'] ?? '';

		if ( empty( $webhook_url ) ) {
			return false;
		}

		$payload = $this->build_payload( $data, $screenshot_url, $post_id );

		$response = wp_remote_post( $webhook_url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( $payload ),
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'AGoodBug - Slack error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			error_log( 'AGoodBug - Slack error: HTTP ' . $code . ' — ' . wp_remote_retrieve_body( $response ) );
			return false;
		}

		return true;
	}

	/**
	 * Build Slack Block Kit payload
	 */
	private function build_payload( array $data, string $screenshot_url, int $post_id ): array {
		$site_name = get_bloginfo( 'name' );
		$edit_url  = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		$page_url  = $data['url'] ?? '';
		$comment   = $data['comment'] ?? '';

		// Reporter
		$user = wp_get_current_user();
		if ( $user->ID > 0 ) {
			$reporter = $user->display_name . ' (' . $user->user_email . ')';
		} elseif ( ! empty( $data['email'] ) ) {
			$reporter = $data['email'];
		} else {
			$reporter = __( 'Anonymous', 'agoodbug' );
		}

		// Device/browser summary
		$device  = ucfirst( $data['device_type'] ?? 'unknown' );
		$browser = $data['browser'] ?? 'N/A';

		$blocks = [
			[
				'type' => 'header',
				'text' => [
					'type' => 'plain_text',
					'text' => '🐛 ' . sprintf( __( 'New Bug Report — %s', 'agoodbug' ), $site_name ),
				],
			],
			[
				'type'   => 'section',
				'fields' => [
					[ 'type' => 'mrkdwn', 'text' => '*' . __( 'From', 'agoodbug' ) . ':* ' . $reporter ],
					[ 'type' => 'mrkdwn', 'text' => '*' . __( 'Page', 'agoodbug' ) . ':* <' . $page_url . '|' . wp_parse_url( $page_url, PHP_URL_PATH ) . '>' ],
					[ 'type' => 'mrkdwn', 'text' => '*' . __( 'Browser', 'agoodbug' ) . ':* ' . $browser ],
					[ 'type' => 'mrkdwn', 'text' => '*' . __( 'Device', 'agoodbug' ) . ':* ' . $device ],
				],
			],
		];

		if ( ! empty( $comment ) ) {
			$blocks[] = [
				'type' => 'section',
				'text' => [
					'type' => 'mrkdwn',
					'text' => '*' . __( 'Description', 'agoodbug' ) . ':*' . "\n" . $comment,
				],
			];
		}

		if ( $screenshot_url ) {
			$blocks[] = [
				'type'      => 'image',
				'image_url' => $screenshot_url,
				'alt_text'  => __( 'Screenshot', 'agoodbug' ),
				'title'     => [ 'type' => 'plain_text', 'text' => __( 'Screenshot', 'agoodbug' ) ],
			];
		}

		$blocks[] = [ 'type' => 'divider' ];

		$blocks[] = [
			'type'     => 'actions',
			'elements' => [
				[
					'type' => 'button',
					'text' => [ 'type' => 'plain_text', 'text' => __( 'View in WordPress', 'agoodbug' ) ],
					'url'  => $edit_url,
				],
			],
		];

		return [ 'blocks' => $blocks ];
	}
}
