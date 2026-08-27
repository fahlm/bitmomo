<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bitmomo Pro entitlement model.
 *
 * Storage: plain WordPress user meta on standard WP users. No custom
 * authentication system, no custom tables. This is intentionally the ONLY
 * place entitlement is written from (the admin profile screen below) or
 * read against (bitmomo_user_has_pro_access(), at the bottom of this file).
 * A future payment webhook should write these same meta keys and call the
 * same helper — not invent a parallel access check.
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

	public function render_fields( $user ) {
		if ( ! $this->can_manage_entitlements() ) {
			return;
		}

		$status     = get_user_meta( $user->ID, self::META_STATUS, true );
		$expires_at = get_user_meta( $user->ID, self::META_EXPIRES_AT, true );
		$started_at = get_user_meta( $user->ID, self::META_STARTED_AT, true );
		$source     = get_user_meta( $user->ID, self::META_SOURCE, true );
		$note       = get_user_meta( $user->ID, self::META_NOTE, true );

		if ( '' === $status ) {
			$status = 'inactive';
		}
		if ( '' === $source ) {
			$source = 'manual';
		}
		?>
		<h2><?php esc_html_e( 'Bitmomo Pro', 'bitmomo-pro' ); ?></h2>
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
					<p class="description"><?php esc_html_e( 'Leave blank for no expiry.', 'bitmomo-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bitmomo_pro_source"><?php esc_html_e( 'Source', 'bitmomo-pro' ); ?></label></th>
				<td>
					<input type="text" name="bitmomo_pro_source" id="bitmomo_pro_source" value="<?php echo esc_attr( $source ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( '"manual" for founder-granted access. A future payment provider can write its own identifier here.', 'bitmomo-pro' ); ?></p>
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

		$status = ( isset( $_POST['bitmomo_pro_status'] ) && 'active' === $_POST['bitmomo_pro_status'] ) ? 'active' : 'inactive';
		update_user_meta( $user_id, self::META_STATUS, $status );

		$started_at = isset( $_POST['bitmomo_pro_started_at'] ) ? sanitize_text_field( wp_unslash( $_POST['bitmomo_pro_started_at'] ) ) : '';
		$started_at = $this->sanitize_date( $started_at );
		if ( 'active' === $status && '' === $started_at ) {
			$started_at = current_time( 'Y-m-d' );
		}
		update_user_meta( $user_id, self::META_STARTED_AT, $started_at );

		$expires_at = isset( $_POST['bitmomo_pro_expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['bitmomo_pro_expires_at'] ) ) : '';
		update_user_meta( $user_id, self::META_EXPIRES_AT, $this->sanitize_date( $expires_at ) );

		$source = isset( $_POST['bitmomo_pro_source'] ) ? sanitize_text_field( wp_unslash( $_POST['bitmomo_pro_source'] ) ) : 'manual';
		update_user_meta( $user_id, self::META_SOURCE, '' !== $source ? $source : 'manual' );

		$note = isset( $_POST['bitmomo_pro_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bitmomo_pro_note'] ) ) : '';
		update_user_meta( $user_id, self::META_NOTE, $note );
	}

	/**
	 * Accepts only a strict YYYY-MM-DD date or an empty string. Anything
	 * else is discarded rather than stored, so a malformed value can never
	 * silently break the expiry comparison in bitmomo_user_has_pro_access().
	 */
	private function sanitize_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}
		return $value;
	}
}

/**
 * Canonical entitlement check. Everything that gates Pro content — the
 * dashboard shortcode today, a payment webhook later — must call this one
 * function rather than reading user meta directly. Do not duplicate this
 * logic in templates.
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

	$status = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STATUS, true );
	if ( 'active' !== $status ) {
		return false;
	}

	$expires_at = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_EXPIRES_AT, true );
	if ( ! empty( $expires_at ) ) {
		$expires_ts = strtotime( $expires_at . ' 23:59:59' );
		if ( false !== $expires_ts && current_time( 'timestamp' ) > $expires_ts ) {
			return false; // Expiry date has fully elapsed.
		}
	}

	return true;
}
