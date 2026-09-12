<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The bm_pro_brief post type: the versioned, private record of what Pro
 * subscribers actually received each day.
 *
 * IMPORTANT COMPATIBILITY NOTE:
 * Early Pro briefs stored directional bias (bullish/neutral/bearish) under
 * the meta key `_bitmomo_pro_market_state`. That storage key is retained for
 * backwards compatibility, but it MUST be interpreted and presented as
 * Directional Bias. Actual public Market State/regime is the separate
 * accumulation/expansion/distribution/capitulation/transition concept owned
 * by the canonical intelligence pipeline.
 */
class Bitmomo_Pro_Briefs {

	const POST_TYPE = 'bm_pro_brief';

	const NONCE_ACTION = 'bitmomo_pro_save_brief';
	const NONCE_FIELD  = 'bitmomo_pro_brief_nonce';

	const DIRECTIONAL_BIASES = array( 'bullish', 'neutral', 'bearish' );
	// Deprecated semantic name retained only for older callers/tests. New code
	// must use DIRECTIONAL_BIASES and present this field as Bias.
	const MARKET_STATES      = array( 'bullish', 'neutral', 'bearish' );
	const FRESHNESS_STATES   = array( 'fresh', 'delayed', 'unavailable' );

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
	 * Exact V1 field set. `market_state` is a LEGACY STORAGE KEY for
	 * Directional Bias; the admin label deliberately exposes the correct
	 * semantic concept so new editorial records do not perpetuate the bug.
	 */
	private function fields() {
		return array(
			'market_state'           => array(
				'label'   => __( 'Directional Bias', 'bitmomo-pro' ),
				'type'    => 'select',
				'options' => self::DIRECTIONAL_BIASES,
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
			'source_record_id'       => array( 'label' => __( 'Canonical Source Record ID (required to publish)', 'bitmomo-pro' ), 'type' => 'text' ),
		);
	}

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
				$has_timezone  = (bool) preg_match( '/(?:Z|[+-]\\d{2}:?\\d{2})$/i', (string) $value );
				$timestamp     = $has_timezone ? strtotime( (string) $value ) : false;
				$display_value = $timestamp ? wp_date( 'Y-m-d\\TH:i', $timestamp, wp_timezone() ) : $value;
				echo '<input type="datetime-local" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $display_value ) . '" />';
			} else {
				echo '<input type="text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		echo '<p class="description">' . esc_html__( 'Publishing preserves this brief as the record of what subscribers received. Directional Bias uses the legacy market_state storage key for compatibility. A canonical Source Record ID and all release fields are required before publication.', 'bitmomo-pro' ) . '</p>';
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		foreach ( $this->fields() as $key => $field ) {
			$meta_key = '_bitmomo_pro_' . $key;
			if ( ! isset( $_POST[ $key ] ) ) continue;
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

		if ( empty( $posts ) ) return null;

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
		// Preferred semantic alias for new callers; legacy key remains present.
		$data['directional_bias'] = $data['market_state'] ?? '';

		return $data;
	}

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
