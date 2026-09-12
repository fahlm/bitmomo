<?php
/**
 * Plugin Name: Bitmomo BTC Intelligence
 * Plugin URI: https://bitmomo.id
 * Description: The public /btc-intelligence/ proof-and-methodology page — how Bitmomo reads BTC, and its track record. Pure presentation/consumption layer: reads the public intelligence adapter and regime-history presentation, never recalculates engine logic or exposes protected Bitmomo Pro values.
 * Version: 0.1.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-btc-intelligence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BITMOMO_BTC_INTELLIGENCE_VERSION', '0.1.1' );
define( 'BITMOMO_BTC_INTELLIGENCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_BTC_INTELLIGENCE_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-page.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-setup.php';

/**
 * Opportunity V1 presentation bridge.
 *
 * The legacy proof page is intentionally kept stable while Opportunity is
 * introduced. This bridge inserts the canonical Opportunity record and the
 * public-safe snapshot provenance at the top of the existing live-snapshot
 * panel. It consumes the public adapter only; no percentile, range, direction,
 * confidence, regime, source, or timestamp value is recalculated in
 * presentation code.
 */
final class Bitmomo_Btc_Opportunity_UI {
	public static function register() {
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'inject' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_styles' ), 30 );
	}

	public static function inject( $output, $tag, $attr, $m ) {
		if ( 'bitmomo_btc_intelligence' !== $tag || ! is_string( $output ) || false !== strpos( $output, 'bm-bi__opportunity' ) ) {
			return $output;
		}

		$panel = self::render_panel();
		if ( '' === $panel ) return $output;

		$needle = '<section class="bm-bi__section bm-bi__section--peak bm-bi__snapshot">';
		if ( false === strpos( $output, $needle ) ) return $output;

		return str_replace( $needle, $needle . $panel, $output );
	}

	private static function render_panel() {
		if ( ! class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) ) return '';
		$snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
		if ( ! is_array( $snapshot ) ) return '';

		$opportunity = is_array( $snapshot['opportunity'] ?? null ) ? $snapshot['opportunity'] : array();
		$available = 'available' === sanitize_key( (string) ( $opportunity['status'] ?? '' ) );
		$state = $available ? sanitize_key( (string) ( $opportunity['state'] ?? '' ) ) : '';
		$labels = array( 'high' => 'HIGH', 'normal' => 'NORMAL', 'low' => 'LOW' );
		$copy = array(
			'high'   => __( 'Aktivitas jangka pendek BTC relatif tinggi. Peluang pergerakan bermakna meningkat dibanding periode yang lebih tenang.', 'bitmomo-btc-intelligence' ),
			'normal' => __( 'Aktivitas jangka pendek BTC berada di sekitar tengah distribusi 14 hari terakhir.', 'bitmomo-btc-intelligence' ),
			'low'    => __( 'Aktivitas jangka pendek BTC relatif rendah. Pasar sedang lebih tenang dibanding periode yang lebih aktif.', 'bitmomo-btc-intelligence' ),
		);
		$previous = sanitize_key( (string) ( $opportunity['previous_state'] ?? '' ) );
		$changed = $available && ! empty( $opportunity['changed'] ) && isset( $labels[ $previous ], $labels[ $state ] );
		$knowledge_time = trim( (string) ( $opportunity['knowledge_time'] ?? '' ) );
		$knowledge_timestamp = $knowledge_time ? strtotime( $knowledge_time ) : false;
		$display_state = $available && isset( $labels[ $state ] ) ? $labels[ $state ] : __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
		$display_copy = $available && isset( $copy[ $state ] )
			? $copy[ $state ]
			: __( 'Opportunity sedang menunggu baseline dan data 5 menit yang memenuhi standar kualitas Bitmomo.', 'bitmomo-btc-intelligence' );

		$provenance = is_array( $snapshot['provenance'] ?? null ) ? $snapshot['provenance'] : array();
		$source = trim( (string) ( $provenance['source'] ?? '' ) );
		$as_of = trim( (string) ( $provenance['as_of'] ?? '' ) );
		$timezone = trim( (string) ( $provenance['timezone'] ?? '' ) );
		if ( '' === $source || '' === $as_of || 'Asia/Jakarta' !== $timezone ) return '';
		$as_of_timestamp = strtotime( $as_of );
		if ( ! $as_of_timestamp ) return '';
		$as_of_display = ( new DateTimeImmutable( '@' . $as_of_timestamp ) )
			->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )
			->format( 'd M Y · H:i' );

		ob_start();
		?>
		<div class="bm-bi__opportunity <?php echo esc_attr( $available ? 'is-' . $state : 'is-unavailable' ); ?>" aria-label="<?php esc_attr_e( 'Opportunity BTC saat ini', 'bitmomo-btc-intelligence' ); ?>">
			<div class="bm-bi__opportunity-head">
				<div>
					<span class="bm-bi__kicker"><?php esc_html_e( 'Opportunity', 'bitmomo-btc-intelligence' ); ?></span>
					<strong><?php echo esc_html( $display_state ); ?></strong>
				</div>
				<span class="bm-bi__opportunity-tag"><?php esc_html_e( 'Aktivitas · bukan arah', 'bitmomo-btc-intelligence' ); ?></span>
			</div>
			<p class="bm-bi__opportunity-copy"><?php echo esc_html( $display_copy ); ?></p>
			<div class="bm-bi__opportunity-meta">
				<?php if ( $changed ) : ?>
					<span><?php echo esc_html( 'CHANGED ' . $labels[ $previous ] . ' → ' . $labels[ $state ] ); ?></span>
				<?php else : ?>
					<span><?php esc_html_e( 'RELATIF TERHADAP 14 HARI TERAKHIR', 'bitmomo-btc-intelligence' ); ?></span>
				<?php endif; ?>
				<?php if ( $knowledge_timestamp ) : ?>
					<time datetime="<?php echo esc_attr( $knowledge_time ); ?>"><?php echo esc_html( ( new DateTimeImmutable( '@' . $knowledge_timestamp ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'H:i' ) . ' WIB' ); ?></time>
				<?php endif; ?>
			</div>
			<p class="bm-bi__opportunity-note"><?php esc_html_e( 'Opportunity menjawab apakah market sedang cukup aktif untuk menghasilkan pergerakan yang bermakna. Directional Bias di bawah menjawab pertanyaan yang berbeda: ke mana evidence saat ini condong.', 'bitmomo-btc-intelligence' ); ?></p>
		</div>
		<div class="bm-bi__snapshot-provenance" aria-label="<?php esc_attr_e( 'Sumber dan waktu data BTC', 'bitmomo-btc-intelligence' ); ?>">
			<span><strong><?php esc_html_e( 'SOURCE', 'bitmomo-btc-intelligence' ); ?></strong> <?php echo esc_html( $source ); ?></span>
			<time datetime="<?php echo esc_attr( $as_of ); ?>"><strong><?php esc_html_e( 'AS OF', 'bitmomo-btc-intelligence' ); ?></strong> <?php echo esc_html( $as_of_display . ' WIB' ); ?></time>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function add_styles() {
		if ( ! is_page( 'btc-intelligence' ) ) return;
		$css = <<<'CSS'
.bm-bi__opportunity{margin:-2px 0 22px;padding:0 0 22px;border-bottom:1px solid var(--bmi-border)}
.bm-bi__opportunity-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px}
.bm-bi__opportunity-head>div{min-width:0}
.bm-bi__opportunity-head strong{display:block;margin-top:6px;color:var(--bmi-teal);font:800 clamp(2rem,7vw,3.25rem)/1 var(--bmi-mono);letter-spacing:-.03em}
.bm-bi__opportunity.is-normal .bm-bi__opportunity-head strong{color:#aebdd2}
.bm-bi__opportunity.is-low .bm-bi__opportunity-head strong,.bm-bi__opportunity.is-unavailable .bm-bi__opportunity-head strong{color:var(--bmi-text-muted)}
.bm-bi__opportunity-tag{flex:0 0 auto;padding:5px 9px;border:1px solid var(--bmi-border);border-radius:999px;color:var(--bmi-text-muted);font:700 .62rem/1.3 var(--bmi-mono);letter-spacing:.07em;text-transform:uppercase}
.bm-bi__opportunity-copy{max-width:650px;margin:14px 0 0!important;color:var(--bmi-text)!important;font-size:.92rem!important;line-height:1.55!important}
.bm-bi__opportunity-meta{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:15px;color:var(--bmi-text-muted);font:700 .64rem/1.4 var(--bmi-mono);letter-spacing:.06em}
.bm-bi__opportunity-note{margin:10px 0 0!important;color:var(--bmi-text-muted)!important;font-size:.74rem!important;line-height:1.45!important}
.bm-bi__snapshot-provenance{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 20px;padding:0 0 18px;border-bottom:1px solid var(--bmi-border);color:var(--bmi-text-muted);font:700 .66rem/1.45 var(--bmi-mono);letter-spacing:.04em}
.bm-bi__snapshot-provenance strong{color:var(--bmi-text);font-weight:800}
@media(max-width:520px){.bm-bi__opportunity-head{flex-direction:column;gap:12px}.bm-bi__opportunity-tag{align-self:flex-start}.bm-bi__opportunity-meta,.bm-bi__snapshot-provenance{align-items:flex-start;flex-direction:column;gap:6px}}
CSS;
		wp_add_inline_style( 'bitmomo-btc-intelligence', $css );
	}
}

/**
 * Bootstraps the plugin's presentation and setup responsibilities.
 */
function bitmomo_btc_intelligence_init() {
	Bitmomo_Btc_Intelligence_Page::instance();
	Bitmomo_Btc_Intelligence_Setup::instance();
	Bitmomo_Btc_Opportunity_UI::register();
}
add_action( 'plugins_loaded', 'bitmomo_btc_intelligence_init' );