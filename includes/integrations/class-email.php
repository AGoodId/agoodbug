<?php
/**
 * Email Integration
 *
 * @package AGoodBug
 */

namespace AGoodBug\Integrations;

use AGoodBug\Plugin;

class Email {

	/**
	 * Send feedback via email
	 *
	 * @param array  $data           Feedback data.
	 * @param string $screenshot_url Screenshot URL.
	 * @param int    $post_id        Post ID.
	 * @return bool
	 */
	public function send( $data, $screenshot_url, $post_id ) {
		$settings   = Plugin::get_settings();
		$recipients = $settings['email_recipients'] ?? get_option( 'admin_email' );

		if ( empty( $recipients ) ) {
			return false;
		}

		$user    = wp_get_current_user();
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] New Bug Report', 'agoodbug' ),
			get_bloginfo( 'name' )
		);

		$body = $this->build_email_body( $data, $screenshot_url, $user, $post_id );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', get_bloginfo( 'name' ), get_option( 'admin_email' ) ),
		];

		// Add Reply-To if we have reporter email
		$reply_email = '';
		$reply_name  = '';
		if ( $user->ID > 0 ) {
			$reply_email = $user->user_email;
			$reply_name  = $user->display_name;
		} elseif ( ! empty( $data['email'] ) ) {
			$reply_email = sanitize_email( $data['email'] );
			$reply_name  = $reply_email;
		}
		if ( $reply_email ) {
			$headers[] = sprintf( 'Reply-To: %s <%s>', $reply_name, $reply_email );
		}

		return wp_mail( $recipients, $subject, $body, $headers );
	}

	/**
	 * Build HTML email body
	 *
	 * @param array    $data           Feedback data.
	 * @param string   $screenshot_url Screenshot URL.
	 * @param \WP_User $user           User object.
	 * @param int      $post_id        Post ID.
	 * @return string
	 */
	private function build_email_body( $data, $screenshot_url, $user, $post_id ) {
		$edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #1d2327; color: #fff; padding: 20px; border-radius: 8px 8px 0 0; }
				.header h1 { margin: 0; font-size: 20px; }
				.content { background: #fff; border: 1px solid #ddd; border-top: none; padding: 20px; border-radius: 0 0 8px 8px; }
				.meta { background: #f5f5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
				.meta-row { display: flex; margin-bottom: 8px; }
				.meta-label { font-weight: 600; width: 100px; color: #666; }
				.meta-value { flex: 1; }
				.comment { background: #fffbea; border-left: 4px solid #f0b429; padding: 15px; margin: 20px 0; }
				.screenshot { margin: 20px 0; }
				.screenshot img { max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 6px; }
				.button { display: inline-block; background: #2271b1; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
				.footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1>🐛 <?php esc_html_e( 'New Bug Report', 'agoodbug' ); ?></h1>
				</div>
				<div class="content">
					<div class="meta">
						<div class="meta-row">
							<span class="meta-label"><?php esc_html_e( 'From:', 'agoodbug' ); ?></span>
							<span class="meta-value">
								<?php
								if ( $user->ID > 0 ) {
									echo esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')';
								} elseif ( ! empty( $data['email'] ) ) {
									echo esc_html( $data['email'] );
								} else {
									esc_html_e( 'Anonymous', 'agoodbug' );
								}
								?>
							</span>
						</div>
						<div class="meta-row">
							<span class="meta-label"><?php esc_html_e( 'Date:', 'agoodbug' ); ?></span>
							<span class="meta-value"><?php echo esc_html( current_time( 'Y-m-d H:i' ) ); ?></span>
						</div>
						<div class="meta-row">
							<span class="meta-label"><?php esc_html_e( 'Page:', 'agoodbug' ); ?></span>
							<span class="meta-value"><a href="<?php echo esc_url( $data['url'] ); ?>"><?php echo esc_html( $data['url'] ); ?></a></span>
						</div>
						<div class="meta-row">
							<span class="meta-label"><?php esc_html_e( 'Device:', 'agoodbug' ); ?></span>
							<span class="meta-value">
								<?php
								$device_type = $data['device_type'] ?? '';
								$touch       = ! empty( $data['touch_enabled'] ) ? ' 👆' : '';
								echo esc_html( ucfirst( $device_type ) . $touch );
								?>
							</span>
						</div>
						<div class="meta-row">
							<span class="meta-label"><?php esc_html_e( 'Screen:', 'agoodbug' ); ?></span>
							<span class="meta-value">
								<?php
								$screen   = $data['screen_resolution'] ?? '';
								$viewport = $data['viewport'] ?? '';
								$ratio    = $data['pixel_ratio'] ?? 1;
								echo esc_html( $screen );
								if ( $viewport && $screen !== $viewport ) {
									echo ' → ' . esc_html( $viewport );
								}
								if ( $ratio > 1 ) {
									echo ' (@' . esc_html( $ratio ) . 'x)';
								}
								?>
							</span>
						</div>
						<div class="meta-row">
							<span class="meta-label"><?php esc_html_e( 'Browser:', 'agoodbug' ); ?></span>
							<span class="meta-value"><?php echo esc_html( $data['browser'] ?? 'N/A' ); ?></span>
						</div>
						<?php if ( ! empty( $data['color_scheme'] ) ) : ?>
						<div class="meta-row">
							<span class="meta-label"><?php esc_html_e( 'Theme:', 'agoodbug' ); ?></span>
							<span class="meta-value">
								<?php echo $data['color_scheme'] === 'dark' ? '🌙 ' : '☀️ '; ?>
								<?php echo esc_html( ucfirst( $data['color_scheme'] ) ); ?>
							</span>
						</div>
						<?php endif; ?>
						<?php if ( ! empty( $data['language'] ) || ! empty( $data['timezone'] ) ) : ?>
						<div class="meta-row">
							<span class="meta-label"><?php esc_html_e( 'Locale:', 'agoodbug' ); ?></span>
							<span class="meta-value">
								<?php
								$locale_parts = [];
								if ( ! empty( $data['language'] ) ) {
									$locale_parts[] = $data['language'];
								}
								if ( ! empty( $data['timezone'] ) ) {
									$locale_parts[] = $data['timezone'];
								}
								echo esc_html( implode( ' / ', $locale_parts ) );
								?>
							</span>
						</div>
						<?php endif; ?>
					</div>

					<div class="comment">
						<strong><?php esc_html_e( 'Description:', 'agoodbug' ); ?></strong>
						<p><?php echo nl2br( esc_html( $data['comment'] ) ); ?></p>
					</div>

					<?php if ( $screenshot_url ) : ?>
						<div class="screenshot">
							<strong><?php esc_html_e( 'Screenshot:', 'agoodbug' ); ?></strong>
							<p><a href="<?php echo esc_url( $screenshot_url ); ?>"><img src="<?php echo esc_url( $screenshot_url ); ?>" alt="Screenshot" /></a></p>
						</div>
					<?php endif; ?>

					<p>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button">
							<?php esc_html_e( 'View in WordPress', 'agoodbug' ); ?>
						</a>
					</p>

					<div class="footer">
						<p><?php esc_html_e( 'This email was sent by AGoodBug feedback widget.', 'agoodbug' ); ?></p>
					</div>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
