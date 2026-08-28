<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bitmomo Pro entitlement model.
 *
 * Storage: plain WordPress user meta on standard WP users. No custom
 * authentication system, no custom tables.
 *
 * As of PR #29, this class no longer writes user meta directly — every
 * write goes through Bitmomo_Pro_Entitlement_Service (grant/extend/revoke),
 * the canonical write boundary. This class still owns rendering the admin
 * profile UI and reading/validating that form's POST data before handing
 * it to the service. bitmomo_user_has_pro_access(), at the bottom of this
 * file, remains the canonical READ gate — everything that gates Pro
 * content must call it rather than reading user meta directly.
 */
class Bitmomo_Pro_Entitlements {

	const META_STATUS     = 'bitmomo_pro_status';
	const META_EXPIRES_AT = 'bitmomo_pro_expires_at';
	const META_STARTED_AT = 'bitmomo_pro_started_at';
	const META_SOURCE     = 'bitmomo_pro_source';
	const META_NOTE       = 'bitmomo_pro_note';

	const NONCE_ACTION = 'bitmomo_pro_save_entitlement';
	const NONCE_FIELD  = 'bitmomo_pro_entitlement_nonce';

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
	}

	/**
	 * Only a founder/admin (manage_options) can see or touch these fields —
	 * including on their own profile screen. A Pro subscriber editing their
	 * own profile never sees or can tamper with this section.
	 */
	private function can_manage_entitlements() {
		return current_user_can( 'manage_options' );
	}

	private function type_labels() {
		return array(
			Bitmomo_Pro_Entitlement_Service::TYPE_FOUNDING => __( 'Founding Beta member', 'bitmomo-pro' ),
			Bitmomo_Pro_Entitlement_Service::TYPE_STANDARD => __( 'Standard/future member', 'bitmomo-pro' ),
			Bitmomo_Pro_Entitlement_Service::TYPE_TEST      => __( 'Internal/test account', 'bitmomo-pro' ),
			Bitmomo_Pro_Entitlement_Service::TYPE_MANUAL    => __( 'Manual (unspecified)', 'bitmomo-pro' ),
		);
	}

	public function render_fields( $user ) {
		if ( ! $this->can_manage_entitlements() ) {
			return;
		}

		$status     = get_user_meta( $user->ID, self::META_STATUS, true );
		$expires_at = get_user_meta( $user->ID, self::META_EXPIRES_AT, true );
		$started_at = get_user_meta( $user->ID, self::META_STARTED_AT, true );
		$source     = get_user_meta( $user->ID, self::META_SOURCE, true );
		$note       = get_user_meta( $user->ID, self::META_NOTE, true );
		$live_status = Bitmomo_Pro_Entitlement_Service::instance()->get_status( $user->ID );

		if ( '' === $status ) {
			$status = 'inactive';
		}
		if ( '' === $source ) {
			$source = Bitmomo_Pro_Entitlement_Service::TYPE_MANUAL;
		}

		$founding_count = Bitmomo_Pro_Entitlement_Service::instance()->count_active_founding_members();
		$founding_cap   = Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP;
		?>
		<h2><?php esc_html_e( 'Bitmomo Pro', 'bitmomo-pro' ); ?></h2>
		<p>
			<strong><?php esc_html_e( 'Live status:', 'bitmomo-pro' ); ?></strong>
			<?php echo esc_html( ucfirst( $live_status ) ); ?>
			&mdash;
			<?php
			printf(
				/* translators: 1: active founding member count, 2: founding seat cap */
				esc_html__( 'Founding members currently active: %1$d / %2$d', 'bitmomo-pro' ),
				(int) $founding_count,
				(int) $founding_cap
			);
			?>
			<?php if ( $founding_count >= $founding_cap ) : ?>
				<br /><span style="color:#b32d2e;"><?php esc_html_e( 'Founding cap reached — consider carefully before granting another "Founding Beta member" source.', 'bitmomo-pro' ); ?></span>
			<?php endif; ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="bitmomo_pro_status"><?php esc_html_e( 'Status', 'bitmomo-pro' ); ?></label></th>
				<td>
					<select name="bitmomo_pro_status" id="bitmomo_pro_status">
						<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'bitmomo-pro' ); ?></option>
						<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'bitmomo-pro' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="bitmomo_pro_started_at"><?php esc_html_e( 'Access start', 'bitmomo-pro' ); ?></label></th>
				<td><input type="date" name="bitmomo_pro_started_at" id="bitmomo_pro_started_at" value="<?php echo esc_attr( $started_at ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="bitmomo_pro_expires_at"><?php esc_html_e( 'Access expiry', 'bitmomo-pro' ); ?></label></th>
				<td>
					<input type="date" name="bitmomo_pro_expires_at" id="bitmomo_pro_expires_at" value="<?php echo esc_attr( $expires_at ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave blank for no expiry. Not auto-filled — billing period is a manual decision during Founding Beta.', 'bitmomo-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bitmomo_pro_source"><?php esc_html_e( 'Type / source', 'bitmomo-pro' ); ?></label></th>
				<td>
					<select name="bitmomo_pro_source" id="bitmomo_pro_source">
						<?php foreach ( $this->type_labels() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $source, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Operational/accountability label only — does not change pricing or access logic. Use "Internal/test account" for your own test grants so they never count toward the Founding cap above.', 'bitmomo-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bitmomo_pro_note"><?php esc_html_e( 'Internal note', 'bitmomo-pro' ); ?></label></th>
				<td><textarea name="bitmomo_pro_note" id="bitmomo_pro_note" class="regular-text" rows="2"><?php echo esc_textarea( $note ); ?></textarea></td>
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

		if ( ! $this->can_manage_entitlements() ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$status     = ( isset( $_POST['bitmomo_pro_status'] ) && 'active' === $_POST['bitmomo_pro_status'] ) ? 'active' : 'inactive';
		$started_at = isset( $_POST['bitmomo_pro_started_at'] ) ? sanitize_text_field( wp_unslash( $_POST['bitmomo_pro_started_at'] ) ) : '';
		$expires_at = isset( $_POST['bitmomo_pro_expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['bitmomo_pro_expires_at'] ) ) : '';
		$source     = isset( $_POST['bitmomo_pro_source'] ) ? sanitize_text_field( wp_unslash( $_POST['bitmomo_pro_source'] ) ) : Bitmomo_Pro_Entitlement_Service::TYPE_MANUAL;
		$note       = isset( $_POST['bitmomo_pro_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bitmomo_pro_note'] ) ) : '';

		$service = Bitmomo_Pro_Entitlement_Service::instance();

		if ( 'active' === $status ) {
			$service->grant_access(
				$user_id,
				array(
					'source'     => $source,
					'started_at' => $started_at,
					'expires_at' => $expires_at,
					'note'       => $note,
				)
			);
		} else {
			$service->update_meta_fields(
				$user_id,
				array(
					'source'     => $source,
					'started_at' => $started_at,
					'expires_at' => $expires_at,
				)
			);
			$service->revoke_access( $user_id, $note );
		}
	}
}

/**
 * Canonical entitlement READ check. Everything that gates Pro content — the
 * dashboard shortcode, a payment webhook later — must call this one
 * function rather than reading user meta directly. Do not duplicate this
 * logic in templates. Delegates its expiry logic to
 * Bitmomo_Pro_Entitlement_Service::get_status() so there is exactly one
 * implementation of "is this grant still valid".
 *
 * @param int $user_id 0 = current logged-in user.
 * @return bool
 */
function bitmomo_user_has_pro_access( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return false; // Not logged in.
	}

	return 'active' === Bitmomo_Pro_Entitlement_Service::instance()->get_status( $user_id );
}

