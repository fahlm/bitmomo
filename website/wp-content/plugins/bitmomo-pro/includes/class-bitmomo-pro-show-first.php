<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Presentation-only enhancer for the public Pro sales shortcode.
 *
 * It visualizes only the same delayed/frozen public proof already consumed by
 * Bitmomo_Pro_Sales. It never queries current protected Pro content, never
 * recomputes intelligence, and never changes payment/entitlement behavior.
 */
final class Bitmomo_Pro_Show_First {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'do_shortcode_tag', array( $this, 'enhance_sales_shortcode' ), 20, 4 );
	}

	public function enhance_sales_shortcode( $output, $tag, $attr, $match ) {
		if ( 'bitmomo_pro_sales' !== $tag || ! is_string( $output ) || '' === $output ) {
			return $output;
		}
		if ( ! class_exists( 'Bitmomo_Btc_Intelligence_Accountability' ) || ! method_exists( 'Bitmomo_Btc_Intelligence_Accountability', 'delayed_proof' ) ) {
			return $output;
		}

		$contract = Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 );
		$rows = is_array( $contract['rows'] ?? null ) ? $contract['rows'] : array();
		$row = ! empty( $rows[0] ) && is_array( $rows[0] ) ? $rows[0] : null;
		if ( ! $row ) {
			return $output;
		}

		$visual = $this->render_decision_visual( $row );
		if ( '' === $visual ) {
			return $output;
		}

		$article_marker = '<article class="bm-pro-sales__decision-card">';
		$article_start = strpos( $output, $article_marker );
		if ( false === $article_start ) {
			return $output;
		}
		$article_end = strpos( $output, '</article>', $article_start );
		if ( false === $article_end ) {
			return $output;
		}

		$output = substr_replace(
			$output,
			'<article class="bm-pro-sales__decision-card is-show-first-enhanced">',
			$article_start,
			strlen( $article_marker )
		);
		$article_end += strlen( ' is-show-first-enhanced' );

		return substr( $output, 0, $article_end ) . $visual . substr( $output, $article_end );
	}

	private function render_decision_visual( array $row ) {
		$low = $this->positive_number( $row['expected_range_low'] ?? null );
		$high = $this->positive_number( $row['expected_range_high'] ?? null );
		$reference = $this->positive_number( $row['reference_price'] ?? null );
		$outcome = $this->positive_number( $row['outcome_price_24h'] ?? null );
		$base = sanitize_textarea_field( (string) ( $row['base_scenario'] ?? '' ) );
		$bull = sanitize_textarea_field( (string) ( $row['bull_scenario'] ?? '' ) );
		$bear = sanitize_textarea_field( (string) ( $row['bear_scenario'] ?? '' ) );

		$has_range = null !== $low && null !== $high && $high >= $low;
		$has_scenarios = '' !== $base || '' !== $bull || '' !== $bear;
		if ( ! $has_range && ! $has_scenarios ) {
			return '';
		}

		$positions = $has_range ? $this->range_positions( $low, $high, $reference, $outcome ) : array();
		ob_start();
		?>
		<div class="bm-pro-sales__decision-visual" aria-label="<?php esc_attr_e( 'Visualisasi Decision View historis', 'bitmomo-pro' ); ?>">
			<?php if ( $has_range ) : ?>
				<div class="bm-pro-sales__range-visual">
					<div class="bm-pro-sales__range-head"><span>EXPECTED RANGE</span><strong><?php echo esc_html( $this->format_price( $low ) . ' – ' . $this->format_price( $high ) ); ?></strong></div>
					<div class="bm-pro-sales__range-track" role="img" aria-label="<?php echo esc_attr( sprintf( 'Expected range %s sampai %s', $this->format_price( $low ), $this->format_price( $high ) ) ); ?>">
						<span class="bm-pro-sales__range-band" style="--range-left:<?php echo esc_attr( $positions['range_left'] ); ?>%;--range-width:<?php echo esc_attr( $positions['range_width'] ); ?>%;"></span>
						<?php if ( null !== $reference ) : ?><span class="bm-pro-sales__range-marker is-reference" style="--marker-pos:<?php echo esc_attr( $positions['reference'] ); ?>%;"><i></i><small>REF <?php echo esc_html( $this->format_price( $reference ) ); ?></small></span><?php endif; ?>
						<?php if ( null !== $outcome ) : ?><span class="bm-pro-sales__range-marker is-outcome" style="--marker-pos:<?php echo esc_attr( $positions['outcome'] ); ?>%;"><i></i><small>+24H <?php echo esc_html( $this->format_price( $outcome ) ); ?></small></span><?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $has_scenarios ) : ?>
				<div class="bm-pro-sales__scenario-map" aria-label="<?php esc_attr_e( 'Scenario Map historis', 'bitmomo-pro' ); ?>">
					<?php if ( '' !== $bear ) : ?><div class="is-bear"><span>BEAR</span><p><?php echo esc_html( $bear ); ?></p></div><?php endif; ?>
					<?php if ( '' !== $base ) : ?><div class="is-base"><span>BASE</span><p><?php echo esc_html( $base ); ?></p></div><?php endif; ?>
					<?php if ( '' !== $bull ) : ?><div class="is-bull"><span>BULL</span><p><?php echo esc_html( $bull ); ?></p></div><?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function range_positions( $low, $high, $reference, $outcome ) {
		$points = array( $low, $high );
		if ( null !== $reference ) $points[] = $reference;
		if ( null !== $outcome ) $points[] = $outcome;
		$min = min( $points );
		$max = max( $points );
		$span = max( 1.0, $max - $min );
		$padding = $span * 0.12;
		$floor = max( 0.0, $min - $padding );
		$ceiling = $max + $padding;
		$visual_span = max( 1.0, $ceiling - $floor );
		$position = static function ( $value ) use ( $floor, $visual_span ) {
			if ( null === $value ) return null;
			return round( max( 0, min( 100, ( ( $value - $floor ) / $visual_span ) * 100 ) ), 2 );
		};
		$range_left = $position( $low );
		$range_right = $position( $high );
		return array(
			'range_left' => $range_left,
			'range_width' => round( max( 1, $range_right - $range_left ), 2 ),
			'reference' => $position( $reference ),
			'outcome' => $position( $outcome ),
		);
	}

	private function positive_number( $value ) {
		return is_numeric( $value ) && (float) $value > 0 ? (float) $value : null;
	}

	private function format_price( $value ) {
		return null !== $value ? '$' . number_format( (float) $value, 0, '.', ',' ) : '—';
	}
}
