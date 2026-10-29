<?php
/** Hermetic native attachment transport tests: no real APIs, emails or credentials. */
namespace AGoodBug {
	class Plugin { public static $settings = array(); public static function get_settings() { return self::$settings; } }
}
namespace {
	require __DIR__ . '/../includes/integrations/class-checkvist.php';
	require __DIR__ . '/../includes/integrations/class-checkvist-attachment.php';
	function verify_native( $value, $message ) { if ( ! $value ) { throw new \RuntimeException( $message ); } }
	function wp_get_current_user() { return (object) array( 'ID' => 0 ); }
	function wp_json_encode( $data ) { return json_encode( $data ); }
	function wp_date( $format ) { return gmdate( $format, 0 ); }
	function __( $text, $domain = null ) { return $text; }
	function sanitize_file_name( $name ) { return preg_replace( '/[^a-zA-Z0-9._-]/', '-', $name ); }
	function get_post_type( $id ) { return $GLOBALS['post_type']; }
	function get_post_meta( $id, $key, $single ) { return $GLOBALS['meta'][ $key ] ?? ''; }
	function update_post_meta( $id, $key, $value ) {
		if ( $GLOBALS['fail_write'] || ( isset( $GLOBALS['meta'][$key] ) && $GLOBALS['meta'][$key] === $value ) ) { return false; }
		$GLOBALS['meta'][$key] = $value; return true;
	}
	function add_option( $key, $value, $deprecated, $autoload ) {
		verify_native( 'no' === $autoload, 'Lock must not autoload' );
		if ( isset( $GLOBALS['options'][$key] ) ) { return false; } $GLOBALS['options'][$key] = $value; return true;
	}
	function delete_option( $key ) { unset( $GLOBALS['options'][$key] ); }
	function wp_get_upload_dir() { return array( 'basedir' => $GLOBALS['upload_root'] ); }
	function get_attached_file( $id, $unfiltered ) { verify_native( true === $unfiltered, 'No remote attachment filter' ); return $GLOBALS['legacy_file']; }
	class WP_Error {}
	function is_wp_error( $response ) { return $response instanceof WP_Error; }
	function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
	function wp_remote_retrieve_body( $response ) { return $response['body']; }
	function wp_remote_get( ...$args ) { throw new \RuntimeException( 'Never fetch screenshot URLs' ); }
	function wp_mail( ...$args ) { throw new \RuntimeException( 'No email transport' ); }
	function wp_remote_post( $url, $args ) { return native_request( 'POST', $url, $args ); }
	function wp_remote_request( $url, $args ) { return native_request( $args['method'], $url, $args ); }
	function native_request( $method, $url, $args ) {
		$GLOBALS['requests'][] = array( $method, $url, $args );
		if ( $GLOBALS['during_import'] && 'POST' === $method ) { $hook = $GLOBALS['during_import']; $GLOBALS['during_import'] = null; $hook(); }
		verify_native( 0 === $args['redirection'], 'Do not forward credentials through redirects' );
		verify_native( 'Basic ' . base64_encode( 'synthetic@example.invalid:synthetic-secret' ) === $args['headers']['Authorization'], 'Existing Basic auth' );
		verify_native( false === strpos( $url, '?' ), 'No query credentials' );
		verify_native( 0 === strpos( $url, 'https://checkvist.com/checklists/955149/' ), 'Only selected Checkvist list' );
		if ( ! $GLOBALS['responses'] ) { throw new \RuntimeException( 'Unexpected request' ); }
		return array_shift( $GLOBALS['responses'] );
	}
	function reset_native( $parent = '42' ) {
		\AGoodBug\Plugin::$settings = array( 'checkvist_username' => 'synthetic@example.invalid', 'checkvist_api_key' => 'synthetic-secret', 'checkvist_list_id' => '42' === $parent ? 'https://checkvist.com/checklists/955149/tasks/42' : '955149' );
		$GLOBALS['post_type'] = 'agoodbug_feedback';
		$GLOBALS['upload_root'] = $GLOBALS['original_upload_root'];
		$GLOBALS['meta'] = array( '_screenshot_path' => $GLOBALS['png_file'] );
		$GLOBALS['options'] = $GLOBALS['requests'] = array(); $GLOBALS['during_import'] = null; $GLOBALS['fail_write'] = false;
		$GLOBALS['responses'] = array(
			array( 'code' => 200, 'body' => json_encode( array( array( 'id' => 123, 'checklist_id' => 955149, 'parent_id' => '' === $parent ? 0 : 42 ) ) ) ),
			array( 'code' => 200, 'body' => '{"id":123}' ),
		);
	}
	function send_native( $text = "A report #008080\nSecond line" ) {
		return ( new \AGoodBug\Integrations\Checkvist() )->send( array( 'comment' => $text, 'url' => 'https://example.invalid/page', 'email' => 'reporter@example.invalid', 'viewport' => '390x844' ), 'https://169.254.169.254/must-not-fetch.png', 7 );
	}
	$directory = sys_get_temp_dir() . '/agoodbug-native-' . bin2hex( random_bytes( 6 ) );
	mkdir( $directory, 0700 ); mkdir( $directory . '/uploads', 0700 );
	$GLOBALS['upload_root'] = $directory . '/uploads';
	$GLOBALS['original_upload_root'] = $GLOBALS['upload_root'];
	$GLOBALS['png_file'] = $directory . '/uploads/agoodbug-screenshot-7.png';
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/hS8AAAAASUVORK5CYII=' );
	file_put_contents( $GLOBALS['png_file'], $png );
	try {
		foreach ( array( '42', '' ) as $parent ) {
			reset_native( $parent );
			$text = "<?xml version=\"1.0\"?><opml><body><outline text=\"Never import this\"/></body></opml>\n- Report #008080 ^today !3";
			verify_native( '123' === send_native( $text ), 'Native import succeeds' );
			verify_native( 2 === count( $GLOBALS['requests'] ), 'Exactly one import and one update' );
			list( , $url, $request ) = $GLOBALS['requests'][0];
			verify_native( 'https://checkvist.com/checklists/955149/import.json' === $url && 90 === $request['timeout'], 'Documented bounded multipart endpoint' );
			$body = $request['body'];
			verify_native( false !== strpos( $body, 'name="add_files[1]"; filename="agoodbug-screenshot-7.png"' ) && false !== strpos( $body, "Content-Type: image/png\r\n\r\n" . $png ), 'Actual PNG bytes, not a URL or base64 placeholder' );
			verify_native( false !== strpos( $body, 'AGoodBug report 7' ) && false === strpos( $body, $text ), 'Untrusted outline/XML never reaches import parser' );
			foreach ( array( 'parse_tasks', 'replace_existing', 'separate_with_empty_line' ) as $field ) { verify_native( false === strpos( $body, 'name="' . $field . '"' ), 'No dangerous or unnecessary import flags' ); }
			verify_native( ( '' !== $parent ) === ( false !== strpos( $body, 'name="parent_id"' ) ), 'Parent matches selection' );
			verify_native( false !== strpos( $body, 'reporter@example.invalid' ) && false !== strpos( $body, 'https://example.invalid/page' ) && false !== strpos( $body, '390x844' ), 'Original metadata retained' );
			$update = json_decode( $GLOBALS['requests'][1][2]['body'], true );
			verify_native( $text === $update['task']['content'] && 'todo,agoodbug' === $update['task']['tags'] && 'today' === $update['task']['due_date'] && ! isset( $update['parse'] ), 'Exact multiline text, literal hashtags, native tags and due date' );
			verify_native( 'https://checkvist.com/checklists/955149/tasks/123.json' === $GLOBALS['requests'][1][1], 'Update the newly imported item only' );
			$receipt = $GLOBALS['meta'][ \AGoodBug\Integrations\Checkvist_Attachment::RECEIPT_META ];
			verify_native( hash( 'sha256', $png ) === $receipt['attachment_sha256'] && true === $receipt['attachment_submitted'] && ! isset( $receipt['attachment_verified'] ), 'Receipt honestly distinguishes submission from PRO storage verification' );
			verify_native( '123' === send_native() && 2 === count( $GLOBALS['requests'] ) && ! $GLOBALS['options'], 'Completed resend is idempotent; lock released' );
		}
		reset_native(); $GLOBALS['during_import'] = function() { verify_native( false === send_native(), 'Concurrent send blocked' ); };
		verify_native( '123' === send_native() && 2 === count( $GLOBALS['requests'] ), 'One import under concurrent invocation' );
		foreach ( array( new WP_Error(), array( 'code' => 503, 'body' => 'Do not log synthetic-secret' ), array( 'code' => 429, 'body' => '' ), array( 'code' => 200, 'body' => 'not JSON' ), array( 'code' => 200, 'body' => '[{"id":123,"checklist_id":1,"parent_id":42}]' ), array( 'code' => 200, 'body' => '[{"id":123,"checklist_id":955149,"parent_id":99}]' ), array( 'code' => 200, 'body' => '[{"id":123},{"id":124}]' ) ) as $failure ) {
			reset_native(); $GLOBALS['responses'] = array( $failure );
			verify_native( false === send_native() && false === send_native() && 1 === count( $GLOBALS['requests'] ), 'Unknown outcomes never create a retry or fallback duplicate' );
		}
		reset_native(); $GLOBALS['responses'] = array( array( 'code' => 401, 'body' => '' ) );
		verify_native( false === send_native(), 'Explicit auth rejection' );
		$GLOBALS['responses'] = array( array( 'code' => 200, 'body' => '[{"id":123,"checklist_id":955149,"parent_id":42}]' ), array( 'code' => 200, 'body' => '{"id":123}' ) );
		verify_native( '123' === send_native(), 'Manual resend after definite rejection is possible' );
		reset_native(); $GLOBALS['responses'][1] = new WP_Error();
		verify_native( false === send_native(), 'Finalisation error reported' );
		$GLOBALS['responses'] = array( array( 'code' => 200, 'body' => '{"id":123}' ) );
		verify_native( '123' === send_native() && array( 'POST', 'PUT', 'PUT' ) === array_column( $GLOBALS['requests'], 0 ), 'Partial recovery retries only existing task update' );
		reset_native(); $GLOBALS['fail_write'] = true;
		verify_native( false === send_native() && ! $GLOBALS['requests'], 'No import without durable uncertainty guard' );
		reset_native(); $GLOBALS['during_import'] = function() { $GLOBALS['fail_write'] = true; };
		verify_native( false === send_native(), 'Receipt persistence failure after remote creation is reported' );
		$GLOBALS['fail_write'] = false;
		verify_native( false === send_native() && 1 === count( $GLOBALS['requests'] ), 'Previously durable processing state prevents a duplicate after DB failure' );
		reset_native(); $GLOBALS['post_type'] = 'page';
		verify_native( false === send_native() && ! $GLOBALS['requests'], 'No arbitrary post files' );
		foreach ( array( 'missing', 'outside', 'invalid', 'oversized', 'symlink', 'empty-root' ) as $case ) {
			reset_native();
			if ( 'missing' === $case ) { $GLOBALS['meta']['_screenshot_path'] = $directory . '/uploads/missing.png'; }
			if ( 'outside' === $case ) { file_put_contents( $directory . '/private.png', $png ); $GLOBALS['meta']['_screenshot_path'] = $directory . '/private.png'; }
			if ( 'invalid' === $case ) { file_put_contents( $directory . '/uploads/not-image.png', 'private text' ); $GLOBALS['meta']['_screenshot_path'] = $directory . '/uploads/not-image.png'; }
			if ( 'oversized' === $case ) { \AGoodBug\Plugin::$settings['max_screenshot_size'] = 1; }
			if ( 'symlink' === $case ) { symlink( $directory . '/private.png', $directory . '/uploads/escape.png' ); $GLOBALS['meta']['_screenshot_path'] = $directory . '/uploads/escape.png'; }
			if ( 'empty-root' === $case ) { $GLOBALS['upload_root'] = ''; }
			verify_native( false === send_native() && ! $GLOBALS['requests'], 'Invalid local image rejected before network: ' . $case );
		}
		reset_native(); unset( $GLOBALS['meta']['_screenshot_path'] ); $GLOBALS['meta']['_screenshot_id'] = 99; $GLOBALS['legacy_file'] = $GLOBALS['png_file'];
		verify_native( '123' === send_native(), 'Legacy attachment-based screenshot supported locally' );
		echo "Native Checkvist PNG/parent/text/metadata/dedupe/concurrency/partial-failure/path-boundary tests passed; PRO storage acceptance remains live.\n";
	} finally {
		foreach ( glob( $directory . '/uploads/*' ) as $file ) { unlink( $file ); } rmdir( $directory . '/uploads' );
		foreach ( glob( $directory . '/*' ) as $file ) { unlink( $file ); } rmdir( $directory );
	}
}
