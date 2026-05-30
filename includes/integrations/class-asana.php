<?php
/**
 * Asana Integration
 *
 * @package AGoodBug
 */

namespace AGoodBug\Integrations;

use AGoodBug\Plugin;

class Asana {

	const API_URL = 'https://app.asana.com/api/1.0';

	/**
	 * Send feedback to Asana as a task.
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @param int    $post_id        Post ID.
	 * @return string|false Asana task URL or false on failure.
	 */
	public function send( $data, $screenshot_url, $post_id ) {
		$settings = Plugin::get_settings();

		$token       = $settings['asana_token'] ?? '';
		$workspace   = $settings['asana_workspace_gid'] ?? '';
		$project     = $settings['asana_project_gid'] ?? '';
		$section     = $settings['asana_section_gid'] ?? '';
		$assignee    = $settings['asana_assignee_gid'] ?? '';
		$title_prefix = $settings['asana_task_prefix'] ?? get_bloginfo( 'name' );

		$existing_gid = $post_id ? get_post_meta( $post_id, '_asana_task_gid', true ) : '';
		$existing_url = $post_id ? get_post_meta( $post_id, '_asana_task_url', true ) : '';
		if ( ! empty( $existing_gid ) ) {
			return $existing_url ?: $existing_gid;
		}

		if ( empty( $token ) || empty( $workspace ) || empty( $project ) ) {
			$this->log_error( $post_id, 'Missing token, workspace GID, or project GID' );
			return false;
		}

		if ( $post_id ) {
			delete_post_meta( $post_id, '_asana_error' );
		}

		try {
			$task = $this->create_task(
				$token,
				[
					'workspace' => $workspace,
					'projects'  => [ $project ],
					'name'      => $this->build_task_name( $data, $post_id, $title_prefix ),
					'notes'     => $this->build_notes( $data, $screenshot_url, $post_id ),
				] + ( ! empty( $assignee ) ? [ 'assignee' => $assignee ] : [] )
			);
		} catch ( \Exception $e ) {
			$this->log_error( $post_id, 'Task creation failed: ' . $e->getMessage() );
			return false;
		}

		if ( empty( $task['gid'] ) ) {
			$this->log_error( $post_id, 'Task response missing GID' );
			return false;
		}

		$task_gid = $task['gid'];
		$task_url = $task['permalink_url'] ?? 'https://app.asana.com/0/' . rawurlencode( $project ) . '/' . rawurlencode( $task_gid );

		if ( ! empty( $section ) ) {
			$this->add_task_to_section( $token, $section, $task_gid, $post_id );
		}

		$attachment_gid = $this->upload_screenshot( $token, $task_gid, $data, $post_id );

		if ( $post_id ) {
			update_post_meta( $post_id, '_asana_task_gid', sanitize_text_field( $task_gid ) );
			update_post_meta( $post_id, '_asana_task_url', esc_url_raw( $task_url ) );
			if ( ! empty( $attachment_gid ) ) {
				update_post_meta( $post_id, '_asana_attachment_gid', sanitize_text_field( $attachment_gid ) );
			}
		}

		return $task_url;
	}

	/**
	 * Create an Asana task.
	 *
	 * @param string $token Asana PAT.
	 * @param array  $data  Task data.
	 * @return array
	 */
	private function create_task( $token, $data ) {
		$response = wp_remote_post(
			add_query_arg( 'opt_fields', 'gid,permalink_url,name', self::API_URL . '/tasks' ),
			[
				'headers' => $this->json_headers( $token ),
				'body'    => wp_json_encode( [ 'data' => $data ] ),
				'timeout' => 30,
			]
		);

		return $this->parse_response( $response, 201 );
	}

	/**
	 * Add a task to an Asana section.
	 *
	 * @param string $token    Asana PAT.
	 * @param string $section  Section GID.
	 * @param string $task_gid Task GID.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	private function add_task_to_section( $token, $section, $task_gid, $post_id ) {
		$response = wp_remote_post(
			self::API_URL . '/sections/' . rawurlencode( $section ) . '/addTask',
			[
				'headers' => $this->json_headers( $token ),
				'body'    => wp_json_encode( [ 'data' => [ 'task' => $task_gid ] ] ),
				'timeout' => 20,
			]
		);

		try {
			$this->parse_response( $response, 200 );
			return true;
		} catch ( \Exception $e ) {
			$this->log_error( $post_id, 'Section add failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Upload screenshot as an Asana attachment.
	 *
	 * @param string $token    Asana PAT.
	 * @param string $task_gid Task GID.
	 * @param array  $data     Feedback data.
	 * @param int    $post_id  Post ID.
	 * @return string|false Attachment GID or false.
	 */
	private function upload_screenshot( $token, $task_gid, $data, $post_id ) {
		$image_bytes = $this->get_screenshot_bytes( $data, $post_id );
		if ( empty( $image_bytes ) ) {
			return false;
		}

		$boundary = 'agoodbug_asana_' . wp_generate_password( 24, false, false );
		$filename = 'screenshot.png';
		$body = $this->multipart_field( $boundary, 'parent', $task_gid );
		$body .= $this->multipart_file( $boundary, 'file', $filename, 'image/png', $image_bytes );
		$body .= '--' . $boundary . "--\r\n";

		$response = wp_remote_post(
			self::API_URL . '/attachments',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				],
				'body'    => $body,
				'timeout' => 30,
			]
		);

		try {
			$result = $this->parse_response( $response, [ 200, 201 ] );
			return $result['gid'] ?? false;
		} catch ( \Exception $e ) {
			$this->log_error( $post_id, 'Attachment upload failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Build a Ybug-like task title.
	 *
	 * @param array  $data         Feedback data.
	 * @param int    $post_id      Post ID.
	 * @param string $title_prefix Prefix.
	 * @return string
	 */
	private function build_task_name( $data, $post_id, $title_prefix ) {
		$comment = trim( $data['comment'] ?? '' );
		$summary = $comment ? wp_trim_words( $comment, 8, '...' ) : wp_parse_url( $data['url'] ?? '', PHP_URL_PATH );
		$prefix = trim( $title_prefix ) ?: get_bloginfo( 'name' );

		return sprintf( '[%s] #%d %s', $prefix, $post_id, $summary ?: __( 'Feedback report', 'agoodbug' ) );
	}

	/**
	 * Build Ybug-like Asana task notes.
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @param int    $post_id        Post ID.
	 * @return string
	 */
	private function build_notes( $data, $screenshot_url, $post_id ) {
		$user = wp_get_current_user();
		$reporter = __( 'Unknown user', 'agoodbug' );
		if ( $user->ID > 0 ) {
			$reporter = $user->display_name . ' (' . $user->user_email . ')';
		} elseif ( ! empty( $data['email'] ) ) {
			$reporter = 'Unknown user (' . sanitize_email( $data['email'] ) . ')';
		}

		$browser_parts = array_map( 'trim', explode( '/', $data['browser'] ?? '' ) );
		$browser = $browser_parts[0] ?? ( $data['browser'] ?? '' );
		$os = $browser_parts[1] ?? '';

		$lines = [
			trim( $data['comment'] ?? '' ),
			'',
			'Source url: ' . ( $data['url'] ?? '' ),
			'',
			'Reported at: ' . gmdate( 'j M \a\t H:i \U\T\C' ),
			'',
			'Reported by: ' . $reporter,
			'',
			'Rating: ',
			'',
			'Net Promoter Score®: -',
			'',
			'Console: Not captured by AGoodBug',
			'',
			'Location: -',
			'',
			'Browser: ' . $browser,
			'',
			'OS: ' . $os,
			'',
			'Screen: ' . ( $data['screen_resolution'] ?? '' ),
			'',
			'Viewport: ' . ( $data['viewport'] ?? '' ),
			'',
		];

		if ( ! empty( $screenshot_url ) ) {
			$lines[] = 'Screenshot: ' . $screenshot_url;
			$lines[] = '';
		}

		$edit_link = $post_id ? get_edit_post_link( $post_id, 'raw' ) : '';
		if ( $edit_link ) {
			$lines[] = 'For more details please visit report page in WordPress: ' . $edit_link;
		} else {
			$lines[] = 'For more details please visit the AGoodBug report in WordPress.';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Get screenshot bytes from raw submission data or saved post meta.
	 *
	 * @param array $data    Feedback data.
	 * @param int   $post_id Post ID.
	 * @return string
	 */
	private function get_screenshot_bytes( $data, $post_id ) {
		if ( ! empty( $data['screenshot'] ) && preg_match( '#^data:image/[^;]+;base64,(.+)$#', $data['screenshot'], $matches ) ) {
			$decoded = base64_decode( $matches[1], true );
			return $decoded ?: '';
		}

		$file = $post_id ? get_post_meta( $post_id, '_screenshot_path', true ) : '';
		if ( $file && file_exists( $file ) && is_readable( $file ) ) {
			$contents = file_get_contents( $file );
			return $contents ?: '';
		}

		return '';
	}

	/**
	 * Parse an Asana API response.
	 *
	 * @param array|\WP_Error $response      HTTP response.
	 * @param int|array       $expected_code Expected status code.
	 * @return array
	 * @throws \Exception When the request fails.
	 */
	private function parse_response( $response, $expected_code ) {
		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw_body, true );

		$expected_codes = is_array( $expected_code ) ? $expected_code : [ $expected_code ];
		if ( ! in_array( $code, $expected_codes, true ) || ! isset( $body['data'] ) ) {
			$message = 'HTTP ' . $code;
			if ( ! empty( $body['errors'][0]['message'] ) ) {
				$message .= ': ' . $body['errors'][0]['message'];
			} elseif ( ! empty( $raw_body ) ) {
				$message .= ': ' . substr( $raw_body, 0, 300 );
			}
			throw new \Exception( $message );
		}

		return is_array( $body['data'] ) ? $body['data'] : [];
	}

	/**
	 * JSON request headers.
	 *
	 * @param string $token Asana PAT.
	 * @return array
	 */
	private function json_headers( $token ) {
		return [
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		];
	}

	/**
	 * Build a multipart text field.
	 *
	 * @param string $boundary Boundary.
	 * @param string $name     Field name.
	 * @param string $value    Field value.
	 * @return string
	 */
	private function multipart_field( $boundary, $name, $value ) {
		return '--' . $boundary . "\r\n"
			. 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n"
			. $value . "\r\n";
	}

	/**
	 * Build a multipart file field.
	 *
	 * @param string $boundary Boundary.
	 * @param string $name     Field name.
	 * @param string $filename Filename.
	 * @param string $type     MIME type.
	 * @param string $contents File contents.
	 * @return string
	 */
	private function multipart_file( $boundary, $name, $filename, $type, $contents ) {
		return '--' . $boundary . "\r\n"
			. 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $filename . '"' . "\r\n"
			. 'Content-Type: ' . $type . "\r\n\r\n"
			. $contents . "\r\n";
	}

	/**
	 * Log integration error.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $message Error message.
	 * @return void
	 */
	private function log_error( $post_id, $message ) {
		error_log( 'AGoodBug - Asana: ' . $message );
		if ( $post_id ) {
			update_post_meta( $post_id, '_asana_error', $message );
		}
	}
}
