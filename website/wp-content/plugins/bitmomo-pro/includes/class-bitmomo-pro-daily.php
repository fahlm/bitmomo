<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Daily Pro" — a founder/editor orchestration screen around the services
 * that already exist (Brief Prefill, Brief Readiness, Briefs, Email
 * Service). This class does not replace any of them and does not
 * introduce a second readiness or freshness definition — it only reads
 * their existing state and, where the task calls for it, links to their
 * existing actions.
 *
 * Goal: let the founder see today's product state and get from canonical
 * source -> publishable Pro brief with minimum friction, without ever
 * fabricating data or bypassing a safety gate.
 */
class Bitmomo_Pro_Daily {

	const APPLY_SUGGESTION_ACTION       = 'bitmomo_pro_apply_what_changed_suggestion';
	const APPLY_SUGGESTION_NONCE_ACTION = 'bitmomo_pro_apply_what_changed';
	const APPLY_SUGGESTION_NONCE_FIELD  = 'bitmomo_pro_apply_what_changed_nonce';

	/**
	 * The closed V1 field set, same keys used throughout the plugin
	 * (Bitmomo_Pro_Brief_Readiness::evaluate_post(), the meta box). Kept
	 * here as a single list rather than re-deriving it, since
	 * Bitmomo_Pro_Briefs::fields() is private to that class.
	 */
	const FIELD_KEYS = array(
		'market_state',
		'btc_reference_price',
		'confidence',
		'confidence_explanation',
		'expected_range_low',
		'expected_range_high',
		'base_scenario',
		'bull_scenario',
		'bear_scenario',
		'invalidation',
		'what_changed',
		'data_timestamp',
		'data_freshness_status',
		'source_record_id',
	);

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_' . self::APPLY_SUGGESTION_ACTION, array( $this, 'handle_apply_suggestion' ) );
		add_action( 'admin_notices', array( $this, 'render_apply_suggestion_notice' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE,
			__( 'Daily Pro', 'bitmomo-pro' ),
			__( 'Daily Pro', 'bitmomo-pro' ),
			'edit_posts',
			'bitmomo-pro-daily',
			array( $this, 'render_page' )
		);
	}

	// ==================================================================
	// DATA HELPERS (read-only; no fabrication — a null stays null)
	// ==================================================================

	private function read_fields( $post_id ) {
		$fields = array();
		foreach ( self::FIELD_KEYS as $key ) {
			$fields[ $key ] = get_post_meta( $post_id, '_bitmomo_pro_' . $key, true );
		}
		return $fields;
	}

	/**
	 * The most recent bm_pro_brief post of ANY status (draft, pending,
	 * publish, future) — "today's" item, whatever stage it's at.
	 */
	private function get_current_post() {
		$posts = get_posts(
			array(
				'post_type'      => Bitmomo_Pro_Briefs::POST_TYPE,
				'post_status'    => array( 'draft', 'pending', 'publish', 'future' ),
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * The most recent PUBLISHED brief, excluding a given post ID — i.e.
	 * "what subscribers are currently seeing" (or were seeing, before
	 * today's draft), used as the comparison baseline.
	 */
	private function get_previous_published_post( $exclude_id = 0 ) {
		$posts = get_posts(
			array(
				'post_type'      => Bitmomo_Pro_Briefs::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'exclude'        => $exclude_id ? array( $exclude_id ) : array(),
			)
		);
		return ! empty( $posts ) ? $posts[0] : null;
	}

	private function market_state_labels() {
		return array(
			'bullish' => __( 'Bullish', 'bitmomo-pro' ),
			'neutral' => __( 'Neutral', 'bitmomo-pro' ),
			'bearish' => __( 'Bearish', 'bitmomo-pro' ),
		);
	}

	// ==================================================================
	// C. DETERMINISTIC COMPARISON ENGINE — no LLM, no trading advice
	// ==================================================================

	/**
	 * Pure function: compares two field arrays (same shape as read_fields())
	 * and returns structured facts only. Never invents a value neither
	 * side actually has.
	 */
	public function compare_briefs( $previous, $current ) {
		$comparison = array();

		$comparison['market_state'] = array(
			'old'     => isset( $previous['market_state'] ) ? $previous['market_state'] : '',
			'new'     => isset( $current['market_state'] ) ? $current['market_state'] : '',
			'changed' => ( isset( $previous['market_state'], $current['market_state'] ) && $previous['market_state'] !== $current['market_state'] ),
		);

		$old_conf = isset( $previous['confidence'] ) && is_numeric( $previous['confidence'] ) ? floatval( $previous['confidence'] ) : null;
		$new_conf = isset( $current['confidence'] ) && is_numeric( $current['confidence'] ) ? floatval( $current['confidence'] ) : null;
		$comparison['confidence'] = array(
			'old'   => $old_conf,
			'new'   => $new_conf,
			'delta' => ( null !== $old_conf && null !== $new_conf ) ? ( $new_conf - $old_conf ) : null,
		);

		$old_low  = isset( $previous['expected_range_low'] ) && is_numeric( $previous['expected_range_low'] ) ? floatval( $previous['expected_range_low'] ) : null;
		$old_high = isset( $previous['expected_range_high'] ) && is_numeric( $previous['expected_range_high'] ) ? floatval( $previous['expected_range_high'] ) : null;
		$new_low  = isset( $current['expected_range_low'] ) && is_numeric( $current['expected_range_low'] ) ? floatval( $current['expected_range_low'] ) : null;
		$new_high = isset( $current['expected_range_high'] ) && is_numeric( $current['expected_range_high'] ) ? floatval( $current['expected_range_high'] ) : null;
		$comparison['expected_range'] = array(
			'old_low'  => $old_low,
			'old_high' => $old_high,
			'new_low'  => $new_low,
			'new_high' => $new_high,
			'changed'  => ( null !== $old_low && null !== $new_low && ( $old_low !== $new_low || $old_high !== $new_high ) ),
		);

		$old_price = isset( $previous['btc_reference_price'] ) && is_numeric( $previous['btc_reference_price'] ) ? floatval( $previous['btc_reference_price'] ) : null;
		$new_price = isset( $current['btc_reference_price'] ) && is_numeric( $current['btc_reference_price'] ) ? floatval( $current['btc_reference_price'] ) : null;
		$comparison['reference_price'] = array(
			'old'        => $old_price,
			'new'        => $new_price,
			'delta'      => ( null !== $old_price && null !== $new_price ) ? ( $new_price - $old_price ) : null,
			'pct_change' => ( null !== $old_price && null !== $new_price && 0.0 !== $old_price ) ? ( ( $new_price - $old_price ) / $old_price * 100 ) : null,
		);

		$old_invalidation = isset( $previous['invalidation'] ) ? trim( (string) $previous['invalidation'] ) : '';
		$new_invalidation = isset( $current['invalidation'] ) ? trim( (string) $current['invalidation'] ) : '';
		$comparison['invalidation'] = array(
			'changed' => ( $old_invalidation !== $new_invalidation ),
		);

		return $comparison;
	}

	/**
	 * D. Deterministic "What Changed" editorial SUGGESTION — plain string
	 * templating only, no LLM call, no trading recommendation. Always a
	 * suggestion the founder reviews; never auto-saved by this method.
	 */
	public function build_what_changed_suggestion( $comparison ) {
		$labels    = $this->market_state_labels();
		$sentences = array();

		$ms = $comparison['market_state'];
		if ( $ms['changed'] && '' !== $ms['old'] && '' !== $ms['new'] ) {
			$sentences[] = sprintf(
				/* translators: 1: old market state label, 2: new market state label */
				__( 'Bias berubah dari %1$s menjadi %2$s.', 'bitmomo-pro' ),
				isset( $labels[ $ms['old'] ] ) ? $labels[ $ms['old'] ] : $ms['old'],
				isset( $labels[ $ms['new'] ] ) ? $labels[ $ms['new'] ] : $ms['new']
			);
		} elseif ( '' !== $ms['new'] ) {
			$sentences[] = sprintf(
				/* translators: %s: market state label */
				__( 'Bias tetap %s.', 'bitmomo-pro' ),
				isset( $labels[ $ms['new'] ] ) ? $labels[ $ms['new'] ] : $ms['new']
			);
		}

		$conf = $comparison['confidence'];
		if ( null !== $conf['delta'] ) {
			if ( 0.0 === $conf['delta'] ) {
				$sentences[] = sprintf(
					/* translators: %d: confidence percentage */
					__( 'Confidence tidak berubah (%d%%).', 'bitmomo-pro' ),
					(int) round( $conf['new'] )
				);
			} else {
				$sentences[] = sprintf(
					/* translators: 1: naik/turun, 2: delta in points, 3: old %, 4: new % */
					__( 'Confidence %1$s %2$d poin (dari %3$d%% ke %4$d%%).', 'bitmomo-pro' ),
					$conf['delta'] > 0 ? __( 'naik', 'bitmomo-pro' ) : __( 'turun', 'bitmomo-pro' ),
					(int) round( abs( $conf['delta'] ) ),
					(int) round( $conf['old'] ),
					(int) round( $conf['new'] )
				);
			}
		}

		$range = $comparison['expected_range'];
		if ( null !== $range['new_low'] && null !== $range['new_high'] ) {
			if ( $range['changed'] ) {
				$low_dir  = ( null !== $range['old_low'] && $range['new_low'] > $range['old_low'] ) ? __( 'lebih tinggi', 'bitmomo-pro' ) : __( 'lebih rendah', 'bitmomo-pro' );
				$high_dir = ( null !== $range['old_high'] && $range['new_high'] > $range['old_high'] ) ? __( 'lebih tinggi', 'bitmomo-pro' ) : __( 'lebih rendah', 'bitmomo-pro' );
				$sentences[] = sprintf(
					/* translators: 1: direction of low bound, 2: direction of high bound */
					__( 'Batas bawah expected range bergeser %1$s, batas atas bergeser %2$s.', 'bitmomo-pro' ),
					$low_dir,
					$high_dir
				);
			} else {
				$sentences[] = __( 'Expected range tidak berubah.', 'bitmomo-pro' );
			}
		}

		$price = $comparison['reference_price'];
		if ( null !== $price['delta'] && 0.0 !== $price['delta'] ) {
			$sentences[] = sprintf(
				/* translators: 1: naik/turun, 2: percentage change */
				__( 'Harga referensi %1$s %2$s%%.', 'bitmomo-pro' ),
				$price['delta'] > 0 ? __( 'naik', 'bitmomo-pro' ) : __( 'turun', 'bitmomo-pro' ),
				null !== $price['pct_change'] ? number_format_i18n( abs( $price['pct_change'] ), 1 ) : '—'
			);
		}

		if ( $comparison['invalidation']['changed'] ) {
			$sentences[] = __( 'Titik invalidasi diperbarui.', 'bitmomo-pro' );
		}

		return implode( ' ', $sentences );
	}

	// ==================================================================
	// E. WORKFLOW STAGE (display only — does not gate anything)
	// ==================================================================

	private function workflow_stages() {
		return array(
			'source'     => __( 'SOURCE', 'bitmomo-pro' ),
			'draft'      => __( 'DRAFT', 'bitmomo-pro' ),
			'editorial'  => __( 'EDITORIAL', 'bitmomo-pro' ),
			'ready'      => __( 'READY', 'bitmomo-pro' ),
			'published'  => __( 'PUBLISHED', 'bitmomo-pro' ),
			'email_sent' => __( 'EMAIL SENT', 'bitmomo-pro' ),
		);
	}

	private function current_workflow_stage( $current_post, $payload_available ) {
		if ( ! $current_post ) {
			return 'source';
		}

		if ( 'publish' === $current_post->post_status ) {
			$sent = get_post_meta( $current_post->ID, Bitmomo_Pro_Email_Service::META_DAILY_SENT_AT, true );
			return $sent ? 'email_sent' : 'published';
		}

		// draft / pending / future
		$eval = Bitmomo_Pro_Brief_Readiness::instance()->evaluate_post( $current_post->ID );
		return $eval['ready'] ? 'ready' : 'editorial';
	}

	// ==================================================================
	// RENDER
	// ==================================================================

	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$payload           = apply_filters( Bitmomo_Pro_Brief_Prefill::AVAILABILITY_FILTER, null );
		$payload_available = ! empty( $payload ) && is_array( $payload );

		$current_post  = $this->get_current_post();
		$previous_post = $this->get_previous_published_post( $current_post ? $current_post->ID : 0 );

		$stage        = $this->current_workflow_stage( $current_post, $payload_available );
		$stage_labels = $this->workflow_stages();

		echo '<div class="wrap"><h1>' . esc_html__( 'Bitmomo Pro — Daily Pro', 'bitmomo-pro' ) . '</h1>';

		// --- E. Workflow status strip -----------------------------------
		echo '<p style="font-family:monospace;font-size:13px;">';
		$parts = array();
		foreach ( $stage_labels as $key => $label ) {
			$parts[] = ( $key === $stage )
				? '<strong style="color:#1a7f4b;">[' . esc_html( $label ) . ']</strong>'
				: esc_html( $label );
		}
		echo implode( ' &rarr; ', $parts );
		echo '</p>';

		// --- A.1 Canonical source availability ---------------------------
		echo '<h2>' . esc_html__( 'Canonical Source', 'bitmomo-pro' ) . '</h2>';
		if ( ! $payload_available ) {
			echo '<p>' . esc_html__( 'Canonical source belum terhubung/tersedia.', 'bitmomo-pro' ) . '</p>';
		} else {
			$source_id = isset( $payload['source_record_id'] ) ? (string) $payload['source_record_id'] : '';
			printf( '<p>%s <code>%s</code></p>', esc_html__( 'Canonical source tersedia — source_record_id:', 'bitmomo-pro' ), esc_html( $source_id ) );

			// --- B. One-click prefill --------------------------------------
			$existing_draft_id = $source_id ? Bitmomo_Pro_Brief_Prefill::instance()->find_draft_by_source_record_id( $source_id ) : 0;
			if ( $existing_draft_id ) {
				printf(
					'<p><a class="button button-secondary" href="%s">%s</a></p>',
					esc_url( (string) get_edit_post_link( $existing_draft_id, '' ) ),
					esc_html__( 'Buka Draft Hari Ini', 'bitmomo-pro' )
				);
			} else {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( Bitmomo_Pro_Brief_Prefill::INGEST_NONCE_ACTION, Bitmomo_Pro_Brief_Prefill::INGEST_NONCE_FIELD );
				echo '<input type="hidden" name="action" value="' . esc_attr( Bitmomo_Pro_Brief_Prefill::INGEST_ACTION ) . '" />';
				echo '<button type="submit" class="button button-primary">' . esc_html__( 'Buat Draft Hari Ini', 'bitmomo-pro' ) . '</button>';
				echo '</form>';
			}
		}

		// --- A.2 Current Pro brief ---------------------------------------
		echo '<h2>' . esc_html__( 'Current Pro Brief', 'bitmomo-pro' ) . '</h2>';
		if ( ! $current_post ) {
			echo '<p>' . esc_html__( 'Belum ada brief sama sekali.', 'bitmomo-pro' ) . '</p>';
		} else {
			$fields = $this->read_fields( $current_post->ID );
			$labels = $this->market_state_labels();
			$eval   = Bitmomo_Pro_Brief_Readiness::instance()->evaluate_post( $current_post->ID );
			$tier   = Bitmomo_Pro_Brief_Readiness::instance()->compute_freshness_tier( $fields['data_timestamp'], $fields['data_freshness_status'] );

			$status_labels = array(
				'draft'   => __( 'Draft', 'bitmomo-pro' ),
				'pending' => __( 'Pending review', 'bitmomo-pro' ),
				'publish' => __( 'Published', 'bitmomo-pro' ),
				'future'  => __( 'Scheduled', 'bitmomo-pro' ),
			);

			echo '<table class="widefat" style="max-width:640px;"><tbody>';
			printf( '<tr><th>%s</th><td>#%d — <a href="%s">%s</a></td></tr>', esc_html__( 'ID/Title', 'bitmomo-pro' ), (int) $current_post->ID, esc_url( (string) get_edit_post_link( $current_post->ID, '' ) ), esc_html( get_the_title( $current_post ) ) );
			printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Status', 'bitmomo-pro' ), esc_html( isset( $status_labels[ $current_post->post_status ] ) ? $status_labels[ $current_post->post_status ] : $current_post->post_status ) );
			printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Market State', 'bitmomo-pro' ), esc_html( isset( $labels[ $fields['market_state'] ] ) ? $labels[ $fields['market_state'] ] : ( $fields['market_state'] ? $fields['market_state'] : '—' ) ) );
			printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Confidence', 'bitmomo-pro' ), esc_html( '' !== $fields['confidence'] ? $fields['confidence'] . '%' : '—' ) );
			printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Data Timestamp', 'bitmomo-pro' ), esc_html( $fields['data_timestamp'] ? $fields['data_timestamp'] : '—' ) );
			printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Freshness Tier', 'bitmomo-pro' ), esc_html( strtoupper( $tier ) ) );
			printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Readiness', 'bitmomo-pro' ), $eval['ready'] ? '<span style="color:#1a7f4b;font-weight:700;">' . esc_html__( 'SIAP DIRILIS', 'bitmomo-pro' ) . '</span>' : '<span style="color:#b32d2e;font-weight:700;">' . esc_html__( 'BELUM SIAP', 'bitmomo-pro' ) . '</span>' );
			echo '</tbody></table>';
		}

		// --- A.3 Previous published brief + C. comparison -----------------
		echo '<h2>' . esc_html__( 'Previous Published Brief (Comparison)', 'bitmomo-pro' ) . '</h2>';
		if ( ! $current_post || ! $previous_post ) {
			echo '<p>' . esc_html__( 'Belum ada cukup data untuk perbandingan.', 'bitmomo-pro' ) . '</p>';
		} else {
			$previous_fields = $this->read_fields( $previous_post->ID );
			$current_fields  = $this->read_fields( $current_post->ID );
			$comparison      = $this->compare_briefs( $previous_fields, $current_fields );
			$labels          = $this->market_state_labels();

			printf( '<p>%s <a href="%s">#%d — %s</a></p>', esc_html__( 'Dibandingkan dengan:', 'bitmomo-pro' ), esc_url( (string) get_edit_post_link( $previous_post->ID, '' ) ), (int) $previous_post->ID, esc_html( get_the_title( $previous_post ) ) );

			echo '<ul style="list-style:disc;margin-left:20px;">';
			printf(
				'<li>%s: %s &rarr; %s</li>',
				esc_html__( 'Market State', 'bitmomo-pro' ),
				esc_html( isset( $labels[ $comparison['market_state']['old'] ] ) ? $labels[ $comparison['market_state']['old'] ] : ( $comparison['market_state']['old'] ? $comparison['market_state']['old'] : '—' ) ),
				esc_html( isset( $labels[ $comparison['market_state']['new'] ] ) ? $labels[ $comparison['market_state']['new'] ] : ( $comparison['market_state']['new'] ? $comparison['market_state']['new'] : '—' ) )
			);
			if ( null !== $comparison['confidence']['delta'] ) {
				printf(
					'<li>%s: %d%% &rarr; %d%% (%s%d)</li>',
					esc_html__( 'Confidence', 'bitmomo-pro' ),
					(int) round( $comparison['confidence']['old'] ),
					(int) round( $comparison['confidence']['new'] ),
					$comparison['confidence']['delta'] >= 0 ? '+' : '',
					(int) round( $comparison['confidence']['delta'] )
				);
			}
			if ( null !== $comparison['expected_range']['new_low'] && null !== $comparison['expected_range']['old_low'] ) {
				printf(
					'<li>%s: $%s&ndash;$%s &rarr; $%s&ndash;$%s</li>',
					esc_html__( 'Expected Range', 'bitmomo-pro' ),
					esc_html( number_format_i18n( $comparison['expected_range']['old_low'] ) ),
					esc_html( number_format_i18n( $comparison['expected_range']['old_high'] ) ),
					esc_html( number_format_i18n( $comparison['expected_range']['new_low'] ) ),
					esc_html( number_format_i18n( $comparison['expected_range']['new_high'] ) )
				);
			}
			if ( null !== $comparison['reference_price']['delta'] ) {
				printf(
					'<li>%s: $%s &rarr; $%s (%s%s%%)</li>',
					esc_html__( 'Reference Price', 'bitmomo-pro' ),
					esc_html( number_format_i18n( $comparison['reference_price']['old'] ) ),
					esc_html( number_format_i18n( $comparison['reference_price']['new'] ) ),
					$comparison['reference_price']['pct_change'] >= 0 ? '+' : '',
					esc_html( number_format_i18n( $comparison['reference_price']['pct_change'], 1 ) )
				);
			}
			printf(
				'<li>%s: %s</li>',
				esc_html__( 'Invalidation', 'bitmomo-pro' ),
				$comparison['invalidation']['changed'] ? esc_html__( 'changed', 'bitmomo-pro' ) : esc_html__( 'unchanged', 'bitmomo-pro' )
			);
			echo '</ul>';

			// --- D. What Changed suggestion ---------------------------------
			if ( 'publish' !== $current_post->post_status ) {
				$suggestion = $this->build_what_changed_suggestion( $comparison );
				if ( '' !== $suggestion ) {
					$has_existing = '' !== trim( (string) $current_fields['what_changed'] );
					echo '<h3>' . esc_html__( 'What Changed — Editorial Suggestion', 'bitmomo-pro' ) . '</h3>';
					echo '<p style="max-width:640px;background:#f6f7f7;border:1px solid #dcdcde;padding:10px;">' . esc_html( $suggestion ) . '</p>';
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
					wp_nonce_field( self::APPLY_SUGGESTION_NONCE_ACTION, self::APPLY_SUGGESTION_NONCE_FIELD );
					echo '<input type="hidden" name="action" value="' . esc_attr( self::APPLY_SUGGESTION_ACTION ) . '" />';
					echo '<input type="hidden" name="post_id" value="' . esc_attr( $current_post->ID ) . '" />';
					if ( $has_existing ) {
						echo '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="confirm_overwrite" value="1" required /> ' . esc_html__( 'What Changed sudah terisi — timpa dengan saran ini', 'bitmomo-pro' ) . '</label>';
					}
					echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Gunakan sebagai draft What Changed', 'bitmomo-pro' ) . '</button>';
					echo '</form>';
				}
			}
		}

		// --- A.4 Daily email status ---------------------------------------
		echo '<h2>' . esc_html__( 'Daily Email Status', 'bitmomo-pro' ) . '</h2>';
		$latest_published = Bitmomo_Pro_Briefs::get_latest_brief();
		if ( ! $latest_published ) {
			echo '<p>' . esc_html__( 'Belum ada brief yang dipublikasikan.', 'bitmomo-pro' ) . '</p>';
		} else {
			$sent_at  = get_post_meta( $latest_published['id'], Bitmomo_Pro_Email_Service::META_DAILY_SENT_AT, true );
			$test_mode = get_post_meta( $latest_published['id'], Bitmomo_Pro_Email_Service::META_DAILY_TEST_MODE, true );
			if ( ! $sent_at ) {
				echo '<p>' . esc_html__( 'Belum dikirim.', 'bitmomo-pro' ) . '</p>';
			} else {
				printf(
					'<p>%s <strong>%s</strong> (%s) — %d %s, %d %s, %d %s. <a href="%s">%s</a></p>',
					esc_html( $test_mode ? __( 'Test dikirim:', 'bitmomo-pro' ) : __( 'Dikirim:', 'bitmomo-pro' ) ),
					esc_html( $sent_at ),
					esc_html( $test_mode ? __( 'test mode', 'bitmomo-pro' ) : __( 'real', 'bitmomo-pro' ) ),
					(int) get_post_meta( $latest_published['id'], Bitmomo_Pro_Email_Service::META_DAILY_RECIPIENT_COUNT, true ),
					esc_html__( 'penerima', 'bitmomo-pro' ),
					(int) get_post_meta( $latest_published['id'], Bitmomo_Pro_Email_Service::META_DAILY_SUCCESS_COUNT, true ),
					esc_html__( 'sukses', 'bitmomo-pro' ),
					(int) get_post_meta( $latest_published['id'], Bitmomo_Pro_Email_Service::META_DAILY_FAILURE_COUNT, true ),
					esc_html__( 'gagal', 'bitmomo-pro' ),
					esc_url( (string) get_edit_post_link( $latest_published['id'], '' ) ),
					esc_html__( 'Buka brief', 'bitmomo-pro' )
				);
			}
			// G. Do NOT auto-send and do NOT duplicate the send control here —
			// link to the existing "Kirim Email Harian" meta box on the post
			// edit screen, which is the one and only send action.
			printf(
				'<p><a class="button button-secondary" href="%s">%s</a></p>',
				esc_url( (string) get_edit_post_link( $latest_published['id'], '' ) ),
				esc_html__( 'Kelola pengiriman email di halaman edit brief', 'bitmomo-pro' )
			);
		}

		echo '</div>';
	}

	// ==================================================================
	// D. Apply suggestion handler — never trusts client-submitted text;
	// always recomputes the suggestion server-side from current state.
	// ==================================================================

	public function handle_apply_suggestion() {
		if ( ! isset( $_POST[ self::APPLY_SUGGESTION_NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::APPLY_SUGGESTION_NONCE_FIELD ] ) ), self::APPLY_SUGGESTION_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bitmomo-pro' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || Bitmomo_Pro_Briefs::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'Invalid brief.', 'bitmomo-pro' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bitmomo-pro' ) );
		}

		$confirm_overwrite = ! empty( $_POST['confirm_overwrite'] );
		$current_value     = get_post_meta( $post_id, '_bitmomo_pro_what_changed', true );
		$redirect          = admin_url( 'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE . '&page=bitmomo-pro-daily' );

		if ( '' !== trim( (string) $current_value ) && ! $confirm_overwrite ) {
			// No hidden overwrite — refuse silently-destructive writes.
			set_transient( 'bitmomo_pro_apply_suggestion_result_' . get_current_user_id(), 'blocked', MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'bitmomo_pro_apply_result', '1', $redirect ) );
			exit;
		}

		$previous_post = $this->get_previous_published_post( $post_id );
		if ( ! $previous_post ) {
			set_transient( 'bitmomo_pro_apply_suggestion_result_' . get_current_user_id(), 'no_comparison', MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'bitmomo_pro_apply_result', '1', $redirect ) );
			exit;
		}

		$comparison = $this->compare_briefs( $this->read_fields( $previous_post->ID ), $this->read_fields( $post_id ) );
		$suggestion = $this->build_what_changed_suggestion( $comparison );

		update_post_meta( $post_id, '_bitmomo_pro_what_changed', sanitize_textarea_field( $suggestion ) );

		set_transient( 'bitmomo_pro_apply_suggestion_result_' . get_current_user_id(), 'applied', MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&bitmomo_pro_apply_result=1' ) );
		exit;
	}

	public function render_apply_suggestion_notice() {
		if ( ! isset( $_GET['bitmomo_pro_apply_result'] ) ) {
			return;
		}

		$result = get_transient( 'bitmomo_pro_apply_suggestion_result_' . get_current_user_id() );
		if ( ! $result ) {
			return;
		}
		delete_transient( 'bitmomo_pro_apply_suggestion_result_' . get_current_user_id() );

		if ( 'applied' === $result ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Saran What Changed diterapkan ke draft. Silakan tinjau dan sesuaikan sebelum publish.', 'bitmomo-pro' ) . '</p></div>';
		} elseif ( 'blocked' === $result ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'What Changed sudah terisi — tidak ditimpa. Centang konfirmasi jika memang ingin menggantinya.', 'bitmomo-pro' ) . '</p></div>';
		} elseif ( 'no_comparison' === $result ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Tidak ada brief terpublikasi sebelumnya untuk dibandingkan.', 'bitmomo-pro' ) . '</p></div>';
		}
	}
}
