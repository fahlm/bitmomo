<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical entitlement WRITE boundary.
 *
 * bitmomo_user_has_pro_access() (defined in
 * includes/class-bitmomo-pro-entitlements.php) remains the canonical READ
 * gate — nothing about that function's public contract changes here, it now
 * simply delegates its expiry logic to self::get_status() so there is one
 * implementation of "is this grant still valid" instead of two.
 *
 * Every entitlement WRITE — the admin profile form today, a future payment
 * webhook, a future MailPoet/Telegram integration — should go through this
 * class instead of calling update_user_meta() directly. This is what makes
 * a future payment integration a small addition instead of a rewrite.
 */
class Bitmomo_Pro_Entitlement_Service {

	const TYPE_FOUNDING = 'founding';
	const TYPE_STANDARD = 'standard';
	const TYPE_TEST      = 'test';
	const TYPE_MANUAL    = 'manual';

	const TYPES = array( self::TYPE_FOUNDING, self::TYPE_STANDARD, self::TYPE_TEST, self::TYPE_MANUAL );

	const FOUNDING_SEAT_CAP = 25;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Grants (or re-grants) active access. Always sets status to active.
	 * Fires 'bitmomo_pro_activated' with ( $user_id, $context ).
	 *
	 * @param int   $user_id
	 * @param array $args {
	 *     @type string $source     One of self::TYPES. Defaults to 'manual'.
	 *     @type string $started_at YYYY-MM-DD. Defaults to today.
	 *     @type string $expires_at YYYY-MM-DD or '' for no expiry.
	 *     @type string $note       Internal note, optional.
	 * }
	 */
	public function grant_access( $user_id, $args = array() ) {
		if ( ! $user_id ) {
			return false;
		}

		$source     = $this->sanitize_type( isset( $args['source'] ) ? $args['source'] : self::TYPE_MANUAL );
		$started_at = $this->sanitize_date( isset( $args['started_at'] ) ? $args['started_at'] : '' );
		if ( '' === $started_at ) {
			$started_at = current_time( 'Y-m-d' );
		}
		$expires_at = $this->sanitize_date( isset( $args['expires_at'] ) ? $args['expires_at'] : '' );

		update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STATUS, 'active' );
		update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STARTED_AT, $started_at );
		update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_EXPIRES_AT, $expires_at );
		update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_SOURCE, $source );
		if ( isset( $args['note'] ) ) {
			update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_NOTE, sanitize_textarea_field( $args['note'] ) );
		}

		/**
		 * Fires when a user is granted (or re-granted) active Pro access.
		 *
		 * @param int   $user_id
		 * @param array $context { source, started_at, expires_at }
		 */
		do_action( 'bitmomo_pro_activated', $user_id, compact( 'source', 'started_at', 'expires_at' ) );

		return true;
	}

	/**
	 * Updates metadata fields without necessarily changing status. Used by
	 * the admin form when it saves source/dates while leaving status
	 * untouched (e.g. editing a record that is being kept inactive).
	 */
	public function update_meta_fields( $user_id, $args = array() ) {
		if ( ! $user_id ) {
			return false;
		}

		if ( isset( $args['source'] ) ) {
			update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_SOURCE, $this->sanitize_type( $args['source'] ) );
		}
		if ( isset( $args['started_at'] ) ) {
			update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STARTED_AT, $this->sanitize_date( $args['started_at'] ) );
		}
		if ( isset( $args['expires_at'] ) ) {
			update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_EXPIRES_AT, $this->sanitize_date( $args['expires_at'] ) );
		}

		return true;
	}

	/**
	 * Extends (or sets) the expiry date and ensures status is active.
	 * Fires 'bitmomo_pro_extended' with ( $user_id, $new_expires_at ).
	 *
	 * @param int    $user_id
	 * @param string $new_expires_at YYYY-MM-DD, or '' to clear expiry (no expiry).
	 */
	public function extend_access( $user_id, $new_expires_at ) {
		if ( ! $user_id ) {
			return false;
		}

		$new_expires_at = $this->sanitize_date( $new_expires_at );

		update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STATUS, 'active' );
		update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_EXPIRES_AT, $new_expires_at );

		do_action( 'bitmomo_pro_extended', $user_id, $new_expires_at );

		return true;
	}

	/**
	 * Revokes access immediately (founder-initiated). Fires
	 * 'bitmomo_pro_revoked' with ( $user_id, $note ).
	 */
	public function revoke_access( $user_id, $note = '' ) {
		if ( ! $user_id ) {
			return false;
		}

		update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STATUS, 'inactive' );
		if ( '' !== $note ) {
			update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_NOTE, sanitize_textarea_field( $note ) );
		}

		do_action( 'bitmomo_pro_revoked', $user_id, $note );

		return true;
	}

	/**
	 * Formally closes out a grant whose expiry date has passed, syncing
	 * stored status to what get_status() already reports live. Nothing in
	 * this plugin calls this automatically (no cron is registered — that
	 * would be state-change automation, out of scope for this PR). It
	 * exists for a future scheduled job or payment-provider webhook to
	 * call explicitly. Until something calls it, an expired grant's status
	 * meta stays 'active' but get_status()/bitmomo_user_has_pro_access()
	 * already correctly treat it as not-active via the live date check.
	 * Fires 'bitmomo_pro_expired' with ( $user_id ).
	 */
	public function expire_access( $user_id ) {
		if ( ! $user_id ) {
			return false;
		}

		update_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STATUS, 'inactive' );

		do_action( 'bitmomo_pro_expired', $user_id );

		return true;
	}

	/**
	 * The single, live source of truth for "is this grant valid right now".
	 * Never trusts stale status meta alone — always re-checks expiry.
	 *
	 * @return string One of 'active', 'expired', 'inactive'.
	 */
	public function get_status( $user_id ) {
		if ( ! $user_id ) {
			return 'inactive';
		}

		$status = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STATUS, true );
		if ( 'active' !== $status ) {
			return 'inactive';
		}

		$expires_at = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_EXPIRES_AT, true );
		if ( ! empty( $expires_at ) ) {
			$expires_ts = strtotime( $expires_at . ' 23:59:59' );
			if ( false !== $expires_ts && current_time( 'timestamp' ) > $expires_ts ) {
				return 'expired';
			}
		}

		return 'active';
	}

	/**
	 * Live count of Founding Beta members currently active (source =
	 * 'founding' and get_status() === 'active'). Test/internal accounts
	 * (source = 'test') never count toward this, by construction.
	 *
	 * Deliberately a simple query suitable for ~dozens of users, not a
	 * cached/optimized metric — this is an operational number for the
	 * founder, not a dashboard.
	 */
	public function count_active_founding_members() {
		$users = get_users(
			array(
				'meta_key'   => Bitmomo_Pro_Entitlements::META_SOURCE,
				'meta_value' => self::TYPE_FOUNDING,
				'fields'     => 'ID',
			)
		);

		$count = 0;
		foreach ( $users as $user_id ) {
			if ( 'active' === $this->get_status( $user_id ) ) {
				$count++;
			}
		}

		return $count;
	}

	private function sanitize_type( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, self::TYPES, true ) ? $value : self::TYPE_MANUAL;
	}

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

