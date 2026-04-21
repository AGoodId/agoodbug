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
		add_action( 'wp_ajax_agoodbug_network_test_agoodmember', [ $this, 'ajax_test_agoodmember' ] );
		add_action( 'wp_ajax_agoodbug_network_test_slack', [ $this, 'ajax_test_slack' ] );
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
		$allowed_styles       = [ 'button', 'tab-bottom', 'tab-side' ];
		$allowed_destinations = [ 'cpt', 'email', 'slack', 'checkvist', 'agoodmember' ];

		return [
			'enabled'                => ! empty( $input['enabled'] ),
			'show_in_admin'          => ! empty( $input['show_in_admin'] ),
			'button_style'           => in_array( $input['button_style'] ?? '', $allowed_styles, true )
				? $input['button_style']
				: 'button',
			'tab_label'              => sanitize_text_field( $input['tab_label'] ?? __( 'Tyck till', 'agoodbug' ) ),
			'allow_anonymous'        => ! empty( $input['allow_anonymous'] ),
			'roles'                  => isset( $input['roles'] ) && is_array( $input['roles'] )
				? array_map( 'sanitize_text_field', $input['roles'] )
				: [ 'administrator', 'editor' ],
			'rate_limit'             => absint( $input['rate_limit'] ?? 10 ),
			// Destinations
			'destinations'           => isset( $input['destinations'] ) && is_array( $input['destinations'] )
				? array_values( array_intersect( array_map( 'sanitize_text_field', $input['destinations'] ), $allowed_destinations ) )
				: [ 'cpt', 'email' ],
			// Email
			'email_recipients'       => sanitize_textarea_field( $input['email_recipients'] ?? '' ),
			// Slack
			'slack_enabled'          => ! empty( $input['slack_enabled'] ),
			'slack_webhook_url'      => esc_url_raw( $input['slack_webhook_url'] ?? '' ),
			// Checkvist
			'checkvist_enabled'      => ! empty( $input['checkvist_enabled'] ),
			'checkvist_username'     => sanitize_text_field( $input['checkvist_username'] ?? '' ),
			'checkvist_api_key'      => sanitize_text_field( $input['checkvist_api_key'] ?? '' ),
			'checkvist_list_id'      => sanitize_text_field( $input['checkvist_list_id'] ?? '' ),
			// AGoodMember
			'agoodmember_enabled'    => ! empty( $input['agoodmember_enabled'] ),
			'agoodmember_token'      => sanitize_text_field( $input['agoodmember_token'] ?? '' ),
			'agoodmember_project_id' => sanitize_text_field( $input['agoodmember_project_id'] ?? '' ),
		];
	}

	/**
	 * Get current network settings with defaults
	 */
	public static function get_settings(): array {
		$defaults = [
			'enabled'                => true,
			'show_in_admin'          => true,
			'button_style'           => 'button',
			'tab_label'              => 'Tyck till',
			'allow_anonymous'        => false,
			'roles'                  => [ 'administrator', 'editor' ],
			'rate_limit'             => 10,
			'destinations'           => [ 'cpt', 'email' ],
			'email_recipients'       => '',
			'slack_enabled'          => false,
			'slack_webhook_url'      => '',
			'checkvist_enabled'      => false,
			'checkvist_username'     => '',
			'checkvist_api_key'      => '',
			'checkvist_list_id'      => '',
			'agoodmember_enabled'    => false,
			'agoodmember_token'      => '',
			'agoodmember_project_id' => '',
		];

		$saved = get_site_option( self::OPTION_NAME, [] );

		return array_merge( $defaults, $saved );
	}

	/**
	 * AJAX: test AGoodMember connection using saved network settings
	 */
	public function ajax_test_agoodmember() {
		check_ajax_referer( 'agoodbug_network_test_agoodmember' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( __( 'Behörighet saknas.', 'agoodbug' ) );
		}

		$settings   = self::get_settings();
		$api_key    = $settings['agoodmember_token'] ?? '';
		$project_id = $settings['agoodmember_project_id'] ?? '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'API-nyckel saknas — spara inställningarna först.', 'agoodbug' ) );
		}

		$response = wp_remote_get( \AGoodBug\Integrations\AGoodMember::API_URL . '/api/external/tasks?limit=1', [
			'headers' => [ 'X-API-Key' => $api_key ],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 401 || $code === 403 ) {
			wp_send_json_error( __( 'Ogiltig API-nyckel.', 'agoodbug' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			wp_send_json_error( sprintf( __( 'API svarade med HTTP %d.', 'agoodbug' ), $code ) );
		}

		$message = __( 'Anslutningen fungerar!', 'agoodbug' );

		if ( ! empty( $project_id ) ) {
			$proj = wp_remote_get( \AGoodBug\Integrations\AGoodMember::API_URL . '/api/external/tasks?project_id=' . rawurlencode( $project_id ) . '&limit=1', [
				'headers' => [ 'X-API-Key' => $api_key ],
				'timeout' => 15,
			] );
			$proj_code = wp_remote_retrieve_response_code( $proj );
			$message   = ( $proj_code >= 200 && $proj_code < 300 )
				? __( 'Anslutningen fungerar! Projektet hittades.', 'agoodbug' )
				: __( 'API-nyckeln fungerar, men projektet kunde inte verifieras.', 'agoodbug' );
		}

		wp_send_json_success( $message );
	}

	/**
	 * AJAX: test Slack webhook using saved network settings
	 */
	public function ajax_test_slack() {
		check_ajax_referer( 'agoodbug_network_test_slack' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( __( 'Behörighet saknas.', 'agoodbug' ) );
		}

		$settings    = self::get_settings();
		$webhook_url = $settings['slack_webhook_url'] ?? '';

		if ( empty( $webhook_url ) ) {
			wp_send_json_error( __( 'Webhook URL saknas — spara inställningarna först.', 'agoodbug' ) );
		}

		$response = wp_remote_post( $webhook_url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'text' => __( '✅ AGoodBug — test av Slack-anslutning lyckades!', 'agoodbug' ),
			] ),
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			wp_send_json_error( sprintf( __( 'Slack svarade med HTTP %d.', 'agoodbug' ), $code ) );
		}

		wp_send_json_success( __( 'Slack-meddelande skickat!', 'agoodbug' ) );
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

				<h2><?php esc_html_e( 'Destinations', 'agoodbug' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Where to send feedback. Individual sites can override these settings.', 'agoodbug' ); ?></p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Send To', 'agoodbug' ); ?></th>
						<td>
							<?php
							$destination_options = [
								'cpt'         => __( 'WordPress (save as post)', 'agoodbug' ),
								'email'       => __( 'Email', 'agoodbug' ),
								'slack'       => __( 'Slack', 'agoodbug' ),
								'checkvist'   => __( 'Checkvist', 'agoodbug' ),
								'agoodmember' => __( 'AGoodMember', 'agoodbug' ),
							];
							foreach ( $destination_options as $value => $label ) :
								?>
								<label style="display: block; margin-bottom: 5px;">
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[destinations][]' ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $settings['destinations'], true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email Recipients', 'agoodbug' ); ?></th>
						<td>
							<textarea name="<?php echo esc_attr( self::OPTION_NAME . '[email_recipients]' ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $settings['email_recipients'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One email address per line. Leave empty to use each site\'s admin email.', 'agoodbug' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Slack', 'agoodbug' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Slack', 'agoodbug' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[slack_enabled]' ); ?>" value="1" <?php checked( $settings['slack_enabled'] ); ?> />
								<?php esc_html_e( 'Send notifications to Slack.', 'agoodbug' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Slack Webhook URL', 'agoodbug' ); ?></th>
						<td>
							<input type="url" name="<?php echo esc_attr( self::OPTION_NAME . '[slack_webhook_url]' ); ?>" value="<?php echo esc_attr( $settings['slack_webhook_url'] ); ?>" class="large-text" placeholder="https://hooks.slack.com/services/..." />
							<p>
								<button type="button" class="button agoodbug-test-btn" data-action="agoodbug_network_test_slack" data-nonce="<?php echo esc_attr( wp_create_nonce( 'agoodbug_network_test_slack' ) ); ?>">
									<?php esc_html_e( 'Testa anslutning', 'agoodbug' ); ?>
								</button>
								<span class="agoodbug-test-result" style="margin-left:8px;"></span>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Checkvist', 'agoodbug' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Checkvist', 'agoodbug' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[checkvist_enabled]' ); ?>" value="1" <?php checked( $settings['checkvist_enabled'] ); ?> />
								<?php esc_html_e( 'Send tasks to Checkvist.', 'agoodbug' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Checkvist Username', 'agoodbug' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION_NAME . '[checkvist_username]' ); ?>" value="<?php echo esc_attr( $settings['checkvist_username'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Checkvist API Key', 'agoodbug' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION_NAME . '[checkvist_api_key]' ); ?>" value="<?php echo esc_attr( $settings['checkvist_api_key'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Checkvist List ID', 'agoodbug' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION_NAME . '[checkvist_list_id]' ); ?>" value="<?php echo esc_attr( $settings['checkvist_list_id'] ); ?>" class="regular-text" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'AGoodMember', 'agoodbug' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable AGoodMember', 'agoodbug' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[agoodmember_enabled]' ); ?>" value="1" <?php checked( $settings['agoodmember_enabled'] ); ?> />
								<?php esc_html_e( 'Send reports to AGoodMember.', 'agoodbug' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'AGoodMember Token', 'agoodbug' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION_NAME . '[agoodmember_token]' ); ?>" value="<?php echo esc_attr( $settings['agoodmember_token'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'AGoodMember Project ID', 'agoodbug' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_NAME . '[agoodmember_project_id]' ); ?>" value="<?php echo esc_attr( $settings['agoodmember_project_id'] ); ?>" class="regular-text" />
							<p>
								<button type="button" class="button agoodbug-test-btn" data-action="agoodbug_network_test_agoodmember" data-nonce="<?php echo esc_attr( wp_create_nonce( 'agoodbug_network_test_agoodmember' ) ); ?>">
									<?php esc_html_e( 'Testa anslutning', 'agoodbug' ); ?>
								</button>
								<span class="agoodbug-test-result" style="margin-left:8px;"></span>
							</p>
						</td>
					</tr>
				</table>

				<script>
				document.querySelectorAll('.agoodbug-test-btn').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var result = btn.parentElement.querySelector('.agoodbug-test-result');
						btn.disabled = true;
						result.style.color = '';
						result.textContent = '<?php echo esc_js( __( 'Testar…', 'agoodbug' ) ); ?>';
						fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: 'action=' + btn.dataset.action + '&_wpnonce=' + btn.dataset.nonce,
						})
						.then(function(r) { return r.json(); })
						.then(function(data) {
							result.style.color = data.success ? 'green' : 'red';
							result.textContent = data.success ? data.data : (data.data || '<?php echo esc_js( __( 'Okänt fel.', 'agoodbug' ) ); ?>');
						})
						.catch(function() {
							result.style.color = 'red';
							result.textContent = '<?php echo esc_js( __( 'Begäran misslyckades.', 'agoodbug' ) ); ?>';
						})
						.finally(function() { btn.disabled = false; });
					});
				});
				</script>

				<p class="submit">
					<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'agoodbug' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}
}
