<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal operational visibility on the normal wp-admin Users list —
 * enough to run the first ~25 customers without a separate CRM.
 *
 * Adds four columns (Pro, Started, Expires, Source) and a simple
 * All / Active Pro / Expired-or-Inactive filter. No charts, no MRR, no
 * cohort system — just enough for the founder to see membership state at
 * a glance on a screen WordPress already gives them.
 */
class Bitmomo_Pro_Users_List {

	const FILTER_PARAM = 'bitmomo_pro_filter';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'manage_users_columns', array( $this, 'add_columns' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_column' ), 10, 3 );
		add_action( 'restrict_manage_users', array( $this, 'render_filter_dropdown' ) );
		add_action( 'pre_get_users', array( $this, 'apply_filter_to_query' ) );
	}

	public function add_columns( $columns ) {
		$columns['bitmomo_pro_status']  = __( 'Pro', 'bitmomo-pro' );
		$columns['bitmomo_pro_started'] = __( 'Started', 'bitmomo-pro' );
		$columns['bitmomo_pro_expires'] = __( 'Expires', 'bitmomo-pro' );
		$columns['bitmomo_pro_source']  = __( 'Source', 'bitmomo-pro' );
		return $columns;
	}

	public function render_column( $output, $column_name, $user_id ) {
		switch ( $column_name ) {
			case 'bitmomo_pro_status':
				$status = Bitmomo_Pro_Entitlement_Service::instance()->get_status( $user_id );
				return esc_html( ucfirst( $status ) );

			case 'bitmomo_pro_started':
				$value = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STARTED_AT, true );
				return $value ? esc_html( $value ) : '&#8212;';

			case 'bitmomo_pro_expires':
				$value = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_EXPIRES_AT, true );
				return $value ? esc_html( $value ) : esc_html__( 'No expiry', 'bitmomo-pro' );

			case 'bitmomo_pro_source':
				$value = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_SOURCE, true );
				return $value ? esc_html( $value ) : '&#8212;';
		}

		return $output;
	}

	public function render_filter_dropdown( $which ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current = isset( $_GET[ self::FILTER_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::FILTER_PARAM ] ) ) : '';
		$founding_count = Bitmomo_Pro_Entitlement_Service::instance()->count_active_founding_members();
		$founding_cap   = Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP;

		printf(
			'<span style="margin:0 8px;display:inline-block;">%s</span>',
			esc_html(
				sprintf(
					/* translators: 1: active founding member count, 2: founding seat cap */
					__( 'Founding members: %1$d / %2$d', 'bitmomo-pro' ),
					$founding_count,
					$founding_cap
				)
			)
		);
		?>
		<select name="<?php echo esc_attr( self::FILTER_PARAM ); ?>">
			<option value="" <?php selected( $current, '' ); ?>><?php esc_html_e( 'All Bitmomo Pro states', 'bitmomo-pro' ); ?></option>
			<option value="active" <?php selected( $current, 'active' ); ?>><?php esc_html_e( 'Active Pro', 'bitmomo-pro' ); ?></option>
			<option value="expired_inactive" <?php selected( $current, 'expired_inactive' ); ?>><?php esc_html_e( 'Expired / Inactive', 'bitmomo-pro' ); ?></option>
		</select>
		<?php
		submit_button( __( 'Filter', 'bitmomo-pro' ), '', 'filter_action', false );
	}

	public function apply_filter_to_query( $query ) {
		if ( ! is_a( $query, 'WP_User_Query' ) ) {
			return;
		}

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$filter = isset( $_GET[ self::FILTER_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::FILTER_PARAM ] ) ) : '';
		if ( '' === $filter ) {
			return;
		}

		$today = current_time( 'Y-m-d' );

		if ( 'active' === $filter ) {
			$query->set(
				'meta_query',
				array(
					'relation' => 'AND',
					array(
						'key'   => Bitmomo_Pro_Entitlements::META_STATUS,
						'value' => 'active',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => Bitmomo_Pro_Entitlements::META_EXPIRES_AT,
							'value'   => '',
							'compare' => '=',
						),
						array(
							'key'     => Bitmomo_Pro_Entitlements::META_EXPIRES_AT,
							'value'   => $today,
							'compare' => '>=',
							'type'    => 'DATE',
						),
					),
				)
			);
		} elseif ( 'expired_inactive' === $filter ) {
			// Approximation, deliberately simple for ~dozens of users: users
			// who have some entitlement history that is currently not a
			// valid active grant. Users who were never granted access at
			// all (no meta row) do not appear here — this filter is about
			// customers whose access lapsed or was turned off, not about
			// everyone who has never been a customer.
			$query->set(
				'meta_query',
				array(
					'relation' => 'AND',
					array(
						'key'     => Bitmomo_Pro_Entitlements::META_STATUS,
						'compare' => 'EXISTS',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => Bitmomo_Pro_Entitlements::META_STATUS,
							'value'   => 'active',
							'compare' => '!=',
						),
						array(
							'key'     => Bitmomo_Pro_Entitlements::META_EXPIRES_AT,
							'value'   => $today,
							'compare' => '<',
							'type'    => 'DATE',
						),
					),
				)
			);
		}
	}
}
