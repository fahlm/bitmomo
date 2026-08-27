<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The bm_pro_brief post type: the versioned, private record of what Pro
 * subscribers actually received each day.
 *
 * This is a product artifact, not a market-data pipeline — it does not
 * fetch or compute anything itself. Content is authored manually in
 * wp-admin for now; the optional source_record_id field exists so a later
 * integration can pre-fill a draft from the canonical Bitmomo AI record
 * (once Codex's canonical adapter work lands) without changing this
 * content type's shape.
 *
 * Once published, a brief represents what was actually shown to
 * subscribers that day and must be preserved, not overwritten — create a
 * new brief for a new day instead of editing an old one after publish.
 *
 * As of PR #31: publishing an incomplete brief is actively prevented by
 * Bitmomo_Pro_Brief_Readiness (it gets reverted to draft, nothing is
 * deleted). get_current_brief_for_display(), below, is what the dashboard
 * shortcode should call — it applies both the readiness and freshness
 * gate to the single latest published brief and never falls back to an
 * older post if that one fails either check.
 */
class Bitmomo_Pro_Briefs {

	const POST_TYPE = 'bm_pro_brief';

	const NONCE_ACTION = 'bitmomo_pro_save_brief';
	const NONCE_FIELD  = 'bitmomo_pro_brief_nonce';

	const MARKET_STATES    = array( 'bullish', 'neutral', 'bearish' );
	const FRESHNESS_STATES = array( 'fresh', 'delayed', 'unavailable' );

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ) );
	}

	public function register_post_type() {
		$labels = array(
			'name'          => __( 'Pro Briefs', 'bitmomo-pro' ),
			'singular_name' => __( 'Pro Brief', 'bitmomo-pro' ),
			'add_new_item'  => __( 'Add Pro Brief', 'bitmomo-pro' ),
			'edit_item'     => __( 'Edit Pro Brief', 'bitmomo-pro' ),
			'all_items'     => __( 'Pro Briefs', 'bitmomo-pro' ),
			'menu_name'     => __( 'Bitmomo Pro Briefs', 'bitmomo-pro' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				// Deliberately private: no public URL, no public query, no
				// REST route. This is paid content, not a public post type.
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'post',
				'supports'            => array( 'title', 'revisions' ),
				'menu_icon'           => 'dashicons-analytics',
			)
		);
	}

	public function add_meta_box() {
		add_meta_box(
			'bitmomo_pro_brief_fields',
			__( 'Pro Brief — Decision View', 'bitmomo-pro' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * The exact V1 field set. Intentionally closed — do not add indicators,
	 * raw analyst output, or other assets (e.g. ETH/altcoins) here.
	 */
	private function fields() {
		return array(
			'market_state'           => array(
				'label'   => __( 'Market State', 'bitmomo-pro' ),
				'type'    => 'select',
				'options' => self::MARKET_STATES,
			),
			'btc_reference_price'    => array( 'label' => __( 'BTC Reference Price', 'bitmomo-pro' ), 'type' => 'number' ),
			'confidence'             => array( 'label' => __( 'Confidence (0-100)', 'bitmomo-pro' ), 'type' => 'number' ),
			'confidence_explanation' => array( 'label' => __( 'Confidence Explanation', 'bitmomo-pro' ), 'type' => 'textarea' ),
			'expected_range_low'     => array( 'label' => __( 'Expected Range Low', 'bitmomo-pro' ), 'type' => 'number' ),
			'expected_range_high'    => array( 'label' => __( 'Expected Range High', 'bitmomo-pro' ), 'type' => 'number' ),
			'base_scenario'          => array( 'label' => __( 'Base Scenario', 'bitmomo-pro' ), 'type' => 'textarea' ),
			'bull_scenario'          => array( 'label' => __( 'Bull Scenario', 'bitmomo-pro' ), 'type' => 'textarea' ),
			'bear_scenario'          => array( 'label' => __( 'Bear Scenario', 'bitmomo-pro' ), 'type' => 'textarea' ),
			'invalidation'           => array( 'label' => __( 'Thesis Invalidation', 'bitmomo-pro' ), 'type' => 'textarea' ),
			'what_changed'           => array( 'label' => __( 'What Changed', 'bitmomo-pro' ), 'type' => 'textarea' ),
			'data_timestamp'         => array( 'label' => __( 'Data / Update Timestamp', 'bitmomo-pro' ), 'type' => 'datetime-local' ),
			'data_freshness_status'  => array(
				'label'   => __( 'Data Quality / Freshness', 'bitmomo-pro' ),
				'type'    => 'select',
				'options' => self::FRESHNESS_STATES,
			),
			'source_record_id'       => array( 'label' => __( 'Source Record ID (optional)', 'bitmomo-pro' ), 'type' => 'text' ),
		);
	}

	/**
	 * Public accessor for the exact V1 field-key list (PR #36 integration
	 * audit) — lets other classes that need just the key list (e.g.
	 * Bitmomo_Pro_Brief_Readiness, Bitmomo_Pro_Daily) read it from this
	 * single canonical definition instead of maintaining their own copy
	 * that could drift out of sync with fields() above.
	 */
	public static function field_keys() {
		return array_keys( self::instance()->fields() );
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		echo '<table class="form-table" role="presentation">';
		foreach ( $this->fields() as $key => $field ) {
			$meta_key = '_bitmomo_pro_' . $key;
			$value    = get_post_meta( $post->ID, $meta_key, true );
			echo '<tr><th style="width:220px;"><label for="' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';

			if ( 'select' === $field['type'] ) {
				echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '">';
				echo '<option value="">' . esc_html__( '— select —', 'bitmomo-pro' ) . '</option>';
				foreach ( $field['options'] as $option ) {
					printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $option ), selected( $value, $option, false ) );
				}
				echo '</select>';
			} elseif ( 'textarea' === $field['type'] ) {
				echo '<textarea name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" class="large-text" rows="3">' . esc_textarea( $value ) . '</textarea>';
			} elseif ( 'number' === $field['type'] ) {
				echo '<input type="number" step="any" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
			} elseif ( 'datetime-local' === $field['type'] ) {
				echo '<input type="datetime-local" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			} else {
				echo '<input type="text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		echo '<p class="description">' . esc_html__( 'Publishing this brief preserves it permanently as the record of what subscribers received that day. Create a new brief for a new day rather than editing an old one after publish. If required fields are missing, publishing will be blocked and reverted to draft — see the Status Rilis box.', 'bitmomo-pro' ) . '</p>';
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( $this->fields() as $key => $field ) {
			$meta_key = '_bitmomo_pro_' . $key;

			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$raw = wp_unslash( $_POST[ $key ] );

			switch ( $field['type'] ) {
				case 'select':
					$value = in_array( $raw, $field['options'], true ) ? $raw : '';
					break;
				case 'number':
					$value = is_numeric( $raw ) ? floatval( $raw ) : '';
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $raw );
					break;
				case 'datetime-local':
					$value = preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $raw ) ? sanitize_text_field( $raw ) : '';
					break;
				default:
					$value = sanitize_text_field( $raw );
					break;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Returns the latest published Pro brief as an associative array, or
	 * null if none has been published yet. Raw fetch only — does NOT apply
	 * readiness or freshness gating. Prefer get_current_brief_for_display()
	 * for anything user-facing (PR #31).
	 */
	public static function get_latest_brief() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		$post = $posts[0];
		$data = array(
			'id'    => $post->ID,
			'title' => get_the_title( $post ),
			'date'  => get_the_date( 'Y-m-d H:i', $post ),
		);

		$instance = self::instance();
		foreach ( $instance->fields() as $key => $field ) {
			$data[ $key ] = get_post_meta( $post->ID, '_bitmomo_pro_' . $key, true );
		}

		return $data;
	}

	/**
	 * The method the dashboard should call. Applies readiness AND
	 * freshness to the single latest published brief only — it never
	 * looks past that one post to an older, still-published brief, even
	 * if an older one happens to still pass both checks. A newer brief
	 * that fails either check means "no current brief", full stop; older
	 * briefs remain visible in wp-admin history but are never surfaced to
	 * subscribers as a silent fallback.
	 *
	 * @return array{tier:string,brief:?array} tier is one of
	 *         'fresh'|'delayed'|'unavailable'; brief is null whenever
	 *         tier is 'unavailable' (including "nothing published yet").
	 */
	public static function get_current_brief_for_display() {
		$brief = self::get_latest_brief();
		if ( null === $brief ) {
			return array( 'tier' => Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE, 'brief' => null );
		}

		$readiness = Bitmomo_Pro_Brief_Readiness::instance();

		$eval = $readiness->evaluate( $brief );
		if ( ! $eval['ready'] ) {
			return array( 'tier' => Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE, 'brief' => null );
		}

		$tier = $readiness->compute_freshness_tier( $brief['data_timestamp'], $brief['data_freshness_status'] );
		if ( Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE === $tier ) {
			return array( 'tier' => $tier, 'brief' => null );
		}

		return array( 'tier' => $tier, 'brief' => $brief );
	}
}

