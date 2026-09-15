<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show-First presentation enhancer.
 *
 * Reads only the existing public-safe adapter. It does not recompute market
 * intelligence, read protected Pro fields, or alter freshness/entitlement.
 */
final class Bitmomo_Btc_Intelligence_Show_First {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'do_shortcode_tag', array( $this, 'enhance_shortcode' ), 20, 4 );
	}

	public function enhance_shortcode( $output, $tag, $attr, $match ) {
		if ( 'bitmomo_btc_intelligence' !== $tag || ! is_string( $output ) || '' === $output ) {
			return $output;
		}
		if ( ! class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) || ! method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'snapshot' ) ) {
			return $output;
		}

		$snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
		if ( ! is_array( $snapshot ) ) {
			return $output;
		}

		$context = $this->render_evidence_context( $snapshot );
		if ( '' === $context ) {
			return $output;
		}

		$section_start = strpos( $output, '<section id="btc-now"' );
		if ( false === $section_start ) {
			return $output;
		}
		$section_end = strpos( $output, '</section>', $section_start );
		if ( false === $section_end ) {
			return $output;
		}
		$insert_at = $section_end + strlen( '</section>' );

		return substr( $output, 0, $insert_at ) . $context . substr( $output, $insert_at );
	}

	private function render_evidence_context( array $snapshot ) {
		$regime = sanitize_key( (string) ( $snapshot['market_state'] ?? '' ) );
		$regime_labels = array(
			'accumulation' => __( 'Akumulasi', 'bitmomo-btc-intelligence' ),
			'expansion'    => __( 'Ekspansi', 'bitmomo-btc-intelligence' ),
			'distribution' => __( 'Distribusi', 'bitmomo-btc-intelligence' ),
			'capitulation' => __( 'Kapitulasi', 'bitmomo-btc-intelligence' ),
			'transition'   => __( 'Transisi', 'bitmomo-btc-intelligence' ),
		);
		$regime_label = isset( $regime_labels[ $regime ] ) ? $regime_labels[ $regime ] : '';
		$certainty = isset( $snapshot['market_state_certainty'] ) && is_numeric( $snapshot['market_state_certainty'] )
			? max( 0, min( 100, (int) $snapshot['market_state_certainty'] ) )
			: null;
		$drivers = array_slice(
			array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $snapshot['key_drivers'] ?? array() ) ) ) ),
			0,
			2
		);

		if ( '' === $regime_label && ! $drivers ) {
			return '';
		}

		ob_start();
		?>
		<section class="bm-bi__evidence-context" aria-label="<?php esc_attr_e( 'Konteks bukti pasar', 'bitmomo-btc-intelligence' ); ?>">
			<?php if ( '' !== $regime_label ) : ?>
				<div class="bm-bi__evidence-regime">
					<span><?php esc_html_e( 'REGIME CONTEXT', 'bitmomo-btc-intelligence' ); ?></span>
					<strong><?php echo esc_html( $regime_label ); ?></strong>
					<?php if ( null !== $certainty ) : ?><small><?php echo esc_html( sprintf( __( 'certainty %d/100', 'bitmomo-btc-intelligence' ), $certainty ) ); ?></small><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $drivers ) : ?>
				<div class="bm-bi__evidence-drivers">
					<span><?php esc_html_e( 'DOMINANT EVIDENCE', 'bitmomo-btc-intelligence' ); ?></span>
					<ul>
						<?php foreach ( $drivers as $driver ) : ?><li><?php echo esc_html( $driver ); ?></li><?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
