<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tiny founder-only scoreboard for the single Founding 149 acquisition motion.
 *
 * This is intentionally not a CRM and does not create a new record type. It
 * reads the canonical Bitmomo_Pro_Whitelist UTM/status/classification metadata
 * and summarizes one campaign only.
 */
final class Bitmomo_Pro_Founding149_Acquisition {

	const CAMPAIGN = 'founding149_tlw_v1';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'restrict_manage_posts', array( $this, 'render_admin_summary' ), 20 );
	}

	/**
	 * @return array{attributed:int,external:int,waiting:int,invited:int,converted:int,excluded:int}
	 */
	public function counts() {
		$out = array(
			'attributed' => 0,
			'external'   => 0,
			'waiting'    => 0,
			'invited'    => 0,
			'converted'  => 0,
			'excluded'   => 0,
		);

		if ( ! class_exists( 'Bitmomo_Pro_Whitelist' ) ) {
			return $out;
		}

		$posts = get_posts(
			array(
				'post_type'      => Bitmomo_Pro_Whitelist::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_key'       => Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN,
				'meta_value'     => self::CAMPAIGN,
				'fields'         => 'ids',
			)
		);

		foreach ( (array) $posts as $post_id ) {
			$out['attributed']++;
			$class = sanitize_key( (string) get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );
			if ( in_array( $class, array( 'test', 'internal' ), true ) ) {
				$out['excluded']++;
				continue;
			}

			$out['external']++;
			$status = sanitize_key( (string) get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_STATUS, true ) );
			if ( '' === $status ) {
				$status = Bitmomo_Pro_Whitelist::STATUS_WAITING;
			}
			if ( isset( $out[ $status ] ) && in_array( $status, Bitmomo_Pro_Whitelist::STATUSES, true ) ) {
				$out[ $status ]++;
			}
		}

		return $out;
	}

	public function render_admin_summary( $post_type ) {
		if (
			! class_exists( 'Bitmomo_Pro_Whitelist' )
			|| Bitmomo_Pro_Whitelist::POST_TYPE !== $post_type
			|| ! current_user_can( 'manage_options' )
		) {
			return;
		}

		$count = $this->counts();
		printf(
			'<span style="margin:0 8px;display:inline-block;"><strong>%s</strong> %s</span>',
			esc_html__( 'Founding149 TLW —', 'bitmomo-pro' ),
			esc_html(
				sprintf(
					/* translators: 1: attributed, 2: external, 3: converted, 4: excluded test/internal */
					__( 'attributed: %1$d, external: %2$d, converted: %3$d, excluded: %4$d', 'bitmomo-pro' ),
					$count['attributed'],
					$count['external'],
					$count['converted'],
					$count['excluded']
				)
			)
		);
	}
}
