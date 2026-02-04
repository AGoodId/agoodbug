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

		// AGoodApp section
		add_settings_section(
			'agoodbug_agoodapp',
			__( 'AGoodApp Integration', 'agoodbug' ),
			null,
			'agoodbug'
		);

		add_settings_field(
			'agoodapp_enabled',
			__( 'Enable', 'agoodbug' ),
			[ $this, 'render_checkbox_field' ],
			'agoodbug',
			'agoodbug_agoodapp',
			[ 'name' => 'agoodapp_enabled' ]
		);

		add_settings_field(
			'agoodapp_url',
			__( 'API URL', 'agoodbug' ),
			[ $this, 'render_text_field' ],
			'agoodbug',
			'agoodbug_agoodapp',
			[
				'name'        => 'agoodapp_url',
				'placeholder' => 'https://app.agoodid.se',
			]
		);

		add_settings_field(
			'agoodapp_token',
			__( 'API Token', 'agoodbug' ),
			[ $this, 'render_password_field' ],
			'agoodbug',
			'agoodbug_agoodapp',
			[ 'name' => 'agoodapp_token' ]
		);

		add_settings_field(
			'agoodapp_org_id',
			__( 'Organization ID', 'agoodbug' ),
			[ $this, 'render_text_field' ],
			'agoodbug',
			'agoodbug_agoodapp',
			[ 'name' => 'agoodapp_org_id' ]
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

		// GitHub section
		add_settings_section(
			'agoodbug_github',
			__( 'GitHub Integration', 'agoodbug' ),
			null,
			'agoodbug'
		);

		add_settings_field(
			'github_enabled',
			__( 'Enable', 'agoodbug' ),
			[ $this, 'render_checkbox_field' ],
			'agoodbug',
			'agoodbug_github',
			[ 'name' => 'github_enabled' ]
		);

		add_settings_field(
			'github_token',
			__( 'Personal Access Token', 'agoodbug' ),
			[ $this, 'render_password_field' ],
			'agoodbug',
			'agoodbug_github',
			[ 'name' => 'github_token' ]
		);

		add_settings_field(
			'github_repo',
			__( 'Repository', 'agoodbug' ),
			[ $this, 'render_text_field' ],
			'agoodbug',
			'agoodbug_github',
			[
				'name'        => 'github_repo',
				'placeholder' => 'owner/repo',
			]
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
			'agoodmember_url',
			__( 'API URL', 'agoodbug' ),
			[ $this, 'render_text_field' ],
			'agoodbug',
			'agoodbug_agoodmember',
			[
				'name'        => 'agoodmember_url',
				'placeholder' => 'https://your-agoodmember.vercel.app',
				'description' => __( 'The URL to your AGoodMember installation.', 'agoodbug' ),
			]
		);

		add_settings_field(
			'agoodmember_token',
			__( 'API Token', 'agoodbug' ),
			[ $this, 'render_password_field' ],
			'agoodbug',
			'agoodbug_agoodmember',
			[
				'name'        => 'agoodmember_token',
				'description' => __( 'Supabase JWT token for API authentication.', 'agoodbug' ),
			]
		);

		add_settings_field(
			'agoodmember_project_id',
			__( 'Project ID', 'agoodbug' ),
			[ $this, 'render_text_field' ],
			'agoodbug',
			'agoodbug_agoodmember',
			[
				'name'        => 'agoodmember_project_id',
				'description' => __( 'UUID of the project to assign tasks to (optional).', 'agoodbug' ),
			]
		);

		add_settings_field(
			'agoodmember_assignee_email',
			__( 'Default Assignee Email', 'agoodbug' ),
			[ $this, 'render_text_field' ],
			'agoodbug',
			'agoodbug_agoodmember',
			[
				'name'        => 'agoodmember_assignee_email',
				'placeholder' => 'user@example.com',
				'description' => __( 'Email of the person to automatically assign tasks to.', 'agoodbug' ),
			]
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
			'email_recipients'   => get_option( 'admin_email' ),
			'agoodapp_enabled'   => false,
			'agoodapp_url'       => '',
			'agoodapp_token'     => '',
			'agoodapp_org_id'    => '',
			'checkvist_enabled'  => false,
			'checkvist_username' => '',
			'checkvist_api_key'  => '',
			'checkvist_list_id'  => '',
			'github_enabled'            => false,
			'github_token'              => '',
			'github_repo'               => '',
			'agoodmember_enabled'       => false,
			'agoodmember_url'           => '',
			'agoodmember_token'         => '',
			'agoodmember_project_id'    => '',
			'agoodmember_assignee_email' => '',
			'rate_limit'                => 10,
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

		// AGoodApp
		$sanitized['agoodapp_enabled'] = ! empty( $input['agoodapp_enabled'] );
		$sanitized['agoodapp_url']     = esc_url_raw( $input['agoodapp_url'] ?? '' );
		$sanitized['agoodapp_token']   = sanitize_text_field( $input['agoodapp_token'] ?? '' );
		$sanitized['agoodapp_org_id']  = sanitize_text_field( $input['agoodapp_org_id'] ?? '' );

		// Checkvist
		$sanitized['checkvist_enabled']  = ! empty( $input['checkvist_enabled'] );
		$sanitized['checkvist_username'] = sanitize_email( $input['checkvist_username'] ?? '' );
		$sanitized['checkvist_api_key']  = sanitize_text_field( $input['checkvist_api_key'] ?? '' );
		$sanitized['checkvist_list_id']  = sanitize_text_field( $input['checkvist_list_id'] ?? '' );

		// GitHub
		$sanitized['github_enabled'] = ! empty( $input['github_enabled'] );
		$sanitized['github_token']   = sanitize_text_field( $input['github_token'] ?? '' );
		$sanitized['github_repo']    = sanitize_text_field( $input['github_repo'] ?? '' );

		// AGoodMember
		$sanitized['agoodmember_enabled']        = ! empty( $input['agoodmember_enabled'] );
		$sanitized['agoodmember_url']            = esc_url_raw( $input['agoodmember_url'] ?? '' );
		$sanitized['agoodmember_token']          = sanitize_text_field( $input['agoodmember_token'] ?? '' );
		$sanitized['agoodmember_project_id']     = sanitize_text_field( $input['agoodmember_project_id'] ?? '' );
		$sanitized['agoodmember_assignee_email'] = sanitize_email( $input['agoodmember_assignee_email'] ?? '' );

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
			'agoodapp'    => __( 'Send to AGoodApp', 'agoodbug' ),
			'checkvist'   => __( 'Create Checkvist task', 'agoodbug' ),
			'github'      => __( 'Create GitHub issue', 'agoodbug' ),
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
}
