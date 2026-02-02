<?php
/**
 * Admin Page
 *
 * @package AGoodBug
 */

namespace AGoodBug;

class Admin_Page {

	/**
	 * Initialize
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page() {
		add_options_page(
			__( 'AGoodBug Settings', 'agoodbug' ),
			__( 'AGoodBug', 'agoodbug' ),
			'manage_options',
			'agoodbug',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== 'settings_page_agoodbug' ) {
			return;
		}

		wp_enqueue_style(
			'agoodbug-admin',
			AGOODBUG_PLUGIN_URL . 'admin/css/admin.css',
			[],
			AGOODBUG_VERSION
		);
	}

	/**
	 * Render settings page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap agoodbug-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="agoodbug-settings__header">
				<p class="description">
					<?php esc_html_e( 'Configure the feedback widget that allows users to report bugs with screenshots.', 'agoodbug' ); ?>
				</p>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'agoodbug_settings_group' );
				do_settings_sections( 'agoodbug' );
				submit_button();
				?>
			</form>

			<div class="agoodbug-settings__footer">
				<h3><?php esc_html_e( 'How it works', 'agoodbug' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Users with allowed roles see a floating bug button on the frontend.', 'agoodbug' ); ?></li>
					<li><?php esc_html_e( 'Clicking the button lets them draw a rectangle to highlight an area.', 'agoodbug' ); ?></li>
					<li><?php esc_html_e( 'A screenshot is captured and they can add a description.', 'agoodbug' ); ?></li>
					<li><?php esc_html_e( 'The report is sent to your configured destinations.', 'agoodbug' ); ?></li>
				</ol>
			</div>
		</div>
		<?php
	}
}
