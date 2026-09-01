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
	const CANCEL_ACTION = 'bitmomo_pro_cancel_renewal';
	const CANCEL_NONCE_ACTION = 'bitmomo_pro_cancel_renewal';
	const CANCEL_NONCE_FIELD = 'bitmomo_pro_cancel_renewal_nonce';

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
		add_action( 'admin_post_' . self::CANCEL_ACTION, array( $this, 'handle_cancel_renewal' ) );
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
						<th><label for="bitmomo_pro_activate_billing"><?php esc_html_e( 'Billing period', 'bitmomo-pro' ); ?></label></th>
						<td><select name="billing_period" id="bitmomo_pro_activate_billing"><option value="monthly"><?php esc_html_e( 'Monthly — Rp149.000', 'bitmomo-pro' ); ?></option><option value="annual"><?php esc_html_e( 'Annual — Rp1.490.000', 'bitmomo-pro' ); ?></option></select></td>
					</tr>
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
							<p class="description"><?php esc_html_e( 'For Founding memberships this is calculated automatically from the billing period using calendar-safe arithmetic.', 'bitmomo-pro' ); ?></p>
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

			<hr />
			<h2><?php esc_html_e( 'Batalkan Perpanjangan', 'bitmomo-pro' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Catat permintaan pembatalan tanpa menghentikan akses pada periode yang sudah dibayar.', 'bitmomo-pro' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::CANCEL_NONCE_ACTION, self::CANCEL_NONCE_FIELD ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CANCEL_ACTION ); ?>" />
				<label for="bitmomo_pro_cancel_email"><?php esc_html_e( 'Customer email', 'bitmomo-pro' ); ?></label>
				<input type="email" required class="regular-text" name="email" id="bitmomo_pro_cancel_email" />
				<?php submit_button( __( 'Batalkan Perpanjangan', 'bitmomo-pro' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_cancel_renewal() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Not allowed.', 'bitmomo-pro' ) );
		if ( ! isset( $_POST[ self::CANCEL_NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::CANCEL_NONCE_FIELD ] ) ), self::CANCEL_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bitmomo-pro' ) );
		}
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$user = $email ? get_user_by( 'email', $email ) : false;
		$result = array( 'ok' => false, 'error' => __( 'Active Pro member not found.', 'bitmomo-pro' ) );
		if ( $user ) {
			$expires_at = get_user_meta( $user->ID, Bitmomo_Pro_Entitlements::META_EXPIRES_AT, true );
			if ( Bitmomo_Pro_Entitlement_Service::instance()->cancel_renewal( $user->ID ) ) {
				$result = array( 'ok' => true, 'email' => $email, 'expires_at' => $expires_at ? $expires_at : __( 'no expiry recorded', 'bitmomo-pro' ) );
			}
		}
		set_transient( 'bitmomo_pro_cancel_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'bitmomo_pro_cancel_result', '1', admin_url( 'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE . '&page=bitmomo-pro-activate' ) ) );
		exit;
	}

	public function handle_submit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bitmomo-pro' ) );
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bitmomo-pro' ) );
		}

		$result = $this->activate_member(
			array(
				'email'               => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
				'display_name'        => isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '',
				'validation_class'    => isset( $_POST['validation_class'] ) ? sanitize_key( wp_unslash( $_POST['validation_class'] ) ) : '',
				'acquisition_channel' => isset( $_POST['acquisition_channel'] ) ? sanitize_text_field( wp_unslash( $_POST['acquisition_channel'] ) ) : '',
				'membership_type'     => isset( $_POST['membership_type'] ) ? sanitize_key( wp_unslash( $_POST['membership_type'] ) ) : Bitmomo_Pro_Entitlement_Service::TYPE_MANUAL,
				'started_at'          => isset( $_POST['started_at'] ) ? sanitize_text_field( wp_unslash( $_POST['started_at'] ) ) : '',
				'expires_at'          => isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '',
				'billing_period'      => isset( $_POST['billing_period'] ) ? sanitize_key( wp_unslash( $_POST['billing_period'] ) ) : Bitmomo_Pro_Entitlement_Service::BILLING_MONTHLY,
				'note'                => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
				'send_welcome'        => ! empty( $_POST['send_welcome'] ),
			)
		);

		$this->store_result_and_redirect( $result );
	}

	/**
	 * The actual activation transaction, independent of $_POST/nonce/redirect
	 * so it can be unit tested directly. $args carries already-sanitized
	 * values (handle_submit() above sanitizes $_POST into exactly this
	 * shape before calling in).
	 *
	 * Edge case this method closes (found by the readiness audit): a
	 * founding-type activation for a brand-new email used to unconditionally
	 * create the WordPress account first and only discover afterwards, via
	 * grant_access()'s own cap check, that the seat cap was full — leaving
	 * an orphaned account with no Pro access and no explanation. Now:
	 * - For a NEW account, founding-cap capacity is checked via the
	 *   canonical Bitmomo_Pro_Entitlement_Service::has_founding_capacity()
	 *   BEFORE wp_insert_user() runs, so the common case never creates the
	 *   account at all.
	 * - grant_access() itself remains the sole final authority on the cap
	 *   (this pre-check is an optimization, not a second source of truth) —
	 *   if it still refuses for any reason after a new account was created
	 *   (e.g. a concurrent activation racing this one), the account is
	 *   flagged via Bitmomo_Pro_Entitlements::META_ACTIVATION_ORPHANED_AT
	 *   (visible on that user's Edit User screen) and the returned error
	 *   names the specific user ID so a founder can resolve it, rather than
	 *   leaving a silent, unexplained account behind.
	 * - An EXISTING user reused by this method is never "orphaned" by a
	 *   failed grant — that account existed before this activation attempt
	 *   and is left exactly as it was, just without the requested grant.
	 *
	 * @param array $args {
	 *     @type string $email
	 *     @type string $display_name
	 *     @type string $validation_class
	 *     @type string $acquisition_channel
	 *     @type string $membership_type   One of Bitmomo_Pro_Entitlement_Service::TYPES.
	 *     @type string $started_at
	 *     @type string $expires_at
	 *     @type string $billing_period
	 *     @type string $note
	 *     @type bool   $send_welcome
	 * }
	 * @return array{ok:bool,user_action:string,entitlement:bool,metadata:bool,welcome:string,orphaned:bool,user_id:?int,error:string}
	 */
	public function activate_member( array $args ) {
		$result = array(
			'ok'          => false,
			'user_action' => '', // 'created' | 'reused'
			'entitlement' => false,
			'metadata'    => false,
			'welcome'     => 'not_requested', // 'sent' | 'failed' | 'not_requested'
			'orphaned'    => false,
			'user_id'     => null,
			'error'       => '',
		);

		$email = isset( $args['email'] ) ? (string) $args['email'] : '';
		if ( ! $email || ! is_email( $email ) ) {
			$result['error'] = __( 'A valid customer email is required.', 'bitmomo-pro' );
			return $result;
		}

		$display_name      = isset( $args['display_name'] ) ? (string) $args['display_name'] : '';
		$validation_class  = isset( $args['validation_class'] ) ? (string) $args['validation_class'] : '';
		$acquisition       = isset( $args['acquisition_channel'] ) ? (string) $args['acquisition_channel'] : '';

		$membership_type = isset( $args['membership_type'] ) ? (string) $args['membership_type'] : Bitmomo_Pro_Entitlement_Service::TYPE_MANUAL;
		if ( ! in_array( $membership_type, Bitmomo_Pro_Entitlement_Service::TYPES, true ) ) {
			$membership_type = Bitmomo_Pro_Entitlement_Service::TYPE_MANUAL;
		}

		$started_at     = isset( $args['started_at'] ) ? (string) $args['started_at'] : '';
		$expires_at     = isset( $args['expires_at'] ) ? (string) $args['expires_at'] : '';
		$billing_period = isset( $args['billing_period'] ) ? (string) $args['billing_period'] : Bitmomo_Pro_Entitlement_Service::BILLING_MONTHLY;
		$note           = isset( $args['note'] ) ? (string) $args['note'] : '';
		$send_welcome   = ! empty( $args['send_welcome'] );

		$entitlement_service = Bitmomo_Pro_Entitlement_Service::instance();

		// --- B. User creation / reuse ---------------------------------
		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			$user_id                = $existing_user->ID;
			$result['user_action']  = 'reused';
		} else {
			// Fail closed BEFORE creating an account — see docblock above.
			// A brand-new account (user_id 0) is never exempt from the cap.
			if ( Bitmomo_Pro_Entitlement_Service::TYPE_FOUNDING === $membership_type
				&& ! $entitlement_service->has_founding_capacity( 0 ) ) {
				$result['error'] = __( 'Founding cap reached — no account was created and no access was granted.', 'bitmomo-pro' );
				return $result;
			}

			$user_id = $this->create_subscriber( $email, $display_name );
			if ( is_wp_error( $user_id ) ) {
				$result['error'] = $user_id->get_error_message();
				return $result;
			}
			$result['user_action'] = 'created';
		}

		// --- C. Entitlement via the canonical service ------------------
		$granted = $entitlement_service->grant_access(
			$user_id,
			array(
				'source'          => $membership_type,
				'started_at'      => $started_at,
				'expires_at'      => $expires_at,
				'note'            => $note,
				'billing_period'  => $billing_period,
			)
		);

		if ( ! $granted ) {
			// No silent partial success: do not proceed to metadata/welcome,
			// and do not report activation as successful.
			$result['error'] = __( 'Entitlement grant failed — activation was not completed.', 'bitmomo-pro' );

			if ( 'created' === $result['user_action'] ) {
				// The pre-check above closes the common path here, but stays
				// a backstop, not the only safety net — flag plainly rather
				// than leave a silent orphan.
				$result['orphaned'] = true;
				update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_ACTIVATION_ORPHANED_AT, current_time( 'mysql' ) );
				$result['error'] = sprintf(
					/* translators: %d: WordPress user ID */
					__( 'A WordPress account (user #%d) was created for this email, but Pro access could NOT be granted (founding cap reached). The account has no Pro access — resolve it from that user\'s Edit User screen, or delete the account if it was created in error.', 'bitmomo-pro' ),
					$user_id
				);
			}

			$result['user_id'] = $user_id;
			return $result;
		}
		$result['entitlement'] = true;

		// --- C. Validation class / acquisition channel via the canonical
		//        Usage setters — no second ad-hoc write path. -----------
		$usage   = Bitmomo_Pro_Usage::instance();
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
		return $result;
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
		if ( isset( $_GET['bitmomo_pro_cancel_result'] ) ) {
			$result = get_transient( 'bitmomo_pro_cancel_result_' . get_current_user_id() );
			delete_transient( 'bitmomo_pro_cancel_result_' . get_current_user_id() );
			if ( is_array( $result ) && ! empty( $result['ok'] ) ) {
				echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Perpanjangan %1$s dibatalkan. Akses tetap aktif sampai %2$s.', 'bitmomo-pro' ), $result['email'], $result['expires_at'] ) ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html( is_array( $result ) ? $result['error'] : __( 'Cancellation could not be recorded.', 'bitmomo-pro' ) ) . '</p></div>';
			}
			return;
		}
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
