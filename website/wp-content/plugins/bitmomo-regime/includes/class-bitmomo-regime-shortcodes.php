<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend shortcodes for Product A (REGIME PR 3).
 *
 * `[bitmomo_market_regime]` — the current official regime as a small,
 * mobile-first card (regime, both directional axes, confidence).
 *
 * `[bitmomo_market_regime_history days="7"]` — the 30-day history
 * projection as a mobile-first vertical list. `days` accepts 1/7/14/30
 * (see Bitmomo_Regime_History::ALLOWED_DAYS); anything else falls back to
 * the class default. Never fabricates missing days — when fewer than 30
 * days of history exist yet, an honest "collected so far" note is shown
 * instead of padding the list.
 *
 * No chart library, no theme edits: all markup and its minimal styling
 * are self-contained in this file's output, printed once per page load
 * regardless of how many shortcode instances render.
 *
 * This is the ONLY new WordPress-touching surface added in PR3 besides
 * Bitmomo_Regime_Admin_Diagnostics — both are pure read/render, neither
 * writes any state.
 */
class Bitmomo_Regime_Shortcodes {

	private static $instance = null;

	private $style_printed = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
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
			return $this->style() . '<div class="bmreg-card bmreg-empty">'
				. esc_html__( 'No regime data yet.', 'bitmomo-regime' )
				. '</div>';
		}

		$regime_id = Bitmomo_Regime_Taxonomy::regime_label_id( $latest['regime'] );
		$regime_en = Bitmomo_Regime_Taxonomy::regime_label_en( $latest['regime'] );
		$bias      = isset( $latest['directional_bias'] ) ? $latest['directional_bias'] : '';
		$conf      = isset( $latest['regime_confidence'] ) ? (int) $latest['regime_confidence'] : 0;

		ob_start();
		?>
		<div class="bmreg-card bmreg-current" data-regime="<?php echo esc_attr( $latest['regime'] ); ?>">
			<div class="bmreg-regime">
				<span class="bmreg-regime-id"><?php echo esc_html( $regime_id ); ?></span>
				<span class="bmreg-regime-en">(<?php echo esc_html( $regime_en ); ?>)</span>
			</div>
			<div class="bmreg-meta">
				<span class="bmreg-bias bmreg-bias-<?php echo esc_attr( $bias ); ?>"><?php echo esc_html( ucfirst( $bias ) ); ?></span>
				<span class="bmreg-confidence"><?php echo esc_html( $conf ); ?>%</span>
			</div>
			<div class="bmreg-asof"><?php echo esc_html( isset( $latest['as_of'] ) ? $latest['as_of'] : '' ); ?></div>
		</div>
		<?php
		return $this->style() . trim( (string) ob_get_clean() );
	}

	public function render_history( $atts ) {
		$atts = shortcode_atts(
			array( 'days' => Bitmomo_Regime_History::DEFAULT_DAYS ),
			$atts,
			'bitmomo_market_regime_history'
		);

		$records    = Bitmomo_Regime_State_Store::instance()->get_recent( Bitmomo_Regime_History::TARGET_DAYS );
		$projection = Bitmomo_Regime_History::for_frontend( $records, (int) $atts['days'] );

		ob_start();
		?>
		<div class="bmreg-history">
			<?php if ( $projection['available_days'] < $projection['target_days'] ) : ?>
				<p class="bmreg-history-note">
					<?php
					printf(
						/* translators: 1: number of days of history collected so far, 2: target number of days (30) */
						esc_html__( 'Menampilkan %1$d dari %2$d hari — riwayat masih terus bertambah.', 'bitmomo-regime' ),
						(int) $projection['available_days'],
						(int) $projection['target_days']
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( empty( $projection['days'] ) ) : ?>
				<div class="bmreg-card bmreg-empty"><?php esc_html_e( 'No regime history yet.', 'bitmomo-regime' ); ?></div>
			<?php else : ?>
				<ul class="bmreg-history-list">
					<?php foreach ( $projection['days'] as $day ) : ?>
						<li class="bmreg-history-item" data-regime="<?php echo esc_attr( $day['regime'] ); ?>">
							<span class="bmreg-history-date"><?php echo esc_html( $day['date'] ); ?></span>
							<span class="bmreg-history-regime"><?php echo esc_html( $day['regime_label_id'] ); ?></span>
							<span class="bmreg-history-bias bmreg-bias-<?php echo esc_attr( $day['directional_bias'] ); ?>"><?php echo esc_html( ucfirst( (string) $day['directional_bias'] ) ); ?></span>
							<span class="bmreg-history-confidence"><?php echo esc_html( (int) $day['regime_confidence'] ); ?>%</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return $this->style() . trim( (string) ob_get_clean() );
	}

	/**
	 * Minimal, mobile-first, self-contained CSS — printed once per page
	 * load no matter how many shortcode instances render. No theme file
	 * is touched; this is the plugin's own scoped styling.
	 */
	private function style() {
		if ( $this->style_printed ) {
			return '';
		}
		$this->style_printed = true;

		return '<style>'
			. '.bmreg-card{font-family:inherit;border:1px solid #ddd;border-radius:8px;padding:12px;max-width:100%;box-sizing:border-box;}'
			. '.bmreg-regime{font-size:1.1em;font-weight:600;}'
			. '.bmreg-regime-en{font-weight:400;opacity:.7;margin-left:.25em;}'
			. '.bmreg-meta{display:flex;gap:.75em;margin-top:.4em;flex-wrap:wrap;}'
			. '.bmreg-asof{margin-top:.4em;font-size:.85em;opacity:.6;}'
			. '.bmreg-history-note{font-size:.85em;opacity:.7;}'
			. '.bmreg-history-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.4em;}'
			. '.bmreg-history-item{display:flex;flex-wrap:wrap;gap:.6em;border:1px solid #eee;border-radius:6px;padding:.5em .75em;font-size:.9em;}'
			. '.bmreg-history-date{opacity:.6;min-width:6.5em;}'
			. '@media (max-width:480px){body.bm-regime-page main.site-main{padding-top:32px;}.bmreg-history-item{flex-direction:column;gap:.15em;}}'
			. '</style>';
	}
}
