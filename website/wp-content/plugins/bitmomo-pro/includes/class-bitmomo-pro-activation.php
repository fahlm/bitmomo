<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Activate Pro Member" — a founder-only manual post-payment activation
 * console for the first ~25 Founding Beta customers.
 *
 * This is explicitly NOT a CRM and NOT a payment integration. The workflow
 * this supports is: payment already confirmed externally (bank transfer,
 * manual invoice, whatever) -> founder activates the customer here in
 * under a minute. No Mayar/Midtrans/Stripe/checkout API/webhook/billing
 * logic exists anywhere in this class.
 *
 * Deliberately does not reimplement business logic that already exists:
 * - Bitmomo_Pro_Entitlement_Service::grant_access() is still the only
 *   place that writes entitlement state.
 * - Bitmomo_Pro_Usage::set_validation_class() / set_acquisition_channel()
 *   are still the only place those two fields are written.
 * - Bitmomo_Pro_Email_Service::send_welcome_email() is still the only
 *   place a welcome email is sent.
 * This class only orchestrates: find-or-create the WordPress user, then
 * call each of those three services in sequence and report what happened.
 */
class Bitmomo_Pro_Activation {

	const NONCE_ACTION = 'bitmomo_pro_activate_member';
	const NONCE_FIELD  = 'bitmomo_pro_activate_nonce';
	const ACTION       = 'bitmomo_pro_activate_member_submit';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submit' ) );
		add_action( 'admin_notices', array( $this, 'render_result_notice' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE,
			__( 'Activate Pro Member', 'bitmomo-pro' ),
			__( 'Activate Pro Member', 'bitmomo-pro' ),
			'manage_options',
			'bitmomo-pro-activate',
			array( $this, 'render_page' )
		);
	}

	private function membership_type_labels() {
		return array(
			Bitmomo_Pro_Entitlement_Service::TYPE_FOUNDING => __( 'Founding Beta member', 'bitmomo-pro' ),
			Bitmomo_Pro_Entitlement_Service::TYPE_STANDARD => __( 'Standard/future member', 'bitmomo-pro' ),
			Bitmomo_Pro_Entitlement_Service::TYPE_TEST      => __( 'Internal/test account', 'bitmomo-pro' ),
			Bitmomo_Pro_Entitlement_Service::TYPE_MANUAL    => __( 'Manual (unspecified)', 'bitmomo-pro' ),
		);
	}

	private function validation_class_labels() {
		return array(
			'cold'     => __( 'Cold', 'bitmomo-pro' ),
			'warm'     => __( 'Warm', 'bitmomo-pro' ),
			'test'     => __( 'Test', 'bitmomo-pro' ),
			'internal' => __( 'Internal', 'bitmomo-pro' ),
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$founding_count = Bitmomo_Pro_Entitlement_Service::instance()->count_active_founding_members();
		$founding_cap   = Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP;
		$today          = current_time( 'Y-m-d' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bitmomo Pro — Activate Pro Member', 'bitmomo-pro' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: active founding member count, 2: founding seat cap */
					esc_html__( 'Founding members currently active: %1$d / %2$d', 'bitmomo-pro' ),
					(int) $founding_count,
					(int) $founding_cap
				);
				?>
			</p>
			<p class="description"><?php esc_html_e( 'For customers whose payment has already been confirmed manually outside this site. This form does not process or verify any payment.', 'bitmomo-pro' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />

				<table class="form-table" role="presentation">
					<tr>
						<th><label for="bitmomo_pro_activate_email"><?php esc_html_e( 'Customer email', 'bitmomo-pro' ); ?></label></th>
						<td><input type="email" required class="regular-text" name="email" id="bitmomo_pro_activate_email" /></td>
					</tr>
					<tr>
						<th><label for="bitmomo_pro_activate_name"><?php esc_html_e( 'Display name', 'bitmomo-pro' ); ?></label></th>
						<td><input type="text" class="regular-text" name="display_name" id="bitmomo_pro_activate_name" /></td>
					</tr>
					<tr>
						<th><label for="bitmomo_pro_activate_validation"><?php esc_html_e( 'Validation class', 'bitmomo-pro' ); ?></label></th>
						<td>
							<select required name="validation_class" id="bitmomo_pro_activate_validation">
								<option value=""><?php esc_html_e( '— pilih —', 'bitmomo-pro' ); ?></option>
								<?php foreach ( $this->validation_class_labels() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'No default — you must explicitly choose this every time.', 'bitmomo-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="bitmomo_pro_activate_channel"><?php esc_html_e( 'Acquisition channel', 'bitmomo-pro' ); ?></label></th>
						<td><input type="text" class="regular-text" maxlength="100" name="acquisition_channel" id="bitmomo_pro_activate_channel" placeholder="<?php esc_attr_e( 'e.g. Twitter, referral, direct', 'bitmomo-pro' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="bitmomo_pro_activate_type"><?php esc_html_e( 'Membership type', 'bitmomo-pro' ); ?></label></th>
						<td>
							<select name="membership_type" id="bitmomo_pro_activate_type">
								<?php foreach ( $this->membership_type_labels() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( Bitmomo_Pro_Entitlement_Service::TYPE_FOUNDING, $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="bitmomo_pro_activate_start"><?php esc_html_e( 'Access start', 'bitmomo-pro' ); ?></label></th>
						<td><input type="date" name="started_at" id="bitmomo_pro_activate_start" value="<?php echo esc_attr( $today ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="bitmomo_pro_activate_expiry"><?php esc_html_e( 'Access expiry', 'bitmomo-pro' ); ?></label></th>
						<td>
							<input type="date" name="expires_at" id="bitmomo_pro_activate_expiry" />
							<p class="description"><?php esc_html_e( 'Leave blank for no expiry.', 'bitmomo-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="bitmomo_pro_activate_note"><?php esc_html_e( 'Internal note', 'bitmomo-pro' ); ?></label></th>
						<td><textarea class="regular-text" rows="2" name="note" id="bitmomo_pro_activate_note"></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Welcome email', 'bitmomo-pro' ); ?></th>
						<td>
							<label><input type="checkbox" name="send_welcome" value="1" checked="checked" /> <?php esc_html_e( 'Send welcome email after activation', 'bitmomo-pro' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Activate', 'bitmomo-pro' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_submit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bitmomo-pro' ) );
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bitmomo-pro' ) );
		}

		$result = array(
			'ok'                => false,
			'user_action'       => '', // 'created' | 'reused'
			'entitlement'       => false,
			'metadata'          => false,
			'welcome'           => 'not_requested', // 'sent' | 'failed' | 'not_requested'
			'error'             => '',
		);

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! $email || ! is_email( $email ) ) {
			$result['error'] = __( 'A valid customer email is required.', 'bitmomo-pro' );
			$this->store_result_and_redirect( $result );
		}

		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';

		$validation_class = isset( $_POST['validation_class'] ) ? sanitize_key( wp_unslash( $_POST['validation_class'] ) ) : '';
		$acquisition       = isset( $_POST['acquisition_channel'] ) ? sanitize_text_field( wp_unslash( $_POST['acquisition_channel'] ) ) : '';

		$membership_type = isset( $_POST['membership_type'] ) ? sanitize_key( wp_unslash( $_POST['membership_type'] ) ) : Bitmomo_Pro_Entitlement_Service::TYPE_MANUAL;
		if ( ! in_array( $membership_type, Bitmomo_Pro_Entitlement_Service::TYPES, true ) ) {
			$membership_type = Bitmomo_Pro_Entitlement_Service::TYPE_MANUAL;
		}

		$started_at = isset( $_POST['started_at'] ) ? sanitize_text_field( wp_unslash( $_POST['started_at'] ) ) : '';
		$expires_at = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';
		$note       = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$send_welcome = ! empty( $_POST['send_welcome'] );

		// --- B. User creation / reuse ---------------------------------
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			$user_id = $existing_user->ID;
			$result['user_action'] = 'reused';
		} else {
			$user_id = $this->create_subscriber( $email, $display_name );
			if ( is_wp_error( $user_id ) ) {
				$result['error'] = $user_id->get_error_message();
				$this->store_result_and_redirect( $result );
			}
			$result['user_action'] = 'created';
		}

		// --- C. Entitlement via the canonical service ------------------
		$granted = Bitmomo_Pro_Entitlement_Service::instance()->grant_access(
			$user_id,
			array(
				'source'     => $membership_type,
				'started_at' => $started_at,
				'expires_at' => $expires_at,
				'note'       => $note,
			)
		);

		if ( ! $granted ) {
			// No silent partial success: do not proceed to metadata/welcome,
			// and do not report activation as successful.
			$result['error'] = __( 'Entitlement grant failed — activation was not completed.', 'bitmomo-pro' );
			$this->store_result_and_redirect( $result );
		}
		$result['entitlement'] = true;

		// --- C. Validation class / acquisition channel via the canonical
		//        Usage setters — no second ad-hoc write path. -----------
		$usage = Bitmomo_Pro_Usage::instance();
		$meta_ok = $usage->set_validation_class( $user_id, $validation_class );
		$meta_ok = $usage->set_acquisition_channel( $user_id, $acquisition ) && $meta_ok;
		$result['metadata'] = $meta_ok;

		// --- Welcome email via the canonical Email Service --------------
		if ( $send_welcome ) {
			$send_result = Bitmomo_Pro_Email_Service::instance()->send_welcome_email( $user_id );
			// A failed welcome email never revokes the entitlement that was
			// just granted above — it is reported so the founder can resend
			// from this user's Edit User screen.
			$result['welcome'] = ! empty( $send_result['sent'] ) ? 'sent' : 'failed';
		}

		$result['ok']      = true;
		$result['user_id'] = $user_id;
		$this->store_result_and_redirect( $result );
	}

	/**
	 * Creates a normal WordPress subscriber account. Generates a
	 * cryptographically random password internally (WordPress requires
	 * one) that is never displayed, logged, or emailed anywhere — account
	 * setup continues through the existing native password-reset link in
	 * the PR #32 welcome email.
	 *
	 * @return int|WP_Error
	 */
	private function create_subscriber( $email, $display_name ) {
		$base_username = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base_username ) {
			$base_username = 'bitmomopro';
		}

		$username = $base_username;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$suffix++;
			$username = $base_username . $suffix;
		}

		$random_password = wp_generate_password( 32, true, true );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $random_password,
				'display_name' => '' !== $display_name ? $display_name : $username,
				'role'         => 'subscriber',
			)
		);

		unset( $random_password );

		return $user_id;
	}

	private function store_result_and_redirect( $result ) {
		set_transient( 'bitmomo_pro_activation_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'bitmomo_pro_activate_result', '1', admin_url( 'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE . '&page=bitmomo-pro-activate' ) ) );
		exit;
	}

	public function render_result_notice() {
		if ( ! isset( $_GET['bitmomo_pro_activate_result'] ) ) {
			return;
		}

		$result = get_transient( 'bitmomo_pro_activation_result_' . get_current_user_id() );
		if ( ! is_array( $result ) ) {
			return;
		}
		delete_transient( 'bitmomo_pro_activation_result_' . get_current_user_id() );

		if ( empty( $result['ok'] ) ) {
			printf(
				'<div class="notice notice-error"><p>%s %s</p></div>',
				esc_html__( 'Activation failed:', 'bitmomo-pro' ),
				esc_html( $result['error'] )
			);
			return;
		}

		$lines   = array();
		$lines[] = 'created' === $result['user_action']
			? __( 'New subscriber account created.', 'bitmomo-pro' )
			: __( 'Existing WordPress user reused (no duplicate created).', 'bitmomo-pro' );
		$lines[] = ! empty( $result['entitlement'] ) ? __( 'Bitmomo Pro access granted.', 'bitmomo-pro' ) : __( 'Entitlement NOT granted.', 'bitmomo-pro' );
		$lines[] = ! empty( $result['metadata'] ) ? __( 'Validation class and acquisition channel saved.', 'bitmomo-pro' ) : __( 'Validation/acquisition metadata NOT saved.', 'bitmomo-pro' );

		if ( 'sent' === $result['welcome'] ) {
			$lines[] = __( 'Welcome email sent.', 'bitmomo-pro' );
		} elseif ( 'failed' === $result['welcome'] ) {
			$lines[] = __( 'Welcome email FAILED to send — access remains active; resend from this user\'s Edit User screen.', 'bitmomo-pro' );
		} else {
			$lines[] = __( 'Welcome email not requested.', 'bitmomo-pro' );
		}

		$class = ( 'failed' === $result['welcome'] || empty( $result['metadata'] ) ) ? 'notice-warning' : 'notice-success';

		echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( implode( ' ', $lines ) ) . '</p></div>';
	}
}
