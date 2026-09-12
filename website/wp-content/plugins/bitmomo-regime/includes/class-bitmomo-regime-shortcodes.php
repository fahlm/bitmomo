<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public Market State shortcodes.
 *
 * The history surface is intentionally compact: one visual, one selected
 * readout, no modal/list duplication. Height encodes Market State certainty;
 * color encodes Directional Bias. Missing days are never fabricated.
 */
class Bitmomo_Regime_Shortcodes {

	private static $instance = null;
	private $style_printed = false;
	private $history_script_printed = false;

	public static function instance() {
		if ( null === self::$instance ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	public function body_class( $classes ) {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'bitmomo_market_regime' ) || has_shortcode( $post->post_content, 'bitmomo_market_regime_history' ) ) ) {
			$classes[] = 'bm-regime-page';
		}
		return $classes;
	}

	public function register() {
		add_shortcode( 'bitmomo_market_regime', array( $this, 'render_current' ) );
		add_shortcode( 'bitmomo_market_regime_history', array( $this, 'render_history' ) );
	}

	public function render_current( $atts ) {
		$latest = Bitmomo_Regime_State_Store::instance()->get_latest();
		if ( null === $latest ) {
			return $this->style() . '<div class="bmreg-card bmreg-empty">' . esc_html__( 'Market State belum tersedia.', 'bitmomo-regime' ) . '</div>';
		}

		$regime_id = Bitmomo_Regime_Taxonomy::regime_label_id( $latest['regime'] );
		$bias = isset( $latest['directional_bias'] ) ? sanitize_key( $latest['directional_bias'] ) : '';
		$conf = isset( $latest['regime_confidence'] ) ? max( 0, min( 100, (int) $latest['regime_confidence'] ) ) : 0;

		ob_start(); ?>
		<div class="bmreg-card bmreg-current" data-regime="<?php echo esc_attr( $latest['regime'] ); ?>">
			<span class="bmreg-current-label"><?php esc_html_e( 'Market State', 'bitmomo-regime' ); ?></span>
			<strong class="bmreg-regime-id"><?php echo esc_html( $regime_id ); ?></strong>
			<div class="bmreg-meta">
				<span class="bmreg-bias bmreg-bias-<?php echo esc_attr( $bias ); ?>"><?php echo esc_html( ucfirst( $bias ) ); ?></span>
				<span><?php echo esc_html( $conf ); ?>% certainty</span>
			</div>
		</div>
		<?php
		return $this->style() . trim( (string) ob_get_clean() );
	}

	public function render_history( $atts ) {
		$atts = shortcode_atts( array( 'days' => Bitmomo_Regime_History::DEFAULT_DAYS ), $atts, 'bitmomo_market_regime_history' );
		$requested = (int) $atts['days'];
		$records = Bitmomo_Regime_State_Store::instance()->get_recent( Bitmomo_Regime_History::TARGET_DAYS );
		$full = Bitmomo_Regime_History::for_frontend( $records, $requested );
		$days = isset( $full['days'] ) && is_array( $full['days'] ) ? $full['days'] : array();

		ob_start(); ?>
		<div class="bmreg-history">
			<?php if ( empty( $days ) ) : ?>
				<div class="bmreg-card bmreg-empty"><?php esc_html_e( 'Riwayat Market State belum tersedia.', 'bitmomo-regime' ); ?></div>
			<?php else : ?>
				<?php $latest = $days[ count( $days ) - 1 ]; ?>
				<div class="bmreg-history-head">
					<span><?php echo esc_html( sprintf( '%dD MARKET CONTEXT', count( $days ) ) ); ?></span>
					<div class="bmreg-history-legend" aria-label="Warna menunjukkan Directional Bias">
						<span class="is-bullish">Bullish</span><span class="is-neutral">Neutral</span><span class="is-bearish">Bearish</span>
					</div>
				</div>
				<div class="bmreg-history-chart" style="--bmreg-count:<?php echo esc_attr( max( 1, count( $days ) ) ); ?>" role="group" aria-label="Riwayat Market State. Tinggi batang menunjukkan certainty dan warna menunjukkan Directional Bias.">
					<div class="bmreg-history-bars">
						<?php foreach ( $days as $index => $day ) :
							$bias = sanitize_key( (string) ( $day['directional_bias'] ?? '' ) );
							$bias = in_array( $bias, array( 'bullish', 'neutral', 'bearish' ), true ) ? $bias : 'unknown';
							$confidence = max( 0, min( 100, (int) ( $day['regime_confidence'] ?? 0 ) ) );
							$detail = array(
								'date' => (string) ( $day['date'] ?? '' ),
								'regime' => (string) ( $day['regime_label_id'] ?? '' ),
								'bias' => ucfirst( $bias ),
								'certainty' => $confidence,
							);
							$is_latest = $index === count( $days ) - 1;
						?>
							<button type="button" class="bmreg-history-bar bmreg-bias-<?php echo esc_attr( $bias ); ?><?php echo $is_latest ? ' is-selected' : ''; ?>" style="--bmreg-height:<?php echo esc_attr( max( 8, $confidence ) ); ?>%" aria-pressed="<?php echo $is_latest ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $detail['date'] . ': ' . $detail['regime'] . ', ' . $detail['bias'] . ', certainty ' . $confidence . '%' ); ?>" data-bmreg-detail="<?php echo esc_attr( wp_json_encode( $detail ) ); ?>"><span></span></button>
						<?php endforeach; ?>
					</div>
					<div class="bmreg-history-axis" aria-hidden="true">
						<?php foreach ( $days as $index => $day ) :
							$show = 0 === $index || $index === count( $days ) - 1 || 0 === ( $index % 5 ); ?>
							<span class="<?php echo $show ? 'is-visible' : ''; ?>"><?php echo $show ? esc_html( wp_date( 'd M', strtotime( $day['date'] ) ) ) : '&nbsp;'; ?></span>
						<?php endforeach; ?>
					</div>
					<p class="bmreg-history-selected" aria-live="polite"><?php echo esc_html( $latest['date'] . ' · ' . $latest['regime_label_id'] . ' · ' . ucfirst( $latest['directional_bias'] ) . ' · ' . (int) $latest['regime_confidence'] . '% certainty' ); ?></p>
				</div>
				<?php if ( (int) ( $full['available_days'] ?? 0 ) < (int) ( $full['requested_days'] ?? 0 ) ) : ?>
					<p class="bmreg-history-note"><?php echo esc_html( sprintf( __( '%d hari data resmi tersedia sejauh ini.', 'bitmomo-regime' ), (int) ( $full['available_days'] ?? 0 ) ) ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return $this->style() . $this->history_script() . trim( (string) ob_get_clean() );
	}

	private function history_script() {
		if ( $this->history_script_printed ) return '';
		$this->history_script_printed = true;
		return '<script>(function(){document.addEventListener("click",function(e){var bar=e.target.closest&&e.target.closest(".bmreg-history-bar");if(!bar)return;var chart=bar.closest(".bmreg-history-chart");if(!chart)return;var data;try{data=JSON.parse(bar.getAttribute("data-bmreg-detail")||"{}");}catch(err){return;}chart.querySelectorAll(".bmreg-history-bar").forEach(function(item){item.classList.toggle("is-selected",item===bar);item.setAttribute("aria-pressed",item===bar?"true":"false");});var out=chart.querySelector(".bmreg-history-selected");if(out){out.textContent=data.date+" · "+data.regime+" · "+data.bias+" · "+data.certainty+"% certainty";}});})();</script>';
	}

	private function style() {
		if ( $this->style_printed ) return '';
		$this->style_printed = true;
		return '<style>'
			. '.bmreg-card,.bmreg-history{font-family:inherit;box-sizing:border-box;max-width:100%;}'
			. '.bmreg-card{border:1px solid #263449;border-radius:12px;padding:16px;background:#0d1624;color:#e5edf6;}'
			. '.bmreg-empty{color:#8ea0b8;}'
			. '.bmreg-current-label,.bmreg-history-head>span{display:block;color:#8395ad;font:700 10px/1.3 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.12em;}'
			. '.bmreg-regime-id{display:block;margin-top:5px;font-size:1.2rem;}'
			. '.bmreg-meta{display:flex;gap:12px;margin-top:8px;color:#8fa1b8;font-size:.82rem;}'
			. '.bmreg-history-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:10px;}'
			. '.bmreg-history-legend{display:flex;align-items:center;gap:12px;color:#8294ae;font-size:10px;}'
			. '.bmreg-history-legend span{display:inline-flex;align-items:center;gap:5px;}.bmreg-history-legend span:before{content:"";width:6px;height:6px;border-radius:50%;background:#8192aa;}'
			. '.bmreg-history-legend .is-bullish:before{background:#35cdbb}.bmreg-history-legend .is-bearish:before{background:#ff7b6d}'
			. '.bmreg-history-chart{padding:14px 0 0;border-top:1px solid #1c2a3d;}'
			. '.bmreg-history-bars{display:grid;grid-template-columns:repeat(var(--bmreg-count),minmax(0,1fr));align-items:end;gap:3px;height:126px;border-bottom:1px solid #263b58;}'
			. '.bmreg-history-bar{position:relative;width:100%;height:100%;min-width:0;padding:0;border:0;background:transparent;cursor:pointer;}'
			. '.bmreg-history-bar span{position:absolute;right:0;bottom:0;left:0;height:var(--bmreg-height);min-height:4px;border-radius:3px 3px 1px 1px;background:#8192aa;opacity:.68;transition:opacity .15s ease,filter .15s ease;}'
			. '.bmreg-history-bar.bmreg-bias-bullish span{background:#35cdbb}.bmreg-history-bar.bmreg-bias-bearish span{background:#ff7b6d}.bmreg-history-bar.bmreg-bias-neutral span{background:#8192aa}'
			. '.bmreg-history-bar:hover span,.bmreg-history-bar:focus-visible span,.bmreg-history-bar.is-selected span{opacity:1;filter:brightness(1.12)}'
			. '.bmreg-history-bar.is-selected:after{content:"";position:absolute;right:50%;bottom:-6px;width:4px;height:4px;border-radius:50%;background:#e7eef7;transform:translateX(50%);}'
			. '.bmreg-history-bar:focus-visible{outline:2px solid #dce7f3;outline-offset:2px;border-radius:3px;}'
			. '.bmreg-history-axis{display:grid;grid-template-columns:repeat(var(--bmreg-count),minmax(0,1fr));gap:3px;margin-top:8px;}.bmreg-history-axis span{min-width:0;color:transparent;font-size:9px;text-align:center;white-space:nowrap;}.bmreg-history-axis span.is-visible{color:#8294ae;}'
			. '.bmreg-history-selected{margin:11px 0 0;color:#a6b5c8;font-size:.82rem;}'
			. '.bmreg-history-note{margin:9px 0 0;color:#8294ae;font-size:.78rem;}'
			. '.bmreg-bias-bullish{color:#35cdbb}.bmreg-bias-bearish{color:#ff7b6d}.bmreg-bias-neutral{color:#aebdd2}'
			. '@media(max-width:560px){.bmreg-history-head{align-items:flex-start;flex-direction:column;gap:8px}.bmreg-history-legend{gap:9px}.bmreg-history-bars{height:96px;gap:2px}.bmreg-history-axis{gap:2px}.bmreg-history-axis span{font-size:8px}.bmreg-history-axis span.is-visible:not(:first-child):not(:last-child){color:transparent}.bmreg-history-selected{font-size:.78rem}}'
			. '</style>';
	}
}
