<?php
/**
 * Network Settings Page (Multisite)
 *
 * @package AGoodBug
 */

namespace AGoodBug;

class Network_Settings {

	const OPTION_NAME = 'agoodbug_network_settings';

	/**
	 * Initialize
	 */
	public function init() {
		add_action( 'network_admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'network_admin_edit_agoodbug_network_settings', [ $this, 'save_settings' ] );
		add_action( 'network_admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add network admin menu page
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'AGoodBug', 'agoodbug' ),
			__( 'AGoodBug', 'agoodbug' ),
			'manage_network_options',
			'agoodbug-network',
			[ $this, 'render_page' ],
			'dashicons-warning',
			80
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== 'toplevel_page_agoodbug-network-network' ) {
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
	 * Save network settings (Settings API doesn't work in network admin)
	 */
	public function save_settings() {
		check_admin_referer( 'agoodbug_network_settings' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'agoodbug' ) );
		}

		$input      = $_POST[ self::OPTION_NAME ] ?? [];
		$sanitized  = $this->sanitize( $input );

		update_site_option( self::OPTION_NAME, $sanitized );

		wp_safe_redirect(
			add_query_arg( 'updated', 'true', network_admin_url( 'admin.php?page=agoodbug-network' ) )
		);
		exit;
	}

	/**
	 * Sanitize network settings input
	 */
	private function sanitize( array $input ): array {
		$allowed_styles = [ 'button', 'tab-bottom', 'tab-side' ];

		return [
			'enabled'        => ! empty( $input['enabled'] ),
			'show_in_admin'  => ! empty( $input['show_in_admin'] ),
			'button_style'   => in_array( $input['button_style'] ?? '', $allowed_styles, true )
				? $input['button_style']
				: 'button',
			'tab_label'      => sanitize_text_field( $input['tab_label'] ?? __( 'Tyck till', 'agoodbug' ) ),
			'allow_anonymous' => ! empty( $input['allow_anonymous'] ),
			'roles'          => isset( $input['roles'] ) && is_array( $input['roles'] )
				? array_map( 'sanitize_text_field', $input['roles'] )
				: [ 'administrator', 'editor' ],
			'rate_limit'     => absint( $input['rate_limit'] ?? 10 ),
		];
	}

	/**
	 * Get current network settings with defaults
	 */
	public static function get_settings(): array {
		$defaults = [
			'enabled'         => true,
			'show_in_admin'   => true,
			'button_style'    => 'button',
			'tab_label'       => 'Tyck till',
			'allow_anonymous' => false,
			'roles'           => [ 'administrator', 'editor' ],
			'rate_limit'      => 10,
		];

		$saved = get_site_option( self::OPTION_NAME, [] );

		return array_merge( $defaults, $saved );
	}

	/**
	 * Render network admin settings page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$updated  = isset( $_GET['updated'] );
		?>
		<div class="wrap agoodbug-settings">
			<h1><?php esc_html_e( 'AGoodBug — Network Settings', 'agoodbug' ); ?></h1>

			<div class="agoodbug-settings__header">
				<p class="description">
					<?php esc_html_e( 'These settings apply as defaults to all sites in the network. Individual sites can override them in their own wp-admin.', 'agoodbug' ); ?>
				</p>
			</div>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'agoodbug' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="edit.php?action=agoodbug_network_settings">
				<?php wp_nonce_field( 'agoodbug_network_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Widget', 'agoodbug' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[enabled]' ); ?>" value="1" <?php checked( $settings['enabled'] ); ?> />
								<?php esc_html_e( 'Show the feedback button on all sites.', 'agoodbug' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show in wp-admin', 'agoodbug' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[show_in_admin]' ); ?>" value="1" <?php checked( $settings['show_in_admin'] ); ?> />
								<?php esc_html_e( 'Also show the feedback button on admin pages.', 'agoodbug' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Button Style', 'agoodbug' ); ?></th>
						<td>
							<fieldset>
								<?php
								$styles = [
									'button'     => __( 'Sticky button (bug icon, bottom right)', 'agoodbug' ),
									'tab-bottom' => __( 'Tab — bottom right corner', 'agoodbug' ),
									'tab-side'   => __( 'Tab — right edge, centered vertically', 'agoodbug' ),
								];
								foreach ( $styles as $value => $label ) :
									?>
									<label style="display: block; margin-bottom: 5px;">
										<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME . '[button_style]' ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['button_style'], $value ); ?> />
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tab Label', 'agoodbug' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_NAME . '[tab_label]' ); ?>" value="<?php echo esc_attr( $settings['tab_label'] ); ?>" placeholder="Tyck till" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Text shown on the tab (used when Button Style is set to a tab variant).', 'agoodbug' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allow Anonymous', 'agoodbug' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[allow_anonymous]' ); ?>" value="1" <?php checked( $settings['allow_anonymous'] ); ?> />
								<?php esc_html_e( 'Allow non-logged-in visitors to submit feedback.', 'agoodbug' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allowed Roles', 'agoodbug' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( wp_roles()->roles as $role_slug => $role_data ) : ?>
									<label style="display: block; margin-bottom: 5px;">
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[roles][]' ); ?>" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $settings['roles'], true ) ); ?> />
										<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Select which user roles can see the feedback button.', 'agoodbug' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rate Limit', 'agoodbug' ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_NAME . '[rate_limit]' ); ?>" value="<?php echo esc_attr( $settings['rate_limit'] ); ?>" min="0" max="1000" class="small-text" />
							<p class="description"><?php esc_html_e( 'Maximum number of reports per user per hour. Set to 0 for unlimited.', 'agoodbug' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'agoodbug' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}
}
