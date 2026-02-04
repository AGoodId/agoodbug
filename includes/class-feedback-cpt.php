<?php
/**
 * Feedback Custom Post Type
 *
 * @package AGoodBug
 */

namespace AGoodBug;

class Feedback_CPT {

	/**
	 * Post type name
	 */
	const POST_TYPE = 'agoodbug_feedback';

	/**
	 * Initialize
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'add_columns' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_columns' ], 10, 2 );
	}

	/**
	 * Register the custom post type
	 */
	public function register_post_type() {
		$labels = [
			'name'               => __( 'Bug Reports', 'agoodbug' ),
			'singular_name'      => __( 'Bug Report', 'agoodbug' ),
			'menu_name'          => __( 'Bug Reports', 'agoodbug' ),
			'add_new'            => __( 'Add New', 'agoodbug' ),
			'add_new_item'       => __( 'Add New Bug Report', 'agoodbug' ),
			'edit_item'          => __( 'Edit Bug Report', 'agoodbug' ),
			'new_item'           => __( 'New Bug Report', 'agoodbug' ),
			'view_item'          => __( 'View Bug Report', 'agoodbug' ),
			'search_items'       => __( 'Search Bug Reports', 'agoodbug' ),
			'not_found'          => __( 'No bug reports found', 'agoodbug' ),
			'not_found_in_trash' => __( 'No bug reports found in trash', 'agoodbug' ),
		];

		$args = [
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => 80,
			'menu_icon'           => 'dashicons-bug',
			'supports'            => [ 'title', 'editor' ],
			'show_in_rest'        => true,
		];

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register meta fields
	 */
	public function register_meta() {
		$meta_fields = [
			'_screenshot_id'       => 'integer',
			'_feedback_type'       => 'string',
			'_page_url'            => 'string',
			'_selection_coords'    => 'string',
			'_viewport'            => 'string',
			'_browser_info'        => 'string',
			'_reporter_id'         => 'integer',
			'_reporter_name'       => 'string',
			'_reporter_email'      => 'string',
			'_destination_results' => 'string',
			// Extended device info
			'_device_type'         => 'string',
			'_screen_resolution'   => 'string',
			'_pixel_ratio'         => 'number',
			'_color_depth'         => 'integer',
			'_touch_enabled'       => 'boolean',
			'_color_scheme'        => 'string',
			'_language'            => 'string',
			'_timezone'            => 'string',
			'_referrer'            => 'string',
			'_cookies_enabled'     => 'boolean',
			'_connection'          => 'string',
			'_memory'              => 'string',
		];

		foreach ( $meta_fields as $key => $type ) {
			$sanitize_callback = 'sanitize_text_field';
			if ( $type === 'integer' ) {
				$sanitize_callback = 'absint';
			} elseif ( $type === 'number' ) {
				$sanitize_callback = 'floatval';
			} elseif ( $type === 'boolean' ) {
				$sanitize_callback = 'rest_sanitize_boolean';
			}

			register_post_meta( self::POST_TYPE, $key, [
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $sanitize_callback,
			] );
		}
	}

	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'agoodbug_details',
			__( 'Bug Report Details', 'agoodbug' ),
			[ $this, 'render_meta_box' ],
			self::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'agoodbug_screenshot',
			__( 'Screenshot', 'agoodbug' ),
			[ $this, 'render_screenshot_meta_box' ],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render meta box
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_meta_box( $post ) {
		$page_url          = get_post_meta( $post->ID, '_page_url', true );
		$viewport          = get_post_meta( $post->ID, '_viewport', true );
		$browser_info      = get_post_meta( $post->ID, '_browser_info', true );
		$reporter          = get_post_meta( $post->ID, '_reporter_name', true );
		$email             = get_post_meta( $post->ID, '_reporter_email', true );
		$destinations      = get_post_meta( $post->ID, '_destination_results', true );

		// Extended device info
		$device_type       = get_post_meta( $post->ID, '_device_type', true );
		$screen_resolution = get_post_meta( $post->ID, '_screen_resolution', true );
		$pixel_ratio       = get_post_meta( $post->ID, '_pixel_ratio', true );
		$color_depth       = get_post_meta( $post->ID, '_color_depth', true );
		$touch_enabled     = get_post_meta( $post->ID, '_touch_enabled', true );
		$color_scheme      = get_post_meta( $post->ID, '_color_scheme', true );
		$language          = get_post_meta( $post->ID, '_language', true );
		$timezone          = get_post_meta( $post->ID, '_timezone', true );
		$referrer          = get_post_meta( $post->ID, '_referrer', true );
		$cookies_enabled   = get_post_meta( $post->ID, '_cookies_enabled', true );
		$connection        = get_post_meta( $post->ID, '_connection', true );
		$memory            = get_post_meta( $post->ID, '_memory', true );
		?>
		<style>
			.agoodbug-meta-row { margin-bottom: 12px; }
			.agoodbug-meta-label { font-weight: 600; font-size: 11px; text-transform: uppercase; color: #646970; margin-bottom: 4px; }
			.agoodbug-meta-value { word-break: break-all; }
			.agoodbug-meta-value a { text-decoration: none; }
			.agoodbug-meta-section { margin-top: 16px; padding-top: 16px; border-top: 1px solid #ddd; }
			.agoodbug-meta-section-title { font-weight: 600; font-size: 12px; color: #1d2327; margin-bottom: 12px; }
			.agoodbug-device-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 500; text-transform: uppercase; }
			.agoodbug-device-badge--mobile { background: #fef3cd; color: #856404; }
			.agoodbug-device-badge--tablet { background: #d4edda; color: #155724; }
			.agoodbug-device-badge--desktop { background: #cce5ff; color: #004085; }
			.agoodbug-device-badge--dark { background: #343a40; color: #fff; }
			.agoodbug-device-badge--light { background: #f8f9fa; color: #212529; border: 1px solid #dee2e6; }
		</style>

		<?php if ( $page_url ) : ?>
			<div class="agoodbug-meta-row">
				<div class="agoodbug-meta-label"><?php esc_html_e( 'Page URL', 'agoodbug' ); ?></div>
				<div class="agoodbug-meta-value">
					<a href="<?php echo esc_url( $page_url ); ?>" target="_blank"><?php echo esc_html( $page_url ); ?></a>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $reporter ) : ?>
			<div class="agoodbug-meta-row">
				<div class="agoodbug-meta-label"><?php esc_html_e( 'Reporter', 'agoodbug' ); ?></div>
				<div class="agoodbug-meta-value">
					<?php echo esc_html( $reporter ); ?>
					<?php if ( $email ) : ?>
						<br><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $destinations ) : ?>
			<div class="agoodbug-meta-row">
				<div class="agoodbug-meta-label"><?php esc_html_e( 'Sent To', 'agoodbug' ); ?></div>
				<div class="agoodbug-meta-value">
					<?php
					$dest_data = json_decode( $destinations, true );
					if ( $dest_data ) {
						foreach ( $dest_data as $dest => $result ) {
							$icon = $result ? '✓' : '✗';
							echo esc_html( $icon . ' ' . ucfirst( $dest ) ) . '<br>';
						}
					}
					?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Device Information Section -->
		<div class="agoodbug-meta-section">
			<div class="agoodbug-meta-section-title"><?php esc_html_e( 'Device Information', 'agoodbug' ); ?></div>

			<?php if ( $device_type ) : ?>
				<div class="agoodbug-meta-row">
					<div class="agoodbug-meta-label"><?php esc_html_e( 'Device Type', 'agoodbug' ); ?></div>
					<div class="agoodbug-meta-value">
						<span class="agoodbug-device-badge agoodbug-device-badge--<?php echo esc_attr( $device_type ); ?>">
							<?php echo esc_html( ucfirst( $device_type ) ); ?>
						</span>
						<?php if ( $touch_enabled ) : ?>
							<span title="<?php esc_attr_e( 'Touch enabled', 'agoodbug' ); ?>">👆</span>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $browser_info ) : ?>
				<div class="agoodbug-meta-row">
					<div class="agoodbug-meta-label"><?php esc_html_e( 'Browser / OS', 'agoodbug' ); ?></div>
					<div class="agoodbug-meta-value"><?php echo esc_html( $browser_info ); ?></div>
				</div>
			<?php endif; ?>

			<?php if ( $screen_resolution || $viewport ) : ?>
				<div class="agoodbug-meta-row">
					<div class="agoodbug-meta-label"><?php esc_html_e( 'Screen / Viewport', 'agoodbug' ); ?></div>
					<div class="agoodbug-meta-value">
						<?php
						if ( $screen_resolution ) {
							echo esc_html( $screen_resolution );
							if ( $viewport && $screen_resolution !== $viewport ) {
								echo ' → ' . esc_html( $viewport );
							}
						} else {
							echo esc_html( $viewport );
						}
						if ( $pixel_ratio && $pixel_ratio > 1 ) {
							echo ' <small>(@' . esc_html( $pixel_ratio ) . 'x)</small>';
						}
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $color_scheme ) : ?>
				<div class="agoodbug-meta-row">
					<div class="agoodbug-meta-label"><?php esc_html_e( 'Color Scheme', 'agoodbug' ); ?></div>
					<div class="agoodbug-meta-value">
						<span class="agoodbug-device-badge agoodbug-device-badge--<?php echo esc_attr( $color_scheme ); ?>">
							<?php echo $color_scheme === 'dark' ? '🌙 ' : '☀️ '; ?>
							<?php echo esc_html( ucfirst( $color_scheme ) ); ?>
						</span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $language || $timezone ) : ?>
				<div class="agoodbug-meta-row">
					<div class="agoodbug-meta-label"><?php esc_html_e( 'Locale / Timezone', 'agoodbug' ); ?></div>
					<div class="agoodbug-meta-value">
						<?php
						$locale_parts = [];
						if ( $language ) {
							$locale_parts[] = $language;
						}
						if ( $timezone ) {
							$locale_parts[] = $timezone;
						}
						echo esc_html( implode( ' / ', $locale_parts ) );
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $connection && $connection !== 'unknown' ) : ?>
				<div class="agoodbug-meta-row">
					<div class="agoodbug-meta-label"><?php esc_html_e( 'Connection', 'agoodbug' ); ?></div>
					<div class="agoodbug-meta-value">
						<?php
						$conn_data = json_decode( $connection, true );
						if ( $conn_data && is_array( $conn_data ) ) {
							echo esc_html( strtoupper( $conn_data['effectiveType'] ?? 'unknown' ) );
							if ( ! empty( $conn_data['downlink'] ) && $conn_data['downlink'] !== 'unknown' ) {
								echo ' (' . esc_html( $conn_data['downlink'] ) . ')';
							}
						} else {
							echo esc_html( $connection );
						}
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $memory && $memory !== 'unknown' ) : ?>
				<div class="agoodbug-meta-row">
					<div class="agoodbug-meta-label"><?php esc_html_e( 'Memory', 'agoodbug' ); ?></div>
					<div class="agoodbug-meta-value"><?php echo esc_html( $memory ); ?></div>
				</div>
			<?php endif; ?>

			<?php if ( $referrer && $referrer !== 'direct' ) : ?>
				<div class="agoodbug-meta-row">
					<div class="agoodbug-meta-label"><?php esc_html_e( 'Referrer', 'agoodbug' ); ?></div>
					<div class="agoodbug-meta-value">
						<a href="<?php echo esc_url( $referrer ); ?>" target="_blank"><?php echo esc_html( $referrer ); ?></a>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render screenshot meta box
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_screenshot_meta_box( $post ) {
		$screenshot_id = get_post_meta( $post->ID, '_screenshot_id', true );

		if ( $screenshot_id ) {
			$image_url = wp_get_attachment_url( $screenshot_id );
			if ( $image_url ) {
				?>
				<a href="<?php echo esc_url( $image_url ); ?>" target="_blank">
					<img src="<?php echo esc_url( $image_url ); ?>" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;" />
				</a>
				<?php
			}
		} else {
			echo '<p>' . esc_html__( 'No screenshot attached.', 'agoodbug' ) . '</p>';
		}
	}

	/**
	 * Add custom columns
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_columns( $columns ) {
		$new_columns = [];

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			if ( $key === 'title' ) {
				$new_columns['page_url'] = __( 'Page', 'agoodbug' );
				$new_columns['reporter'] = __( 'Reporter', 'agoodbug' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom columns
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'page_url':
				$url = get_post_meta( $post_id, '_page_url', true );
				if ( $url ) {
					$parsed = wp_parse_url( $url );
					echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $parsed['path'] ?? $url ) . '</a>';
				}
				break;

			case 'reporter':
				$name = get_post_meta( $post_id, '_reporter_name', true );
				echo esc_html( $name );
				break;
		}
	}

	/**
	 * Create a new feedback post
	 *
	 * @param array $data Feedback data.
	 * @return int|\WP_Error Post ID or error.
	 */
	public static function create_feedback( $data ) {
		$user = wp_get_current_user();
		$is_logged_in = is_user_logged_in();

		// Determine reporter info
		$reporter_name  = $is_logged_in ? $user->display_name : __( 'Anonymous', 'agoodbug' );
		$reporter_email = $is_logged_in ? $user->user_email : sanitize_email( $data['email'] ?? '' );
		$reporter_id    = $is_logged_in ? $user->ID : 0;

		// Determine title based on feedback type
		$feedback_type = $data['feedback_type'] ?? 'screenshot';
		$title_prefix  = $feedback_type === 'general'
			? __( 'Feedback', 'agoodbug' )
			: __( 'Bug Report', 'agoodbug' );

		$post_data = [
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => sprintf(
				/* translators: %1$s: feedback type prefix, %2$s: page path, %3$s: date */
				'%1$s: %2$s - %3$s',
				$title_prefix,
				wp_parse_url( $data['url'], PHP_URL_PATH ) ?: '/',
				wp_date( 'Y-m-d H:i' )
			),
			'post_content' => sanitize_textarea_field( $data['comment'] ?? '' ),
			'post_author'  => $reporter_id ?: 1, // Use admin if anonymous
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save meta
		update_post_meta( $post_id, '_feedback_type', sanitize_text_field( $data['feedback_type'] ?? 'screenshot' ) );
		update_post_meta( $post_id, '_page_url', esc_url_raw( $data['url'] ) );
		update_post_meta( $post_id, '_viewport', sanitize_text_field( $data['viewport'] ?? '' ) );
		update_post_meta( $post_id, '_browser_info', sanitize_text_field( $data['browser'] ?? '' ) );
		update_post_meta( $post_id, '_selection_coords', sanitize_text_field( $data['selection'] ?? '' ) );
		update_post_meta( $post_id, '_reporter_id', $reporter_id );
		update_post_meta( $post_id, '_reporter_name', $reporter_name );
		update_post_meta( $post_id, '_reporter_email', $reporter_email );

		// Extended device info
		update_post_meta( $post_id, '_device_type', sanitize_text_field( $data['device_type'] ?? '' ) );
		update_post_meta( $post_id, '_screen_resolution', sanitize_text_field( $data['screen_resolution'] ?? '' ) );
		update_post_meta( $post_id, '_pixel_ratio', floatval( $data['pixel_ratio'] ?? 1 ) );
		update_post_meta( $post_id, '_color_depth', absint( $data['color_depth'] ?? 0 ) );
		update_post_meta( $post_id, '_touch_enabled', ! empty( $data['touch_enabled'] ) );
		update_post_meta( $post_id, '_color_scheme', sanitize_text_field( $data['color_scheme'] ?? '' ) );
		update_post_meta( $post_id, '_language', sanitize_text_field( $data['language'] ?? '' ) );
		update_post_meta( $post_id, '_timezone', sanitize_text_field( $data['timezone'] ?? '' ) );
		update_post_meta( $post_id, '_referrer', esc_url_raw( $data['referrer'] ?? '' ) );
		update_post_meta( $post_id, '_cookies_enabled', ! empty( $data['cookies_enabled'] ) );
		update_post_meta( $post_id, '_connection', sanitize_text_field( $data['connection'] ?? '' ) );
		update_post_meta( $post_id, '_memory', sanitize_text_field( $data['memory'] ?? '' ) );

		// Handle screenshot
		if ( ! empty( $data['screenshot'] ) ) {
			$screenshot_id = self::save_screenshot( $data['screenshot'], $post_id );
			if ( $screenshot_id && ! is_wp_error( $screenshot_id ) ) {
				update_post_meta( $post_id, '_screenshot_id', $screenshot_id );
			}
		}

		return $post_id;
	}

	/**
	 * Save base64 screenshot as attachment
	 *
	 * @param string $base64_data Base64 encoded image data.
	 * @param int    $post_id     Parent post ID.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private static function save_screenshot( $base64_data, $post_id ) {
		// Extract the base64 data
		$data = explode( ',', $base64_data );
		if ( count( $data ) !== 2 ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid screenshot data', 'agoodbug' ) );
		}

		$decoded = base64_decode( $data[1] );
		if ( ! $decoded ) {
			return new \WP_Error( 'decode_failed', __( 'Failed to decode screenshot', 'agoodbug' ) );
		}

		// Check file size
		$settings = Plugin::get_settings();
		$max_size = $settings['max_screenshot_size'] ?? ( 5 * 1024 * 1024 );

		if ( strlen( $decoded ) > $max_size ) {
			return new \WP_Error( 'file_too_large', __( 'Screenshot exceeds maximum size', 'agoodbug' ) );
		}

		// Generate filename
		$filename = 'agoodbug-screenshot-' . $post_id . '-' . time() . '.png';

		// Upload
		$upload = wp_upload_bits( $filename, null, $decoded );

		if ( $upload['error'] ) {
			return new \WP_Error( 'upload_failed', $upload['error'] );
		}

		// Create attachment
		$attachment = [
			'post_mime_type' => 'image/png',
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}
}
