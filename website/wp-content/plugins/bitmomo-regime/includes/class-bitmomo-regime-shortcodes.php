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

	/** The recovered chart/modal handler is printed at most once per page load. */
	private $history_script_printed = false;

	/** Makes each history instance's modal id unique so aria-controls stays unambiguous. */
	private $history_instance = 0;

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

	/**
	 * The `days` attribute is still accepted so existing shortcode calls do
	 * not change meaning, but it is deliberately not read here: the chart is
	 * built client-side by bitmomo-frontend.js from the full projection, so
	 * how many days are drawn is that script's decision, not this one's.
	 */
	public function render_history( $atts ) {
		unset( $atts );

		$records = Bitmomo_Regime_State_Store::instance()->get_recent( Bitmomo_Regime_History::TARGET_DAYS );
		$full    = Bitmomo_Regime_History::for_frontend( $records, Bitmomo_Regime_History::TARGET_DAYS );

		$this->history_instance++;
		$modal_id   = 'bmreg-history-modal-' . $this->history_instance;
		$heading_id = $modal_id . '-title';

		ob_start();
		?>
		<div class="bmreg-history">
			<?php if ( $full['available_days'] < $full['target_days'] ) : ?>
				<p class="bmreg-history-note">
					<?php
					printf(
						/* translators: 1: number of days of history collected so far, 2: target number of days (30) */
						esc_html__( 'Menampilkan %1$d dari %2$d hari — riwayat masih terus bertambah.', 'bitmomo-regime' ),
						(int) $full['available_days'],
						(int) $full['target_days']
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( empty( $full['days'] ) ) : ?>
				<div class="bmreg-card bmreg-empty"><?php esc_html_e( 'No regime history yet.', 'bitmomo-regime' ); ?></div>
			<?php else : ?>
				<?php
				/*
				 * The chart is NOT rendered here. bitmomo-frontend.js reads this
				 * JSON block and builds the combined bar + price-line chart
				 * (.bmreg-trend-*) client-side. Emitting bars from PHP as well
				 * would duplicate a working component; dropping this block would
				 * silently kill it. It stays exactly as the theme expects it.
				 */
				?>
				<script type="application/json" class="bmreg-history-data"><?php
					echo wp_json_encode( $full['days'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
				?></script>

				<?php
				/*
				 * The modal is the part that genuinely had no markup. Its CSS
				 * (.bmreg-history-modal, .bmreg-history-dialog,
				 * .bmreg-history-pro-link) and its delegated click handler both
				 * ship on every page load from style() and history_script()
				 * below, and until now acted on nothing.
				 */
				?>
				<button type="button"
					class="bmreg-history-open"
					aria-controls="<?php echo esc_attr( $modal_id ); ?>"
					aria-expanded="false"><?php esc_html_e( 'Lihat semua riwayat', 'bitmomo-regime' ); ?></button>

				<div class="bmreg-history-modal" id="<?php echo esc_attr( $modal_id ); ?>" aria-hidden="true">
					<div class="bmreg-history-backdrop" data-bmreg-close></div>
					<div class="bmreg-history-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" tabindex="-1">
						<button type="button" class="bmreg-history-close" data-bmreg-close aria-label="<?php esc_attr_e( 'Tutup', 'bitmomo-regime' ); ?>">&times;</button>
						<h3 id="<?php echo esc_attr( $heading_id ); ?>"><?php esc_html_e( 'Riwayat Market State & Bias', 'bitmomo-regime' ); ?></h3>
						<p class="bmreg-history-modal-note"><?php
							printf(
								/* translators: 1: number of days collected so far, 2: target number of days (30) */
								esc_html__( '%1$d dari %2$d hari tercatat. Satu entri resmi per hari.', 'bitmomo-regime' ),
								(int) $full['available_days'],
								(int) $full['target_days']
							);
						?></p>

						<ul class="bmreg-history-modal-list">
							<?php foreach ( array_reverse( $full['days'] ) as $day ) : ?>
								<li>
									<span><?php echo esc_html( self::display_date( $day['date'] ) ); ?></span>
									<em><?php echo esc_html( (string) $day['regime_label_id'] ); ?></em>
									<span class="bmreg-bias-<?php echo esc_attr( self::bias_slug( $day['directional_bias'] ) ); ?>"><?php
										echo esc_html( self::bias_label( $day['directional_bias'] ) );
									?></span>
									<b><?php echo esc_html( self::clamp_confidence( $day['regime_confidence'] ) ); ?>%</b>
								</li>
							<?php endforeach; ?>
						</ul>

						<p class="bmreg-history-pro-link">
							<a href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php
								esc_html_e( 'Data lengkap tersedia di Bitmomo Pro →', 'bitmomo-regime' );
							?></a>
						</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		$markup = trim( (string) ob_get_clean() );

		$script = empty( $full['days'] ) ? '' : $this->history_script();

		return $this->style() . $script . $markup;
	}

	/**
	 * Presentation helpers for the modal list. Kept as named methods rather
	 * than inlined so the bias vocabulary and the clamping rule have one
	 * definition, matching Bitmomo_Regime_Taxonomy rather than restating it.
	 */
	private static function bias_slug( $bias ) {
		$bias = strtolower( (string) $bias );
		return Bitmomo_Regime_Taxonomy::is_valid_bias( $bias ) ? $bias : 'unknown';
	}

	private static function bias_label( $bias ) {
		$bias = self::bias_slug( $bias );
		return 'unknown' === $bias ? '—' : ucfirst( $bias );
	}

	private static function clamp_confidence( $confidence ) {
		return (int) max( 0, min( 100, (int) $confidence ) );
	}

	private static function display_date( $date ) {
		$timestamp = strtotime( (string) $date );
		return $timestamp ? gmdate( 'd M Y', $timestamp ) : (string) $date;
	}



	/**
	 * Recovered verbatim from the live staging server, which held the only
	 * surviving copy. Delegated click handling (open / close / per-bar
	 * detail) plus Escape-to-close and focus return -- no library, printed
	 * once per page load no matter how many instances render.
	 */
	private function history_script() {
		if ( $this->history_script_printed ) {
			return '';
		}
		$this->history_script_printed = true;
		return '<script>(function(){document.addEventListener("click",function(e){var open=e.target.closest&&e.target.closest(".bmreg-history-open");if(open){var m=document.getElementById(open.getAttribute("aria-controls"));if(m){m.setAttribute("aria-hidden","false");open.setAttribute("aria-expanded","true");var d=m.querySelector(".bmreg-history-dialog");if(d)d.focus();}}var close=e.target.closest&&e.target.closest("[data-bmreg-close]");if(close){var modal=close.closest(".bmreg-history-modal");if(modal){modal.setAttribute("aria-hidden","true");var trigger=document.querySelector("[aria-controls=\""+modal.id+"\"]");if(trigger){trigger.setAttribute("aria-expanded","false");trigger.focus();}}}var bar=e.target.closest&&e.target.closest(".bmreg-history-bar");if(bar){var data;try{data=JSON.parse(bar.getAttribute("data-bmreg-detail")||"{}");}catch(err){data=null;}var selected=bar.closest(".bmreg-history-chart")&&bar.closest(".bmreg-history-chart").querySelector(".bmreg-history-selected");if(data&&selected){selected.textContent=data.date+" · "+data.regime_label_id+" · "+String(data.directional_bias||"").replace(/^./,function(c){return c.toUpperCase();})+" · "+data.regime_confidence+"%";}}});document.addEventListener("keydown",function(e){if(e.key!=="Escape")return;var m=document.querySelector(".bmreg-history-modal[aria-hidden=\"false\"]");if(m){m.setAttribute("aria-hidden","true");var t=document.querySelector("[aria-controls=\""+m.id+"\"]");if(t){t.setAttribute("aria-expanded","false");t.focus();}}});}());</script>';
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
			// The .bmreg-history-chart/-bars/-bar/-axis/-selected rules below
			// style a server-rendered chart that no longer exists: the live
			// chart is .bmreg-trend-*, built by bitmomo-frontend.js. They are
			// recovered verbatim and left in place rather than deleted on
			// inference — removing them is a separate, reviewable decision.
			. '.bmreg-history-chart{border:1px solid #263b58;border-radius:12px;padding:18px 16px 12px;margin:16px 0 12px;background:rgba(16,30,49,.65);}'
			. '.bmreg-history-bars{height:150px;display:flex;align-items:flex-end;gap:8px;border-bottom:1px solid #263b58;padding:0 2px;}'
			. '.bmreg-history-bar{position:relative;flex:1 1 0;min-width:0;height:100%;padding:0;border:0;background:transparent;cursor:pointer;}'
			. '.bmreg-history-bar span{position:absolute;left:10%;right:10%;bottom:0;height:var(--bmreg-bar-height);border-radius:5px 5px 2px 2px;background:#2dd4bf;opacity:.9;transition:opacity .15s,transform .15s;}'
			. '.bmreg-history-bar:hover span,.bmreg-history-bar:focus-visible span{opacity:1;transform:translateY(-2px);outline:none;}'
			. '.bmreg-history-bar.bmreg-bias-bearish span{background:#ff7b6d;}.bmreg-history-bar.bmreg-bias-neutral span{background:#8b9bb4;}.bmreg-history-bar.bmreg-bias-unknown span{background:#596a82;}'
			. '.bmreg-history-axis{display:flex;gap:8px;margin-top:7px;}.bmreg-history-axis span{flex:1 1 0;min-width:0;text-align:center;color:#71839f;font-size:10px;white-space:nowrap;overflow:hidden;}'
			. '.bmreg-history-selected{margin:10px 0 0;color:#9aacc4;font-size:.85em;}'
			. '.bmreg-history-open{display:inline-flex;align-items:center;justify-content:center;margin:4px 0 16px;padding:.65em 1em;border:1px solid #2dd4bf;border-radius:999px;background:transparent;color:#2dd4bf;font:inherit;cursor:pointer;}.bmreg-history-open:hover,.bmreg-history-open:focus-visible{background:rgba(45,212,191,.1);}'
			. '.bmreg-history-modal[aria-hidden="true"]{display:none;}.bmreg-history-modal{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;padding:20px;}.bmreg-history-backdrop{position:absolute;inset:0;background:rgba(2,10,20,.78);}.bmreg-history-dialog{position:relative;width:min(680px,100%);max-height:min(80vh,720px);overflow:auto;padding:24px;border:1px solid #263b58;border-radius:16px;background:#101e31;color:#e9f1f7;box-shadow:0 20px 70px rgba(0,0,0,.35);}.bmreg-history-dialog h3{margin:0 32px 8px 0;font-size:1.2em;}.bmreg-history-modal-note{margin:0 0 16px;color:#9aacc4;font-size:.9em;}.bmreg-history-close{position:absolute;top:10px;right:12px;border:0;background:transparent;color:#9aacc4;font-size:28px;line-height:1;cursor:pointer;}.bmreg-history-modal-list{list-style:none;margin:0;padding:0;display:grid;gap:6px;}.bmreg-history-modal-list li{display:grid;grid-template-columns:1.2fr 1fr .8fr auto;gap:10px;align-items:center;padding:9px 0;border-top:1px solid rgba(148,163,184,.18);font-size:.9em;}.bmreg-history-modal-list li span{color:#9aacc4;}.bmreg-history-modal-list li em{font-style:normal;}.bmreg-history-modal-list li b{font-weight:700;text-align:right;}.bmreg-bias-bullish{color:#2dd4bf;}.bmreg-bias-bearish{color:#ff7b6d;}.bmreg-bias-neutral{color:#c7d4db;}'
			. '.bmreg-history-pro-link{margin:18px 0 0;padding-top:16px;border-top:1px solid rgba(148,163,184,.18);}.bmreg-history-pro-link a{color:#2dd4bf;font-weight:700;text-decoration:none;}.bmreg-history-pro-link a:hover,.bmreg-history-pro-link a:focus-visible{text-decoration:underline;}'
			. '.bmreg-history-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.4em;}'
			. '.bmreg-history-item{display:flex;flex-wrap:wrap;gap:.6em;border:1px solid #eee;border-radius:6px;padding:.5em .75em;font-size:.9em;}'
			. '.bmreg-history-date{opacity:.6;min-width:6.5em;}'
			. '@media (max-width:480px){body.bm-regime-page main.site-main{padding-top:32px;}.bmreg-history-item{flex-direction:column;gap:.15em;}.bmreg-history-bars{gap:4px;height:120px;}.bmreg-history-axis{gap:4px;}.bmreg-history-axis span{font-size:9px;}.bmreg-history-dialog{padding:20px 16px;}.bmreg-history-modal-list li{grid-template-columns:1fr 1fr;gap:4px 8px;}.bmreg-history-modal-list li b{text-align:left;}}'
			. '</style>';
	}
}
