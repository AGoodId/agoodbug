<?php
/** Native screenshot imports through Checkvist's documented multipart API. */
namespace AGoodBug\Integrations;

use AGoodBug\Plugin;

class Checkvist_Attachment {

	const RECEIPT_META = '_agoodbug_checkvist_native';

	/** One report per destination; ambiguous imports are never repeated automatically. */
	public function send( $post_id, $content, $note, $destination, $username, $api_key ) {
		if ( ! $this->valid_id( $post_id ) || 'agoodbug_feedback' !== get_post_type( $post_id ) ) {
			return false;
		}
		$list_id = $destination['list_id'];
		$parent = $destination['parent_id'];
		$scope = hash( 'sha256', $list_id . ':' . $parent . ':' . strtolower( $username ) );
		$lock = '_agoodbug_checkvist_native_' . $post_id;
		if ( ! add_option( $lock, time(), '', 'no' ) ) {
			return false;
		}
		try {
			$receipt = get_post_meta( $post_id, self::RECEIPT_META, true );
			if ( is_array( $receipt ) && ! empty( $receipt['scope'] ) && $scope === $receipt['scope'] ) {
				$state = $receipt['state'] ?? '';
				if ( 'complete' === $state && $this->valid_id( $receipt['task_id'] ?? null ) ) {
					return (string) $receipt['task_id'];
				}
				if ( 'created' === $state && $this->valid_id( $receipt['task_id'] ?? null ) ) {
					return $this->finish( $post_id, $content, $receipt, $list_id, $username, $api_key );
				}
				if ( 'rejected' !== $state ) {
					error_log( 'AGoodBug - Checkvist: ambiguous previous import; inspect the remote list before retrying.' );
					return false;
				}
			}
			$file = $this->local_screenshot( $post_id );
			if ( false === $file ) {
				error_log( 'AGoodBug - Checkvist: a readable local PNG inside this site uploads directory is required.' );
				return false;
			}
			$receipt = array( 'scope' => $scope, 'state' => 'processing', 'attachment_sha256' => hash( 'sha256', $file['bytes'] ) );
			if ( ! update_post_meta( $post_id, self::RECEIPT_META, $receipt ) ) {
				return false;
			}
			$boundary = 'agoodbug-' . bin2hex( random_bytes( 24 ) );
			// A safe placeholder avoids import's outline/OPML/smart-syntax parsing of user text.
			$fields = array( 'import_content' => 'AGoodBug report ' . $post_id, 'import_content_note' => $note );
			if ( '' !== $parent ) {
				$fields['parent_id'] = $parent;
			}
			$body = '';
			foreach ( $fields as $name => $value ) {
				$body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n" . $value . "\r\n";
			}
			$body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"add_files[1]\"; filename=\"" . $file['name'] . "\"\r\nContent-Type: image/png\r\n\r\n" . $file['bytes'] . "\r\n--" . $boundary . "--\r\n";
			$response = wp_remote_post( Checkvist::API_URL . $list_id . '/import.json', array(
				'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary, 'Authorization' => 'Basic ' . base64_encode( $username . ':' . $api_key ) ),
				'body' => $body, 'timeout' => 90, 'redirection' => 0,
			) );
			$code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
			$result = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
			$receipt['state'] = 'uncertain';
			if ( in_array( $code, array( 400, 401, 403, 404, 413, 415, 422 ), true ) ) {
				$receipt['state'] = 'rejected';
			}
			if ( $code >= 200 && $code < 300 && is_array( $result ) && 1 === count( $result ) && isset( $result[0] ) && is_array( $result[0] )
				&& $this->valid_id( $result[0]['id'] ?? null ) && (string) $list_id === (string) ( $result[0]['checklist_id'] ?? '' )
				&& (string) ( $parent ?: '0' ) === (string) ( $result[0]['parent_id'] ?? '0' ) ) {
				$receipt['task_id'] = (string) $result[0]['id'];
				$receipt['state'] = 'created';
				// Checkvist's API cannot attest stored attachments (non-PRO imports may ignore them).
				$receipt['attachment_submitted'] = true;
			}
			if ( ! update_post_meta( $post_id, self::RECEIPT_META, $receipt ) || 'created' !== $receipt['state'] ) {
				error_log( 'AGoodBug - Checkvist: native import incomplete; no automatic retry or alternate destination.' );
				return false;
			}
			return $this->finish( $post_id, $content, $receipt, $list_id, $username, $api_key );
		} finally {
			delete_option( $lock );
		}
	}

	/** Finalise an existing imported task; failures retry only this idempotent PUT. */
	private function finish( $post_id, $content, $receipt, $list_id, $username, $api_key ) {
		$response = wp_remote_request( Checkvist::API_URL . $list_id . '/tasks/' . $receipt['task_id'] . '.json', array(
			'method' => 'PUT',
			'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Basic ' . base64_encode( $username . ':' . $api_key ) ),
			'body' => wp_json_encode( array( 'task' => array( 'content' => $content, 'tags' => 'todo,agoodbug', 'due_date' => 'today' ) ) ),
			'timeout' => 30, 'redirection' => 0,
		) );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $result ) || (string) $receipt['task_id'] !== (string) ( $result['id'] ?? '' ) ) {
			return false;
		}
		$receipt['state'] = 'complete';
		return update_post_meta( $post_id, self::RECEIPT_META, $receipt ) ? $receipt['task_id'] : false;
	}

	/** Never fetch screenshot URLs or read arbitrary paths, symlink escapes, or oversized files. */
	private function local_screenshot( $post_id ) {
		$path = get_post_meta( $post_id, '_screenshot_path', true );
		if ( ! is_string( $path ) || '' === $path ) {
			$attachment_id = get_post_meta( $post_id, '_screenshot_id', true );
			$path = $this->valid_id( $attachment_id ) ? get_attached_file( $attachment_id, true ) : false;
		}
		$uploads = wp_get_upload_dir();
		$basedir = $uploads['basedir'] ?? '';
		$root = is_string( $basedir ) && '' !== $basedir && empty( $uploads['error'] ) ? realpath( $basedir ) : false;
		$path = is_string( $path ) ? realpath( $path ) : false;
		if ( ! $root || ! $path || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}
		$settings = Plugin::get_settings();
		$limit = min( 10 * 1024 * 1024, max( 1, (int) ( $settings['max_screenshot_size'] ?? 5 * 1024 * 1024 ) ) );
		$bytes = file_get_contents( $path, false, null, 0, $limit + 1 );
		$info = is_string( $bytes ) ? @getimagesizefromstring( $bytes ) : false;
		if ( false === $bytes || strlen( $bytes ) > $limit || 'image/png' !== ( $info['mime'] ?? '' ) ) {
			return false;
		}
		return array( 'bytes' => $bytes, 'name' => sanitize_file_name( basename( $path ) ) );
	}

	private function valid_id( $value ) {
		return ( is_string( $value ) || is_int( $value ) ) && 1 === preg_match( '/^[1-9][0-9]*$/D', (string) $value );
	}
}
