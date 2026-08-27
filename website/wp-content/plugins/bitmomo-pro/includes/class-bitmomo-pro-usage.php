<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bitmomo Pro PMF / usage signals for the first ~25 Founding Beta
 * customers. Deliberately small: this is NOT anonymous/visitor analytics
 * (nothing is recorded for logged-out or non-entitled visitors), it is
 * per-account usage bookkeeping tied to accounts that already exist for
 * entitlement purposes.
 *
 * Two independent things live here:
 *
 * 1. Validation classification (cold/warm/test/internal) + acquisition
 *    channel — both set manually by the founder on the User Edit screen,
 *    after reconciling payment. Never auto-inferred from behavior. This is
 *    intentionally a SEPARATE field from Bitmomo_Pro_Entitlements::META_SOURCE
 *    (founding/standard/test/manual, PR #29), which is an operational
 *    membership-type label, not a lead-quality judgment.
 *
 * 2. Usage metadata (first/last Pro view, view count, last brief viewed) —
 *    recorded automatically, but ONLY from the 'bitmomo_pro_brief_viewed'
 *    action, which Bitmomo_Pro_Shortcodes fires exactly once per dashboard
 *    render and only in the branch where a real, current, entitled-user
 *    brief was actually shown (never for anonymous, inactive, or
 *    unavailable/stale-brief views).
 *
 * Privacy: no IP address is stored, no fingerprinting, no cookies, and no
 * browsing history beyond "this already-authenticated user viewed the Pro
 * brief at this timestamp" — the same category of data WordPress already
 * keeps for logged-in users (e.g. last login), stored as ordinary user
 * meta on the account that already exists for billing/entitlement.
 *
 * "Activated" (the only definition this plugin uses, see is_activated()):
 * a user with CURRENTLY active Bitmomo Pro access who has been recorded
 * viewing at least one current brief. Paying but never opening the
 * dashboard does not count. This is a usage signal, not a renewal or
 * billing status — no renewal tracking exists anywhere in this class,
 * because no billing/payment data exists yet to base it on.
 */
class Bitmomo_Pro_Usage {

	const META_VALIDATION_CLASS     = 'bitmomo_pro_validation_class';
	const META_ACQUISITION_CHANNEL  = 'bitmomo_pro_acquisition_channel';
	const META_FIRST_VIEW_AT        = 'bitmomo_pro_first_view_at';
	const META_LAST_VIEW_AT         = 'bitmomo_pro_last_view_at';
	const META_VIEW_COUNT           = 'bitmomo_pro_view_count';
	const META_LAST_BRIEF_VIEWED_ID = 'bitmomo_pro_last_brief_viewed_id';

	const VALIDATION_CLASSES = array( 'cold', 'warm', 'test', 'internal' );

	const ACTIVATION_VIEW_THRESHOLD = 1;

	const VIEW_ACTION = 'bitmomo_pro_brief_viewed';

	const NONCE_ACTION = 'bitmomo_pro_save_pmf';
	const NONCE_FIELD  = 'bitmomo_pro_pmf_nonce';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'show_user_profile', array( $this, 'render_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_fields' ) );

		add_action( self::VIEW_ACTION, array( $this, 'record_view' ), 10, 2 );
	}

	private function validation_class_labels() {
		return array(
			'cold'     => __( 'Cold (belum ada sinyal kepercayaan sebelumnya)', 'bitmomo-pro' ),
			'warm'     => __( 'Warm (sudah mengenal Bitmomo sebelumnya)', 'bitmomo-pro' ),
			'test'     => __( 'Test (akun pengujian)', 'bitmomo-pro' ),
			'internal' => __( 'Internal (tim/founder)', 'bitmomo-pro' ),
		);
	}

	// ==================================================================
	// ADMIN: validation class + acquisition channel (manual, founder-set)
	// ==================================================================

	public function render_fields( $user ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$validation_class = get_user_meta( $user->ID, self::META_VALIDATION_CLASS, true );
		$acquisition       = get_user_meta( $user->ID, self::META_ACQUISITION_CHANNEL, true );
		$first_view        = get_user_meta( $user->ID, self::META_FIRST_VIEW_AT, true );
		$last_view         = get_user_meta( $user->ID, self::META_LAST_VIEW_AT, true );
		$view_count        = (int) get_user_meta( $user->ID, self::META_VIEW_COUNT, true );
		$last_brief_id     = get_user_meta( $user->ID, self::META_LAST_BRIEF_VIEWED_ID, true );
		?>
		<h2><?php esc_html_e( 'Bitmomo Pro — PMF / Usage Signals', 'bitmomo-pro' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Validation class is set manually by the founder after reconciling payment — never inferred automatically. The usage fields below are read-only and are recorded only when this user actually views a current, active Pro brief (never on anonymous, inactive, or stale-brief views).', 'bitmomo-pro' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="bitmomo_pro_validation_class"><?php esc_html_e( 'Validation class', 'bitmomo-pro' ); ?></label></th>
				<td>
					<select name="bitmomo_pro_validation_class" id="bitmomo_pro_validation_class">
						<option value="" <?php selected( $validation_class, '' ); ?>><?php esc_html_e( '— not yet classified —', 'bitmomo-pro' ); ?></option>
						<?php foreach ( $this->validation_class_labels() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $validation_class, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Separate from the operational Type/source field above — this is a lead-quality judgment, not a membership type.', 'bitmomo-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bitmomo_pro_acquisition_channel"><?php esc_html_e( 'Acquisition channel', 'bitmomo-pro' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="bitmomo_pro_acquisition_channel" id="bitmomo_pro_acquisition_channel" value="<?php echo esc_attr( $acquisition ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Twitter, referral, direct', 'bitmomo-pro' ); ?>" />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Usage (read-only)', 'bitmomo-pro' ); ?></th>
				<td>
					<p style="margin:4px 0;"><?php echo esc_html( sprintf( __( 'First Pro view: %s', 'bitmomo-pro' ), $first_view ? $first_view : __( 'never', 'bitmomo-pro' ) ) ); ?></p>
					<p style="margin:4px 0;"><?php echo esc_html( sprintf( __( 'Last Pro view: %s', 'bitmomo-pro' ), $last_view ? $last_view : __( 'never', 'bitmomo-pro' ) ) ); ?></p>
					<p style="margin:4px 0;"><?php echo esc_html( sprintf( __( 'View count: %d', 'bitmomo-pro' ), $view_count ) ); ?></p>
					<p style="margin:4px 0;"><?php echo esc_html( sprintf( __( 'Last brief viewed (post ID): %s', 'bitmomo-pro' ), $last_brief_id ? $last_brief_id : '—' ) ); ?></p>
					<p style="margin:4px 0;"><strong><?php echo esc_html( sprintf( __( 'Activated: %s', 'bitmomo-pro' ), $this->is_activated( $user->ID ) ? __( 'Yes', 'bitmomo-pro' ) : __( 'No', 'bitmomo-pro' ) ) ); ?></strong></p>
				</td>
			</tr>
		</table>
		<?php
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
	}

	public function save_fields( $user_id ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$validation_class = isset( $_POST['bitmomo_pro_validation_class'] ) ? sanitize_key( wp_unslash( $_POST['bitmomo_pro_validation_class'] ) ) : '';
		$this->set_validation_class( $user_id, $validation_class );

		$acquisition = isset( $_POST['bitmomo_pro_acquisition_channel'] ) ? sanitize_text_field( wp_unslash( $_POST['bitmomo_pro_acquisition_channel'] ) ) : '';
		$this->set_acquisition_channel( $user_id, $acquisition );
	}

	// ==================================================================
	// CANONICAL SETTERS (PR #34: used by both the profile form above and
	// the Activate Pro Member console, so validation-class/acquisition-
	// channel writes never happen through a second ad-hoc implementation)
	// ==================================================================

	/**
	 * Sanitizes and stores the validation class. An empty string clears
	 * classification; any value not in VALIDATION_CLASSES is normalized to
	 * empty rather than rejected outright (defensive default, matches the
	 * prior inline behavior this replaces).
	 */
	public function set_validation_class( $user_id, $class ) {
		if ( ! $user_id ) {
			return false;
		}

		$class = sanitize_key( (string) $class );
		if ( '' !== $class && ! in_array( $class, self::VALIDATION_CLASSES, true ) ) {
			$class = '';
		}

		update_user_meta( $user_id, self::META_VALIDATION_CLASS, $class );
		return true;
	}

	/**
	 * Sanitizes and stores the acquisition channel. Plain free text,
	 * capped at 100 characters — deliberately not a closed taxonomy.
	 */
	public function set_acquisition_channel( $user_id, $channel ) {
		if ( ! $user_id ) {
			return false;
		}

		$channel = sanitize_text_field( (string) $channel );
		$channel = substr( $channel, 0, 100 );

		update_user_meta( $user_id, self::META_ACQUISITION_CHANNEL, $channel );
		return true;
	}

	// ==================================================================
	// USAGE RECORDING (automatic, but only from a real entitled render)
	// ==================================================================

	/**
	 * Records one Pro brief view. Called only via the 'bitmomo_pro_brief_viewed'
	 * action, which Bitmomo_Pro_Shortcodes fires exactly once per dashboard
	 * render, and only after confirming a real, current brief was rendered
	 * for a logged-in, entitled user. No IP, no cookie, no fingerprint —
	 * just this user's own account meta.
	 */
	public function record_view( $user_id, $brief_id ) {
		if ( ! $user_id || ! $brief_id ) {
			return;
		}

		$now = current_time( 'mysql' );

		$first_view = get_user_meta( $user_id, self::META_FIRST_VIEW_AT, true );
		if ( '' === $first_view ) {
			update_user_meta( $user_id, self::META_FIRST_VIEW_AT, $now );
		}

		update_user_meta( $user_id, self::META_LAST_VIEW_AT, $now );
		update_user_meta( $user_id, self::META_LAST_BRIEF_VIEWED_ID, (int) $brief_id );

		$count = (int) get_user_meta( $user_id, self::META_VIEW_COUNT, true );
		update_user_meta( $user_id, self::META_VIEW_COUNT, $count + 1 );
	}

	/**
	 * "Activated" — see class docblock. Active entitlement AND at least
	 * one recorded view of a current brief.
	 */
	public function is_activated( $user_id ) {
		if ( ! function_exists( 'bitmomo_user_has_pro_access' ) || ! bitmomo_user_has_pro_access( $user_id ) ) {
			return false;
		}

		$count = (int) get_user_meta( $user_id, self::META_VIEW_COUNT, true );
		return $count >= self::ACTIVATION_VIEW_THRESHOLD;
	}

	// ==================================================================
	// SIMPLE ADMIN-VISIBLE COUNTS (no charts, no renewal tracking)
	// ==================================================================

	/**
	 * Deliberately simple queries suitable for ~dozens of users, matching
	 * the style of Bitmomo_Pro_Entitlement_Service::count_active_founding_members() —
	 * not cached/optimized metrics, just operational numbers for the founder.
	 */
	public function count_activated() {
		$users = get_users(
			array(
				'meta_key'   => Bitmomo_Pro_Entitlements::META_STATUS,
				'meta_value' => 'active',
				'fields'     => 'ID',
			)
		);

		$count = 0;
		foreach ( $users as $user_id ) {
			if ( $this->is_activated( $user_id ) ) {
				$count++;
			}
		}

		return $count;
	}

	public function counts_by_validation_class() {
		$users = get_users(
			array(
				'meta_key'   => Bitmomo_Pro_Entitlements::META_STATUS,
				'meta_value' => 'active',
				'fields'     => 'ID',
			)
		);

		$counts                 = array_fill_keys( self::VALIDATION_CLASSES, 0 );
		$counts['unclassified'] = 0;

		foreach ( $users as $user_id ) {
			if ( 'active' !== Bitmomo_Pro_Entitlement_Service::instance()->get_status( $user_id ) ) {
				continue;
			}

			$class = get_user_meta( $user_id, self::META_VALIDATION_CLASS, true );
			if ( in_array( $class, self::VALIDATION_CLASSES, true ) ) {
				$counts[ $class ]++;
			} else {
				$counts['unclassified']++;
			}
		}

		return $counts;
	}

	/**
	 * Renders a simple text summary line (no charts) for the wp-admin
	 * Users screen, called from Bitmomo_Pro_Users_List. Caller is
	 * responsible for the manage_options capability check.
	 */
	public function render_admin_summary() {
		$active_total = 0;
		$active_users = get_users(
			array(
				'meta_key'   => Bitmomo_Pro_Entitlements::META_STATUS,
				'meta_value' => 'active',
				'fields'     => 'ID',
			)
		);
		foreach ( $active_users as $user_id ) {
			if ( 'active' === Bitmomo_Pro_Entitlement_Service::instance()->get_status( $user_id ) ) {
				$active_total++;
			}
		}

		$activated = $this->count_activated();
		$by_class  = $this->counts_by_validation_class();

		printf(
			'<span style="margin:0 8px;display:inline-block;">%s</span>',
			esc_html(
				sprintf(
					/* translators: 1: activated count, 2: active Pro total */
					__( 'Activated: %1$d / %2$d active Pro', 'bitmomo-pro' ),
					$activated,
					$active_total
				)
			)
		);
		printf(
			'<span style="margin:0 8px;display:inline-block;">%s</span>',
			esc_html(
				sprintf(
					/* translators: 1: cold count, 2: warm count, 3: test count, 4: internal count, 5: unclassified count */
					__( 'Validation — cold: %1$d, warm: %2$d, test: %3$d, internal: %4$d, unclassified: %5$d', 'bitmomo-pro' ),
					$by_class['cold'],
					$by_class['warm'],
					$by_class['test'],
					$by_class['internal'],
					$by_class['unclassified']
				)
			)
		);
	}
}

