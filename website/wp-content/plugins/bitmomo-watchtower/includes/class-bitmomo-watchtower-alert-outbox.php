<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The provider-neutral alert outbox for Product B (WATCHTOWER PR 3).
 *
 * A private, headless, append-only custom post type — same trust
 * posture as Bitmomo_Regime_State_Store / Bitmomo_Watchtower_Thesis_Store.
 * This class ONLY enqueues alert records with status 'queued'. It never
 * sends anything anywhere: no Telegram credentials exist in this
 * plugin, no HTTP call is made, no provider adapter is implemented.
 * This is the explicit, clean handoff point the spec asks for — Codex's
 * future Telegram adapter reads `get_queued()`, sends, and would mark an
 * entry 'sent'/'failed' (that transition is NOT implemented here).
 */
class Bitmomo_Watchtower_Alert_Outbox {

	const POST_TYPE = 'bm_watchtower_alert';

	const META_KEYS = array(
		'created_at',
		'alert_class',
		'transition_type',
		'reason',
		'track',
		'domain',
		'payload',
		'status',
	);

	const JSON_META_KEYS = array( 'payload' );

	/**
	 * The ONLY status this plugin ever writes. A future Telegram adapter
	 * (Codex's runtime work, not part of this plugin) is what would move
	 * an entry to 'sent' or 'failed' after this plugin hands it off.
	 */
	const STATUS_QUEUED = 'queued';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Watchtower Alert Outbox', 'bitmomo-watchtower' ),
					'singular_name' => __( 'Watchtower Alert', 'bitmomo-watchtower' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
			)
		);
	}

	/**
	 * @param array $alert {alert_class, transition_type, reason, track, domain, payload}
	 *                      — the caller (Bitmomo_Watchtower_Orchestrator) is expected to have
	 *                      already decided should_alert=true via Bitmomo_Watchtower_Alert_Policy
	 *                      before calling this; this method does not re-check cooldown itself.
	 *
	 * @return array{success:bool,errors:string[],record:?array}
	 */
	public function enqueue( $alert ) {
		if ( ! is_array( $alert ) || empty( $alert['alert_class'] ) || empty( $alert['transition_type'] ) ) {
			return array(
				'success' => false,
				'errors'  => array( 'alert_class and transition_type are required to enqueue an alert.' ),
				'record'  => null,
			);
		}

		$state = array(
			'created_at'       => current_time( 'mysql' ),
			'alert_class'      => $alert['alert_class'],
			'transition_type'  => $alert['transition_type'],
			'reason'           => isset( $alert['reason'] ) ? (string) $alert['reason'] : '',
			'track'            => isset( $alert['track'] ) ? (string) $alert['track'] : '',
			'domain'           => isset( $alert['domain'] ) ? (string) $alert['domain'] : '',
			'payload'          => ( isset( $alert['payload'] ) && is_array( $alert['payload'] ) ) ? $alert['payload'] : array(),
			'status'           => self::STATUS_QUEUED,
		);

		$post_id = $this->record( $state );
		if ( ! $post_id ) {
			return array(
				'success' => false,
				'errors'  => array( 'Failed to persist the alert record.' ),
				'record'  => null,
			);
		}

		$state['id'] = $post_id;
		return array(
			'success' => true,
			'errors'  => array(),
			'record'  => $state,
		);
	}

	private function record( $state ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s alert — %s', $state['alert_class'], $state['created_at'] ),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		foreach ( self::META_KEYS as $key ) {
			if ( ! array_key_exists( $key, $state ) ) {
				continue;
			}
			$value = $state[ $key ];
			if ( in_array( $key, self::JSON_META_KEYS, true ) ) {
				$value = wp_json_encode( $value );
			}
			update_post_meta( $post_id, '_bitmomo_watchtower_alert_' . $key, $value );
		}

		return (int) $post_id;
	}

	/**
	 * The most recent alert of a given class, regardless of status — this
	 * is what Bitmomo_Watchtower_Alert_Policy's cooldown check reads.
	 */
	public function get_last_for_class( $alert_class ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_bitmomo_watchtower_alert_alert_class',
						'value' => $alert_class,
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		return $this->hydrate( $posts[0] );
	}

	/**
	 * Everything still in 'queued' status, oldest first — what a future
	 * Telegram adapter would drain.
	 *
	 * @return array[]
	 */
	public function get_queued( $limit = 50 ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) $limit ),
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_bitmomo_watchtower_alert_status',
						'value' => self::STATUS_QUEUED,
					),
				),
			)
		);

		$records = array();
		foreach ( $posts as $post ) {
			$records[] = $this->hydrate( $post );
		}
		return $records;
	}

	/**
	 * Up to $limit most recent alert records regardless of status, newest
	 * first — for admin diagnostics (WATCHTOWER PR 4).
	 *
	 * @return array[]
	 */
	public function get_recent( $limit = 30 ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) $limit ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$records = array();
		foreach ( $posts as $post ) {
			$records[] = $this->hydrate( $post );
		}
		return $records;
	}

	private function hydrate( $post ) {
		$record = array(
			'id'   => $post->ID,
			'date' => get_the_date( 'Y-m-d H:i:s', $post ),
		);

		foreach ( self::META_KEYS as $key ) {
			$raw = get_post_meta( $post->ID, '_bitmomo_watchtower_alert_' . $key, true );
			if ( in_array( $key, self::JSON_META_KEYS, true ) ) {
				$decoded        = json_decode( (string) $raw, true );
				$record[ $key ] = is_array( $decoded ) ? $decoded : array();
			} else {
				$record[ $key ] = $raw;
			}
		}

		return $record;
	}
}
