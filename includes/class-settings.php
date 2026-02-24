<?php
/**
 * Settings Handler
 *
 * @package AGoodBug
 */

namespace AGoodBug;

class Settings {

	/**
	 * Option name
	 */
	const OPTION_NAME = 'agoodbug_settings';

	/**
	 * Initialize
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_agoodbug_fetch_projects', [ $this, 'ajax_fetch_projects' ] );
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'agoodbug_settings_group',
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => $this->get_defaults(),
			]
		);

		// General section
		add_settings_section(
			'agoodbug_general',
			__( 'General Settings', 'agoodbug' ),
			[ $this, 'render_general_section' ],
			'agoodbug'
		);

		add_settings_field(
			'enabled',
			__( 'Enable Widget', 'agoodbug' ),
			[ $this, 'render_checkbox_field' ],
			'agoodbug',
			'agoodbug_general',
			[
				'name'        => 'enabled',
				'description' => __( 'Show the feedback button on the frontend.', 'agoodbug' ),
			]
		);

		add_settings_field(
			'allow_anonymous',
			__( 'Allow Anonymous', 'agoodbug' ),
			[ $this, 'render_checkbox_field' ],
			'agoodbug',
			'agoodbug_general',
			[
				'name'        => 'allow_anonymous',
				'description' => __( 'Allow non-logged-in visitors to submit feedback (email field will be shown).', 'agoodbug' ),
			]
		);

		add_settings_field(
			'roles',
			__( 'Allowed Roles', 'agoodbug' ),
			[ $this, 'render_roles_field' ],
			'agoodbug',
			'agoodbug_general'
		);

		add_settings_field(
			'rate_limit',
			__( 'Rate Limit', 'agoodbug' ),
			[ $this, 'render_number_field' ],
			'agoodbug',
			'agoodbug_general',
			[
				'name'        => 'rate_limit',
				'description' => __( 'Maximum number of reports per user per hour. Set to 0 for unlimited.', 'agoodbug' ),
				'min'         => 0,
				'max'         => 1000,
			]
		);

		// Destinations section
		add_settings_section(
			'agoodbug_destinations',
			__( 'Destinations', 'agoodbug' ),
			[ $this, 'render_destinations_section' ],
			'agoodbug'
		);

		add_settings_field(
			'destinations',
			__( 'Send feedback to', 'agoodbug' ),
			[ $this, 'render_destinations_field' ],
			'agoodbug',
			'agoodbug_destinations'
		);

		// Email section
		add_settings_section(
			'agoodbug_email',
			__( 'Email Settings', 'agoodbug' ),
			null,
			'agoodbug'
		);

		add_settings_field(
			'email_recipients',
			__( 'Recipients', 'agoodbug' ),
			[ $this, 'render_text_field' ],
			'agoodbug',
			'agoodbug_email',
			[
				'name'        => 'email_recipients',
				'description' => __( 'Comma-separated email addresses.', 'agoodbug' ),
				'placeholder' => get_option( 'admin_email' ),
			]
		);

		// Checkvist section
		add_settings_section(
			'agoodbug_checkvist',
			__( 'Checkvist Integration', 'agoodbug' ),
			null,
			'agoodbug'
		);

		add_settings_field(
			'checkvist_enabled',
			__( 'Enable', 'agoodbug' ),
			[ $this, 'render_checkbox_field' ],
			'agoodbug',
			'agoodbug_checkvist',
			[ 'name' => 'checkvist_enabled' ]
		);

		add_settings_field(
			'checkvist_username',
			__( 'Username (Email)', 'agoodbug' ),
			[ $this, 'render_text_field' ],
			'agoodbug',
			'agoodbug_checkvist',
			[
				'name'        => 'checkvist_username',
				'placeholder' => 'your@email.com',
				'description' => __( 'Your Checkvist account email address.', 'agoodbug' ),
			]
		);

		add_settings_field(
			'checkvist_api_key',
			__( 'API Key', 'agoodbug' ),
			[ $this, 'render_password_field' ],
			'agoodbug',
			'agoodbug_checkvist',
			[
				'name'        => 'checkvist_api_key',
				'description' => __( 'Get this from Checkvist Settings → Integration → Remote API key.', 'agoodbug' ),
			]
		);

		add_settings_field(
			'checkvist_list_id',
			__( 'List ID', 'agoodbug' ),
			[ $this, 'render_text_field' ],
			'agoodbug',
			'agoodbug_checkvist',
			[ 'name' => 'checkvist_list_id' ]
		);

		// AGoodMember section
		add_settings_section(
			'agoodbug_agoodmember',
			__( 'AGoodMember Integration', 'agoodbug' ),
			[ $this, 'render_agoodmember_section' ],
			'agoodbug'
		);

		add_settings_field(
			'agoodmember_enabled',
			__( 'Enable', 'agoodbug' ),
			[ $this, 'render_checkbox_field' ],
			'agoodbug',
			'agoodbug_agoodmember',
			[ 'name' => 'agoodmember_enabled' ]
		);

		add_settings_field(
			'agoodmember_token',
			__( 'API-nyckel', 'agoodbug' ),
			[ $this, 'render_password_field' ],
			'agoodbug',
			'agoodbug_agoodmember',
			[
				'name'        => 'agoodmember_token',
				'description' => __( 'Generera en API-nyckel i AGoodMember → Inställningar → API-nycklar.', 'agoodbug' ),
			]
		);

		add_settings_field(
			'agoodmember_project_id',
			__( 'Projekt', 'agoodbug' ),
			[ $this, 'render_project_select_field' ],
			'agoodbug',
			'agoodbug_agoodmember'
		);
	}

	/**
	 * Render AGoodMember section
	 */
	public function render_agoodmember_section() {
		echo '<p>' . esc_html__( 'Send bug reports as tasks to AGoodMember.', 'agoodbug' ) . '</p>';
	}

	/**
	 * Get default settings
	 *
	 * @return array
	 */
	public function get_defaults() {
		return [
			'enabled'            => true,
			'allow_anonymous'    => false,
			'roles'              => [ 'administrator', 'editor' ],
			'destinations'       => [ 'cpt', 'email' ],
			'email_recipients'       => get_option( 'admin_email' ),
			'checkvist_enabled'      => false,
			'checkvist_username'     => '',
			'checkvist_api_key'      => '',
			'checkvist_list_id'      => '',
			'agoodmember_enabled'    => false,
			'agoodmember_token'      => '',
			'agoodmember_project_id' => '',
			'rate_limit'             => 10,
		];
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Input data.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = [];
		$defaults  = $this->get_defaults();

		$sanitized['enabled']          = ! empty( $input['enabled'] );
		$sanitized['allow_anonymous']  = ! empty( $input['allow_anonymous'] );
		$sanitized['roles']            = isset( $input['roles'] ) && is_array( $input['roles'] )
			? array_map( 'sanitize_text_field', $input['roles'] )
			: $defaults['roles'];
		$sanitized['destinations']     = isset( $input['destinations'] ) && is_array( $input['destinations'] )
			? array_map( 'sanitize_text_field', $input['destinations'] )
			: $defaults['destinations'];
		$sanitized['email_recipients'] = sanitize_text_field( $input['email_recipients'] ?? $defaults['email_recipients'] );

		// Checkvist
		$sanitized['checkvist_enabled']  = ! empty( $input['checkvist_enabled'] );
		$sanitized['checkvist_username'] = sanitize_email( $input['checkvist_username'] ?? '' );
		$sanitized['checkvist_api_key']  = sanitize_text_field( $input['checkvist_api_key'] ?? '' );
		$sanitized['checkvist_list_id']  = sanitize_text_field( $input['checkvist_list_id'] ?? '' );

		// AGoodMember
		$sanitized['agoodmember_enabled']    = ! empty( $input['agoodmember_enabled'] );
		$sanitized['agoodmember_token']      = sanitize_text_field( $input['agoodmember_token'] ?? '' );
		$sanitized['agoodmember_project_id'] = $this->sanitize_project_id( $input['agoodmember_project_id'] ?? '' );

		// Rate limit
		$sanitized['rate_limit'] = absint( $input['rate_limit'] ?? $defaults['rate_limit'] );

		return $sanitized;
	}

	/**
	 * Render general section
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure the feedback widget visibility and behavior.', 'agoodbug' ) . '</p>';
	}

	/**
	 * Render destinations section
	 */
	public function render_destinations_section() {
		echo '<p>' . esc_html__( 'Choose where to send feedback reports.', 'agoodbug' ) . '</p>';
	}

	/**
	 * Render checkbox field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value    = ! empty( $settings[ $args['name'] ] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['name'] . ']' ); ?>" value="1" <?php checked( $value ); ?> />
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<?php echo esc_html( $args['description'] ); ?>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Render text field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$settings    = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value       = $settings[ $args['name'] ] ?? '';
		$placeholder = $args['placeholder'] ?? '';
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['name'] . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="regular-text" />
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render password field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_password_field( $args ) {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value    = $settings[ $args['name'] ] ?? '';
		?>
		<input type="password" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['name'] . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off" />
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render number field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value    = $settings[ $args['name'] ] ?? 0;
		$min      = $args['min'] ?? 0;
		$max      = $args['max'] ?? 1000;
		?>
		<input type="number" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['name'] . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" class="small-text" />
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render roles field
	 */
	public function render_roles_field() {
		$settings       = get_option( self::OPTION_NAME, $this->get_defaults() );
		$selected_roles = $settings['roles'] ?? [ 'administrator', 'editor' ];
		$all_roles      = wp_roles()->roles;
		?>
		<fieldset>
			<?php foreach ( $all_roles as $role_slug => $role_data ) : ?>
				<label style="display: block; margin-bottom: 5px;">
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[roles][]' ); ?>" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $selected_roles, true ) ); ?> />
					<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Select which user roles can see the feedback button.', 'agoodbug' ); ?></p>
		<?php
	}

	/**
	 * Render destinations field
	 */
	public function render_destinations_field() {
		$settings     = get_option( self::OPTION_NAME, $this->get_defaults() );
		$destinations = $settings['destinations'] ?? [ 'cpt' ];

		$options = [
			'cpt'         => __( 'Save in WordPress (Bug Reports)', 'agoodbug' ),
			'email'       => __( 'Send email', 'agoodbug' ),
			'checkvist'   => __( 'Create Checkvist task', 'agoodbug' ),
			'agoodmember' => __( 'Create AGoodMember task', 'agoodbug' ),
		];
		?>
		<fieldset>
			<?php foreach ( $options as $value => $label ) : ?>
				<label style="display: block; margin-bottom: 5px;">
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[destinations][]' ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $destinations, true ) ); ?> <?php disabled( $value === 'cpt' ); ?> />
					<?php echo esc_html( $label ); ?>
					<?php if ( $value === 'cpt' ) : ?>
						<span class="description">(<?php esc_html_e( 'always enabled', 'agoodbug' ); ?>)</span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Render project select field with dynamic loading
	 */
	public function render_project_select_field() {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value    = $settings['agoodmember_project_id'] ?? '';
		?>
		<select id="agoodbug-project-select" class="regular-text" disabled>
			<option value="">&mdash; Laddar&hellip; &mdash;</option>
		</select>
		<input type="hidden" id="agoodbug-project-id" name="<?php echo esc_attr( self::OPTION_NAME . '[agoodmember_project_id]' ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<span id="agoodbug-project-status" class="agoodbug-project-status"></span>
		<p class="description"><?php esc_html_e( 'Välj ett projekt där buggrapporter ska skapas (valfritt).', 'agoodbug' ); ?></p>
		<?php
	}

	/**
	 * AJAX handler to fetch projects from AGoodMember
	 */
	public function ajax_fetch_projects() {
		check_ajax_referer( 'agoodbug_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Behörighet saknas.' );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( 'API-nyckel saknas.' );
		}

		$api_url  = Integrations\AGoodMember::API_URL;
		$response = wp_remote_get( $api_url . '/api/external/projects', [
			'headers' => [
				'X-API-Key' => $api_key,
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Kunde inte ansluta till AGoodMember: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 401 ) {
			wp_send_json_error( 'Ogiltig API-nyckel.' );
		}

		if ( $code !== 200 || empty( $body['projects'] ) ) {
			wp_send_json_error( $body['error'] ?? 'Inga projekt hittades.' );
		}

		wp_send_json_success( $body['projects'] );
	}

	/**
	 * Sanitize project ID - accepts UUID directly or extracts from URL
	 *
	 * @param string $input Project UUID or URL.
	 * @return string Project UUID or empty string.
	 */
	private function sanitize_project_id( $input ) {
		return $this->extract_project_id( $input );
	}

	/**
	 * Extract project ID from URL or return as-is if already a UUID
	 *
	 * @param string $input Project URL or UUID.
	 * @return string Project UUID or empty string.
	 */
	private function extract_project_id( $input ) {
		$input = trim( $input );

		if ( empty( $input ) ) {
			return '';
		}

		// UUID pattern
		$uuid_pattern = '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}';

		// If it looks like a URL, extract the UUID from it
		if ( filter_var( $input, FILTER_VALIDATE_URL ) || strpos( $input, '/projects/' ) !== false ) {
			if ( preg_match( '#/projects/(' . $uuid_pattern . ')#i', $input, $matches ) ) {
				return strtolower( $matches[1] );
			}
		}

		// If it's already a UUID, return it
		if ( preg_match( '/^' . $uuid_pattern . '$/i', $input ) ) {
			return strtolower( $input );
		}

		// Invalid input
		return '';
	}
}
