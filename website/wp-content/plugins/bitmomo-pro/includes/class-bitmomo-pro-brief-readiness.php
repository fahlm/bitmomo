<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Release-readiness and freshness gate for bm_pro_brief.
 *
 * Two distinct, deliberately separate concepts:
 * - READINESS: is this brief's content complete/valid enough to ever be
 *   shown as the current Pro product (evaluate() / evaluate_post()).
 * - FRESHNESS: given a ready brief, how old is it — fresh / delayed /
 *   unavailable (compute_freshness_tier()).
 *
 * This class enforces readiness at publish time (a brief that fails
 * readiness cannot become/stay the published, subscriber-facing post — it
 * is reverted to draft, nothing is deleted) and exposes both checks for
 * Bitmomo_Pro_Briefs::get_current_brief_for_display(), which the dashboard
 * shortcode uses instead of trusting "latest published" blindly.
 */
class Bitmomo_Pro_Brief_Readiness {

	const TIER_FRESH       = 'fresh';
	const TIER_DELAYED     = 'delayed';
	const TIER_UNAVAILABLE = 'unavailable';

	const STATE_READY              = 'siap_dirilis';
	const STATE_NOT_READY          = 'belum_siap';
	const STATE_DELAYED            = 'data_terlambat';
	const STATE_UNAVAILABLE        = 'data_tidak_layak';

	const NOT_READY_TRANSIENT_PREFIX = 'bitmomo_pro_brief_not_ready_';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Priority 20: after Bitmomo_Pro_Briefs::save_meta() (priority 10,
		// the default) has already written the submitted meta, so the
		// values we evaluate here reflect what was just saved.
		add_action( 'save_post_' . Bitmomo_Pro_Briefs::POST_TYPE, array( $this, 'enforce_release_gate' ), 20, 3 );
		add_filter( 'redirect_post_location', array( $this, 'append_notice_query_arg' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_not_ready_notice' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_readiness_meta_box' ) );
	}

	/**
	 * Evaluates a raw field array (same keys as the meta box fields,
	 * '_bitmomo_pro_' prefix stripped) against the V1 required-field list.
	 * Pure function — no WordPress DB access — so it's directly unit
	 * testable. See tests/test-bitmomo-pro-brief-readiness.php.
	 *
	 * @return array{ready:bool,issues:string[]}
	 */
	public function evaluate( $fields ) {
		$fields = is_array( $fields ) ? $fields : array();
		$issues = array();

		$market_state = isset( $fields['market_state'] ) ? $fields['market_state'] : '';
		if ( '' === $market_state || ! in_array( $market_state, Bitmomo_Pro_Briefs::MARKET_STATES, true ) ) {
			$issues[] = __( 'Market State tidak valid.', 'bitmomo-pro' );
		}

		$price = isset( $fields['btc_reference_price'] ) ? $fields['btc_reference_price'] : '';
		if ( '' === $price || ! is_numeric( $price ) || floatval( $price ) <= 0 ) {
			$issues[] = __( 'BTC Reference Price harus lebih besar dari 0.', 'bitmomo-pro' );
		}

		$confidence = isset( $fields['confidence'] ) ? $fields['confidence'] : '';
		if ( '' === $confidence || ! is_numeric( $confidence ) || floatval( $confidence ) < 0 || floatval( $confidence ) > 100 ) {
			$issues[] = __( 'Confidence harus berupa angka 0-100.', 'bitmomo-pro' );
		}

		$low  = isset( $fields['expected_range_low'] ) ? $fields['expected_range_low'] : '';
		$high = isset( $fields['expected_range_high'] ) ? $fields['expected_range_high'] : '';
		if ( '' === $low || ! is_numeric( $low ) || floatval( $low ) <= 0 ) {
			$issues[] = __( 'Expected Range Low harus lebih besar dari 0.', 'bitmomo-pro' );
		}
		if ( '' === $high || ! is_numeric( $high ) ) {
			$issues[] = __( 'Expected Range High harus berupa angka.', 'bitmomo-pro' );
		} elseif ( is_numeric( $low ) && floatval( $high ) < floatval( $low ) ) {
			$issues[] = __( 'Expected Range High harus >= Expected Range Low.', 'bitmomo-pro' );
		}

		$required_text = array(
			'confidence_explanation' => __( 'Confidence Explanation', 'bitmomo-pro' ),
			'base_scenario'          => __( 'Base Scenario', 'bitmomo-pro' ),
			'bull_scenario'          => __( 'Bull Scenario', 'bitmomo-pro' ),
			'bear_scenario'          => __( 'Bear Scenario', 'bitmomo-pro' ),
			'invalidation'           => __( 'Thesis Invalidation', 'bitmomo-pro' ),
			'what_changed'           => __( 'What Changed', 'bitmomo-pro' ),
		);
		foreach ( $required_text as $key => $label ) {
			$value = isset( $fields[ $key ] ) ? trim( (string) $fields[ $key ] ) : '';
			if ( '' === $value ) {
				/* translators: %s: field label */
				$issues[] = sprintf( __( '%s tidak boleh kosong.', 'bitmomo-pro' ), $label );
			}
		}

		$timestamp = isset( $fields['data_timestamp'] ) ? $fields['data_timestamp'] : '';
		if ( '' === $timestamp || false === strtotime( (string) $timestamp ) ) {
			$issues[] = __( 'Data Timestamp tidak valid.', 'bitmomo-pro' );
		}

		$freshness_status = isset( $fields['data_freshness_status'] ) ? $fields['data_freshness_status'] : '';
		if ( '' === $freshness_status || ! in_array( $freshness_status, Bitmomo_Pro_Briefs::FRESHNESS_STATES, true ) ) {
			$issues[] = __( 'Data Quality / Freshness tidak valid.', 'bitmomo-pro' );
		}

		return array(
			'ready'  => empty( $issues ),
			'issues' => $issues,
		);
	}

	/**
	 * Same as evaluate(), reading the fields straight from post meta.
	 */
	public function evaluate_post( $post_id ) {
		$fields = array();
		// Reads the canonical V1 field-key list from Bitmomo_Pro_Briefs (PR #36
		// integration audit) instead of a separately maintained copy — extra
		// keys not used by evaluate() (e.g. source_record_id) are harmless here.
		foreach ( Bitmomo_Pro_Briefs::field_keys() as $key ) {
			$fields[ $key ] = get_post_meta( $post_id, '_bitmomo_pro_' . $key, true );
		}
		return $this->evaluate( $fields );
	}

	/**
	 * Pure function: age of $data_timestamp against 'now', combined with
	 * the editor's own explicit freshness status. An explicit
	 * 'unavailable' always wins regardless of age. Otherwise:
	 * <=3h fresh, <=24h delayed, >24h (or unparseable) unavailable.
	 */
	public function compute_freshness_tier( $data_timestamp, $explicit_status ) {
		if ( 'unavailable' === $explicit_status ) {
			return self::TIER_UNAVAILABLE;
		}

		$ts = strtotime( (string) $data_timestamp );
		if ( false === $ts ) {
			return self::TIER_UNAVAILABLE;
		}

		$age_hours = ( current_time( 'timestamp' ) - $ts ) / HOUR_IN_SECONDS;

		if ( $age_hours < 0 ) {
			// A timestamp in the future is not something we trust either.
			return self::TIER_UNAVAILABLE;
		}
		if ( $age_hours <= 3 ) {
			return self::TIER_FRESH;
		}
		if ( $age_hours <= 24 ) {
			return self::TIER_DELAYED;
		}
		return self::TIER_UNAVAILABLE;
	}

	/**
	 * "Diperbarui N jam/menit lalu" — used only for the delayed tier.
	 */
	public function format_age_label( $data_timestamp ) {
		$ts = strtotime( (string) $data_timestamp );
		if ( false === $ts ) {
			return '';
		}

		$diff = max( 0, current_time( 'timestamp' ) - $ts );
		$hours = (int) floor( $diff / HOUR_IN_SECONDS );

		if ( $hours < 1 ) {
			$minutes = max( 1, (int) floor( $diff / MINUTE_IN_SECONDS ) );
			/* translators: %d: minutes */
			return sprintf( _n( 'Diperbarui %d menit lalu', 'Diperbarui %d menit lalu', $minutes, 'bitmomo-pro' ), $minutes );
		}

		/* translators: %d: hours */
		return sprintf( _n( 'Diperbarui %d jam lalu', 'Diperbarui %d jam lalu', $hours, 'bitmomo-pro' ), $hours );
	}

	/**
	 * Combined admin-facing state for the readiness meta box: one of the
	 * four labels the task specifies, plus the specific issues (only
	 * populated for the "not ready" state).
	 */
	public function admin_state_for_post( $post_id ) {
		$eval = $this->evaluate_post( $post_id );
		if ( ! $eval['ready'] ) {
			return array(
				'state'  => self::STATE_NOT_READY,
				'issues' => $eval['issues'],
			);
		}

		$data_timestamp   = get_post_meta( $post_id, '_bitmomo_pro_data_timestamp', true );
		$explicit_status  = get_post_meta( $post_id, '_bitmomo_pro_data_freshness_status', true );
		$tier             = $this->compute_freshness_tier( $data_timestamp, $explicit_status );

		if ( self::TIER_UNAVAILABLE === $tier ) {
			return array( 'state' => self::STATE_UNAVAILABLE, 'issues' => array() );
		}
		if ( self::TIER_DELAYED === $tier ) {
			return array( 'state' => self::STATE_DELAYED, 'issues' => array() );
		}
		return array( 'state' => self::STATE_READY, 'issues' => array() );
	}

	/**
	 * The publish-time gate. Runs after Bitmomo_Pro_Briefs::save_meta()
	 * has already persisted the submitted fields. If the post is (or was
	 * just set to) 'publish' but fails readiness, it is reverted to
	 * 'draft' — content and all meta are left completely intact, nothing
	 * is deleted, and the editor sees exactly why on their next page load.
	 */
	public function enforce_release_gate( $post_id, $post, $update ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$eval = $this->evaluate_post( $post_id );
		if ( $eval['ready'] ) {
			return; // Complete enough to stay published.
		}

		// Revert to draft without re-triggering this same check via the
		// wp_update_post() call below (which itself fires save_post).
		remove_action( 'save_post_' . Bitmomo_Pro_Briefs::POST_TYPE, array( $this, 'enforce_release_gate' ), 20 );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		add_action( 'save_post_' . Bitmomo_Pro_Briefs::POST_TYPE, array( $this, 'enforce_release_gate' ), 20, 3 );

		set_transient( self::NOT_READY_TRANSIENT_PREFIX . $post_id, $eval['issues'], 5 * MINUTE_IN_SECONDS );
	}

	public function append_notice_query_arg( $location, $post_id ) {
		if ( Bitmomo_Pro_Briefs::POST_TYPE !== get_post_type( $post_id ) ) {
			return $location;
		}
		if ( false !== get_transient( self::NOT_READY_TRANSIENT_PREFIX . $post_id ) ) {
			$location = add_query_arg( 'bitmomo_pro_not_ready', $post_id, $location );
		}
		return $location;
	}

	public function render_not_ready_notice() {
		if ( ! isset( $_GET['bitmomo_pro_not_ready'] ) ) {
			return;
		}

		$post_id = absint( $_GET['bitmomo_pro_not_ready'] );
		$issues  = get_transient( self::NOT_READY_TRANSIENT_PREFIX . $post_id );
		if ( false === $issues ) {
			return;
		}
		delete_transient( self::NOT_READY_TRANSIENT_PREFIX . $post_id );

		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Brief belum siap dirilis — dikembalikan ke draft. Isi yang sudah kamu tulis tidak hilang.', 'bitmomo-pro' ) . '</strong></p><ul style="margin-left:20px;list-style:disc;">';
		foreach ( (array) $issues as $issue ) {
			echo '<li>' . esc_html( $issue ) . '</li>';
		}
		echo '</ul></div>';
	}

	public function add_readiness_meta_box() {
		add_meta_box(
			'bitmomo_pro_brief_readiness',
			__( 'Status Rilis', 'bitmomo-pro' ),
			array( $this, 'render_readiness_meta_box' ),
			Bitmomo_Pro_Briefs::POST_TYPE,
			'side',
			'high'
		);
	}

	public function render_readiness_meta_box( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Simpan draft untuk melihat status kesiapan.', 'bitmomo-pro' ) . '</p>';
			return;
		}

		$result = $this->admin_state_for_post( $post->ID );

		$labels = array(
			self::STATE_READY       => __( 'SIAP DIRILIS', 'bitmomo-pro' ),
			self::STATE_NOT_READY   => __( 'BELUM SIAP', 'bitmomo-pro' ),
			self::STATE_DELAYED     => __( 'DATA TERLAMBAT', 'bitmomo-pro' ),
			self::STATE_UNAVAILABLE => __( 'DATA TIDAK LAYAK DITAMPILKAN', 'bitmomo-pro' ),
		);
		$colors = array(
			self::STATE_READY       => '#1a7f4b',
			self::STATE_NOT_READY   => '#b32d2e',
			self::STATE_DELAYED     => '#a16207',
			self::STATE_UNAVAILABLE => '#b32d2e',
		);

		printf(
			'<p style="font-weight:700;font-size:13px;color:%1$s;">%2$s</p>',
			esc_attr( $colors[ $result['state'] ] ),
			esc_html( $labels[ $result['state'] ] )
		);

		if ( ! empty( $result['issues'] ) ) {
			echo '<ul style="margin-left:16px;list-style:disc;font-size:12px;">';
			foreach ( $result['issues'] as $issue ) {
				echo '<li>' . esc_html( $issue ) . '</li>';
			}
			echo '</ul>';
		}

		echo '<p class="description" style="font-size:11px;">' . esc_html__( 'Ini hanya menentukan apa yang tampil sebagai brief hari ini. Brief lama tetap tersimpan di riwayat.', 'bitmomo-pro' ) . '</p>';
	}
}
