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
			'_page_url'            => 'string',
			'_selection_coords'    => 'string',
			'_viewport'            => 'string',
			'_browser_info'        => 'string',
			'_reporter_id'         => 'integer',
			'_reporter_name'       => 'string',
			'_reporter_email'      => 'string',
			'_destination_results' => 'string',
		];

		foreach ( $meta_fields as $key => $type ) {
			register_post_meta( self::POST_TYPE, $key, [
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $type === 'integer' ? 'absint' : 'sanitize_text_field',
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
		$page_url     = get_post_meta( $post->ID, '_page_url', true );
		$viewport     = get_post_meta( $post->ID, '_viewport', true );
		$browser_info = get_post_meta( $post->ID, '_browser_info', true );
		$reporter     = get_post_meta( $post->ID, '_reporter_name', true );
		$email        = get_post_meta( $post->ID, '_reporter_email', true );
		$destinations = get_post_meta( $post->ID, '_destination_results', true );
		?>
		<style>
			.agoodbug-meta-row { margin-bottom: 12px; }
			.agoodbug-meta-label { font-weight: 600; font-size: 11px; text-transform: uppercase; color: #646970; margin-bottom: 4px; }
			.agoodbug-meta-value { word-break: break-all; }
			.agoodbug-meta-value a { text-decoration: none; }
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

		<?php if ( $viewport ) : ?>
			<div class="agoodbug-meta-row">
				<div class="agoodbug-meta-label"><?php esc_html_e( 'Viewport', 'agoodbug' ); ?></div>
				<div class="agoodbug-meta-value"><?php echo esc_html( $viewport ); ?></div>
			</div>
		<?php endif; ?>

		<?php if ( $browser_info ) : ?>
			<div class="agoodbug-meta-row">
				<div class="agoodbug-meta-label"><?php esc_html_e( 'Browser', 'agoodbug' ); ?></div>
				<div class="agoodbug-meta-value"><?php echo esc_html( $browser_info ); ?></div>
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

		$post_data = [
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => sprintf(
				/* translators: %1$s: page path, %2$s: date */
				__( 'Bug Report: %1$s - %2$s', 'agoodbug' ),
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
		update_post_meta( $post_id, '_page_url', esc_url_raw( $data['url'] ) );
		update_post_meta( $post_id, '_viewport', sanitize_text_field( $data['viewport'] ?? '' ) );
		update_post_meta( $post_id, '_browser_info', sanitize_text_field( $data['browser'] ?? '' ) );
		update_post_meta( $post_id, '_selection_coords', sanitize_text_field( $data['selection'] ?? '' ) );
		update_post_meta( $post_id, '_reporter_id', $reporter_id );
		update_post_meta( $post_id, '_reporter_name', $reporter_name );
		update_post_meta( $post_id, '_reporter_email', $reporter_email );

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
