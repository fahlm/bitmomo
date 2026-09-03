<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public [bitmomo_btc_intelligence] page — /btc-intelligence/'s single
 * canonical source of truth (see Bitmomo_Btc_Intelligence_Setup for how the
 * route itself is provisioned).
 *
 * DATA SOURCE (2026-09 P0 wiring pass): this class reads ONLY the public,
 * fail-closed `Bitmomo_Public_Intelligence_Adapter` (bitmomo-ai, merged via
 * PR #69 / commit 056e488) -- never Bitmomo_AI_Intelligence's free_projection()
 * method, Bitmomo_Regime_State_Store, or Bitmomo_AI_Scorecard(_Repository) directly.
 * The adapter already wraps those canonical stores and already enforces
 * every public-safety rule this page depends on (fail-closed snapshot,
 * official-record dedup + reconstructed-record exclusion in history,
 * private-field stripping in evaluation_summary()) -- duplicating that
 * logic here would be exactly the "recompute in the frontend" this page
 * must never do. The three methods actually consumed:
 * - `Bitmomo_Public_Intelligence_Adapter::snapshot()` -- current BTC
 *   reference price, Market State, Directional Bias, canonical
 *   direction_strength (5-state), Confidence, freshness, key drivers.
 *   Returns null (fails closed) unless every field -- bias AND strength
 *   included -- resolves to a valid canonical value, so this page never
 *   has to reconcile a "bias known, strength unknown" partial state.
 * - `Bitmomo_Public_Intelligence_Adapter::evaluation_summary()` -- powers
 *   Track Record, Confidence Evaluation, Expected Range Performance,
 *   Regime Performance, and Data Quality (sections 7-11). Each metric row
 *   already carries the backend's own `sample_status` ('INSUFFICIENT
 *   SAMPLE' | 'EARLY SAMPLE' | 'ADEQUATE') -- this class only maps that
 *   exact string to an Indonesian label for display, it never recomputes
 *   or guesses the n<10/10-29/>=30 threshold itself. Entries are keyed by
 *   version (engine+classifier for directional/confidence data, model for
 *   Expected Range, classifier for Regime Performance); incompatible
 *   versions are rendered as separate groups, never merged/averaged.
 * - The bitmomo-regime plugin's own [bitmomo_market_regime_history]
 *   shortcode (section 6, Historical Market State/Bias) -- left
 *   unchanged. It already renders the same canonical
 *   Bitmomo_Regime_State_Store the adapter's own history() wraps, with
 *   its own honest "still accumulating" state; there is no public field
 *   this page is missing by keeping it, and duplicating a second history
 *   renderer against adapter_history() here would be pure churn. (The
 *   `adapter_history()` accessor below is still exposed as a documented,
 *   guarded shape for any future section that needs the day-level
 *   direction_strength the shortcode doesn't carry.)
 *
 * DATA THIS CLASS DELIBERATELY NEVER READS:
 * - Bitmomo_AI_Scorecard / Bitmomo_AI_Scorecard_Repository directly --
 *   admin-only; only the adapter's already-stripped evaluation_summary()
 *   may reach this page.
 * - Any Bitmomo_Pro_* class -- current/live Expected Range, Scenario Map,
 *   Thesis Invalidation, What Changed, or any other protected Pro value.
 *
 * Non-negotiable rendering rules:
 * - Sample status: display the backend's own sample_status string
 *   verbatim (mapped to an ID label) -- never recompute or guess it.
 * - Version grouping: entries from different version keys are rendered
 *   as separate groups, never silently merged, averaged, or displayed as
 *   one combined figure.
 * - Unknown stays unknown: a missing/null field, or the adapter itself
 *   being unavailable, renders the honest boundary state via
 *   render_adapter_pending_boundary() / render_blocked_boundary() --
 *   never a defaulted, zero, or "N/A"-as-a-number value.
 *
 * CRITICAL SEMANTIC SEPARATION -- four logically independent concepts
 * appear in render_current_snapshot():
 *   A. Direction        Bullish / Neutral / Bearish (snapshot()
 *                        ['directional_bias'] -- public-safe).
 *   B. Directional Strength   Moderate / Strong, resolved to one of the
 *                        five canonical zones via snapshot()
 *                        ['direction_strength']. Comes ONLY from that
 *                        adapter field -- never computed, inferred, or
 *                        guessed here.
 *   C. Confidence        Strength/completeness of the evidence behind
 *                        the analysis (snapshot()['confidence'], bucketed
 *                        Rendah/Sedang/Tinggi). NOT a probability of
 *                        being right.
 *   D. Market State      Regime/context (Akumulasi, Ekspansi, ...), from
 *                        snapshot()['market_state'] -- unrelated to A-C.
 * Confidence must never move the spectrum marker's position (A/B), and
 * A/B must never be derived from C. STRONG BULLISH + LOW CONFIDENCE and
 * NEUTRAL + HIGH CONFIDENCE are both valid, expected states.
 *
 * DIRECTIONAL STRENGTH -- now canonical (2026-09-03, PR #69 / commit
 * 056e488): `Bitmomo_AI_Signal_Engine`'s `direction_strength( $score )`
 * method (the renamed, now-public `score_status()`) classifies the same
 * aggregate directional score into the exact five states this page uses
 * (<=-60 strong_bearish, -59..-20 bearish, -19..19 neutral, 20..59
 * bullish, >=60 strong_bullish), and Bitmomo_AI_Intelligence's
 * free_projection() method now exposes the result as `direction_strength`. The
 * adapter's `snapshot()` fails closed to null unless that field resolves
 * to a valid canonical value, so this page's marker is ALWAYS a precise,
 * single-zone position when the section renders at all -- the previous
 * "wide" (strength-unknown) band and its "kekuatan arah segera hadir"
 * note have been removed as no longer honest-necessary; a snapshot the
 * adapter can't fully resolve now renders the section's normal
 * unavailable state instead of a partial spectrum.
 */
class Bitmomo_Btc_Intelligence_Page {

	private static $instance = null;

	/**
	 * Per-render caches for the three expected adapter methods, each
	 * loaded and guarded independently -- Codex may ship them one at a
	 * time, so this class never assumes all three exist just because one
	 * does.
	 */
	private $adapter_snapshot = null;
	private $adapter_snapshot_loaded = false;
	private $adapter_history = null;
	private $adapter_history_loaded = false;
	private $evaluation_summary = null;
	private $evaluation_summary_loaded = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'bitmomo_btc_intelligence', array( $this, 'render_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
	}

	public function maybe_enqueue_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( (string) $post->post_content, 'bitmomo_btc_intelligence' ) ) {
			return;
		}
		wp_enqueue_style(
			'bitmomo-btc-intelligence',
			BITMOMO_BTC_INTELLIGENCE_URL . 'assets/css/bitmomo-btc-intelligence.css',
			array(),
			BITMOMO_BTC_INTELLIGENCE_VERSION
		);
	}

	/**
	 * Returns Bitmomo_Public_Intelligence_Adapter::evaluation_summary()'s
	 * output if that class exists and defines the method, else null.
	 * Cached per request/render so every consuming section shares one call.
	 *
	 * Real shape (PR #69 / commit 056e488): version_policy,
	 * sample_rules, directional_evaluation[version] = {all, rolling_30,
	 * by_direction, confidence_buckets}, expected_range_evaluation
	 * {policy, version_policy, versions[version]}, regime_performance
	 * {append_only_n, transition_n, transition_frequency_pct,
	 * versions[version][regime]}, data_quality. Every metric row carries
	 * the backend's own `sample_status` string verbatim -- one of
	 * 'INSUFFICIENT SAMPLE' | 'EARLY SAMPLE' | 'ADEQUATE' -- which this
	 * class only ever maps to a display label via render_public_metric(),
	 * never recomputes.
	 */
	private function adapter_evaluation_summary() {
		if ( $this->evaluation_summary_loaded ) {
			return $this->evaluation_summary;
		}
		$this->evaluation_summary_loaded = true;

		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' )
			&& method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'evaluation_summary' ) ) {
			$this->evaluation_summary = Bitmomo_Public_Intelligence_Adapter::evaluation_summary();
		}

		return $this->evaluation_summary;
	}

	private function adapter_evaluation_summary_available() {
		return is_array( $this->adapter_evaluation_summary() );
	}

	/**
	 * Guarded/cached accessor for Bitmomo_Public_Intelligence_Adapter::
	 * snapshot(), mirroring adapter_evaluation_summary() exactly. Powers
	 * render_current_snapshot() (section 2). Fails closed to null unless
	 * every field the adapter requires -- including direction_strength --
	 * resolves to a valid canonical value.
	 */
	private function adapter_snapshot() {
		if ( $this->adapter_snapshot_loaded ) {
			return $this->adapter_snapshot;
		}
		$this->adapter_snapshot_loaded = true;

		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' )
			&& method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'snapshot' ) ) {
			$this->adapter_snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
		}

		return $this->adapter_snapshot;
	}

	private function adapter_snapshot_available() {
		return is_array( $this->adapter_snapshot() );
	}

	/**
	 * Guarded/cached accessor for Bitmomo_Public_Intelligence_Adapter::
	 * history(), mirroring adapter_evaluation_summary() exactly. Not yet
	 * consumed by any section -- section 6 (Historical Market State/Bias)
	 * deliberately keeps using the bitmomo-regime shortcode instead (see
	 * the class docblock), since that shortcode already renders the same
	 * canonical store this method wraps with no gap to fill. Kept as a
	 * guarded/cached accessor for any future section that needs the
	 * day-level direction_strength the shortcode doesn't carry; honors
	 * the same version-grouping rule -- entries from an incompatible or
	 * unrecognized version must never be merged with current ones.
	 */
	private function adapter_history() {
		if ( $this->adapter_history_loaded ) {
			return $this->adapter_history;
		}
		$this->adapter_history_loaded = true;

		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' )
			&& method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'history' ) ) {
			$this->adapter_history = Bitmomo_Public_Intelligence_Adapter::history();
		}

		return $this->adapter_history;
	}

	private function adapter_history_available() {
		return is_array( $this->adapter_history() );
	}

	/**
	 * Shared "is the adapter data this section needs ready yet" branch,
	 * used by every section listed under PUBLIC ADAPTER DEPENDENCY above.
	 * Renders the same honest "Belum Tersedia" boundary either way --
	 * this never fabricates a number and never assumes a shape the
	 * adapter hasn't confirmed.
	 *
	 * $unavailable_note is shown when the relevant adapter method itself
	 * isn't callable yet (today, always -- no adapter class exists, so
	 * every call site's currently-rendered copy is preserved exactly by
	 * passing the same note this section always used). $partial_note,
	 * when given, is shown instead once the adapter method exists but has
	 * nothing summarized for this specific section yet; omitting it just
	 * reuses $unavailable_note in both states, which is intentional at
	 * call sites that don't have distinct wording yet.
	 */
	private function render_adapter_pending_boundary( $available, $unavailable_note = '', $partial_note = '' ) {
		if ( $available ) {
			$this->render_blocked_boundary( '' !== $partial_note ? $partial_note : $unavailable_note );
		} else {
			$this->render_blocked_boundary( $unavailable_note );
		}
	}

	/**
	 * The exact backend sample_status strings computed by the private
	 * P1 scorecard's own sample-status method (reached only through the
	 * adapter's already-public evaluation_summary(), never called
	 * directly by this class) -- 'INSUFFICIENT SAMPLE' | 'EARLY SAMPLE' |
	 * 'ADEQUATE' -- mapped 1:1 to their Indonesian display label. This is
	 * presentation only: it never re-derives the status from `n`, and an
	 * unrecognized string is shown verbatim rather than coerced into one
	 * of the three, so a future backend status value is never silently
	 * mislabeled.
	 */
	private function public_sample_status_label( $sample_status ) {
		$sample_status = (string) $sample_status;
		$labels        = array(
			'ADEQUATE'            => __( 'Sampel Memadai', 'bitmomo-btc-intelligence' ),
			'EARLY SAMPLE'        => __( 'Sampel Awal', 'bitmomo-btc-intelligence' ),
			'INSUFFICIENT SAMPLE' => __( 'Sampel Belum Cukup', 'bitmomo-btc-intelligence' ),
		);
		if ( isset( $labels[ $sample_status ] ) ) {
			return $labels[ $sample_status ];
		}
		return '' !== $sample_status ? $sample_status : __( 'Status sampel tidak diketahui', 'bitmomo-btc-intelligence' );
	}

	/**
	 * Renders one evaluation_summary() metric row honestly: the headline
	 * figure (only the exact field named by $value_field -- never a
	 * recomputed derivative of other fields), the backend's own `n` and
	 * `sample_status` verbatim. A missing/null headline value renders as
	 * an honest "belum ada hasil" note, never a fabricated 0% or N/A
	 * number, matching the "unknown stays unknown" rule.
	 */
	private function render_public_metric( $label, $metric, $value_field = 'accuracy_pct', $suffix = '%' ) {
		$metric = is_array( $metric ) ? $metric : array();
		$n      = isset( $metric['n'] ) ? (int) $metric['n'] : 0;
		$value  = array_key_exists( $value_field, $metric ) ? $metric[ $value_field ] : null;
		$status = isset( $metric['sample_status'] ) ? (string) $metric['sample_status'] : '';
		?>
		<div class="bm-bi__metric-stat">
			<?php if ( '' !== $label ) : ?>
				<span class="bm-bi__kicker"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
			<strong>
				<?php
				if ( null !== $value && is_numeric( $value ) ) {
					echo esc_html( number_format_i18n( (float) $value, 1 ) . $suffix );
				} else {
					esc_html_e( 'Belum ada hasil', 'bitmomo-btc-intelligence' );
				}
				?>
			</strong>
			<span class="bm-bi__metric-meta">
				<?php
				printf(
					/* translators: 1: observation count, 2: backend sample-status label */
					esc_html__( 'n=%1$d · %2$s', 'bitmomo-btc-intelligence' ),
					$n,
					esc_html( $this->public_sample_status_label( $status ) )
				);
				?>
			</span>
		</div>
		<?php
	}

	public function render_page( $atts ) {
		ob_start();
		echo '<div class="bm-bi">';
		$this->render_hero();
		$this->render_current_snapshot();
		$this->render_how_it_works();
		$this->render_five_axes();
		$this->render_how_to_read();
		$this->render_historical_regime();
		$this->render_track_record();
		$this->render_confidence_evaluation();
		$this->render_expected_range_performance();
		$this->render_regime_performance();
		$this->render_data_quality();
		$this->render_methodology();
		$this->render_pro_cta();
		echo '</div>';
		return ob_get_clean();
	}

	/* =====================================================================
	 * 1. HERO
	 * ================================================================== */
	private function render_hero() {
		?>
		<section class="bm-bi__hero">
			<p class="bm-bi__eyebrow">BTC INTELLIGENCE</p>
			<h1 class="bm-bi__hero-title"><?php esc_html_e( 'Pahami BTC dalam konteks.', 'bitmomo-btc-intelligence' ); ?></h1>
			<p class="bm-bi__hero-subhead"><?php esc_html_e( 'Lima axis. Satu framework. Track record terbuka.', 'bitmomo-btc-intelligence' ); ?></p>
			<p class="bm-bi__hero-micro"><?php esc_html_e( 'Bukan sinyal beli/jual. Bukan saran keuangan.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 2. CURRENT BTC INTELLIGENCE -- wired to
	 * Bitmomo_Public_Intelligence_Adapter::snapshot() (PR #69 / 056e488)
	 * ================================================================== */
	private function render_current_snapshot() {
		$snapshot  = $this->adapter_snapshot();
		$available = is_array( $snapshot );

		// snapshot() fails closed to null unless directional_bias AND
		// direction_strength both resolve to valid canonical values, so
		// there is no partial "bias known, strength unknown" state left
		// to reconcile here -- $available already covers it.
		$bias_labels = array(
			'bullish' => __( 'Bullish', 'bitmomo-btc-intelligence' ),
			'bearish' => __( 'Bearish', 'bitmomo-btc-intelligence' ),
			'neutral' => __( 'Neutral', 'bitmomo-btc-intelligence' ),
		);
		// Directional Strength labels mirror the homepage's exact wording
		// (template-parts/home-hero.php's $bm_direction_label) so the same
		// canonical value reads identically everywhere on the site.
		$strength_labels = array(
			'strong_bearish' => __( 'Strong Bearish', 'bitmomo-btc-intelligence' ),
			'bearish'        => __( 'Moderate Bearish', 'bitmomo-btc-intelligence' ),
			'neutral'        => __( 'Neutral', 'bitmomo-btc-intelligence' ),
			'bullish'        => __( 'Moderate Bullish', 'bitmomo-btc-intelligence' ),
			'strong_bullish' => __( 'Strong Bullish', 'bitmomo-btc-intelligence' ),
		);
		// Zone index (0-4), matching Bitmomo_AI_Signal_Engine::
		// direction_strength()'s own enum exactly -- this frontend does
		// not invent these names or boundaries, only positions them.
		$strength_zone_index = array(
			'strong_bearish' => 0,
			'bearish'        => 1,
			'neutral'        => 2,
			'bullish'        => 3,
			'strong_bullish' => 4,
		);

		$raw_bias     = $available ? sanitize_key( (string) $snapshot['directional_bias'] ) : '';
		$raw_strength = $available ? sanitize_key( (string) $snapshot['direction_strength'] ) : '';
		$resolved     = $available && isset( $bias_labels[ $raw_bias ] ) && isset( $strength_zone_index[ $raw_strength ] );

		$regime_label = ( $resolved && class_exists( 'Bitmomo_Regime_Taxonomy' ) && ! empty( $snapshot['market_state'] ) )
			? Bitmomo_Regime_Taxonomy::regime_label_id( $snapshot['market_state'] )
			: __( 'Belum tersedia', 'bitmomo-btc-intelligence' );

		// Confidence: displayed only via the adapter's own 'high'/
		// 'medium'/'low' classification (confidence_label() in the
		// adapter, thresholds 70/40 -- this frontend maps that string to
		// an ID label, it never re-derives the threshold from the raw
		// value itself).
		$confidence_key_map = array( 'high' => 'tinggi', 'medium' => 'sedang', 'low' => 'rendah' );
		$confidence_display  = array(
			'high'   => __( 'Tinggi', 'bitmomo-btc-intelligence' ),
			'medium' => __( 'Sedang', 'bitmomo-btc-intelligence' ),
			'low'    => __( 'Rendah', 'bitmomo-btc-intelligence' ),
		);
		$confidence_raw   = $resolved ? sanitize_key( (string) ( $snapshot['confidence']['label'] ?? '' ) ) : '';
		$confidence_key   = $confidence_key_map[ $confidence_raw ] ?? 'rendah';
		$confidence_label = $confidence_display[ $confidence_raw ] ?? $confidence_display['low'];

		$key_drivers = $resolved && is_array( $snapshot['key_drivers'] ?? null )
			? array_values( array_filter( array_map( 'strval', $snapshot['key_drivers'] ) ) )
			: array();

		$updated = ( $resolved && ! empty( $snapshot['freshness']['timestamp_iso'] ) )
			? strtotime( (string) $snapshot['freshness']['timestamp_iso'] )
			: false;
		?>
		<section class="bm-bi__section bm-bi__section--peak bm-bi__snapshot">
			<p class="bm-bi__eyebrow">KONDISI BTC SAAT INI</p>
			<?php if ( ! $resolved ) : ?>
				<div class="bm-bi__snapshot-unavailable" role="status">
					<span class="bm-bi__badge bm-bi__badge--muted"><?php esc_html_e( 'Belum tersedia', 'bitmomo-btc-intelligence' ); ?></span>
					<h2><?php esc_html_e( 'Update BTC terbaru belum tersedia.', 'bitmomo-btc-intelligence' ); ?></h2>
					<p><?php esc_html_e( 'Sistem sedang menunggu data yang memenuhi standar kualitas Bitmomo.', 'bitmomo-btc-intelligence' ); ?></p>
				</div>
			<?php else : ?>
				<div class="bm-bi__snapshot-top">
					<div class="bm-bi__metric bm-bi__metric--price">
						<span class="bm-bi__kicker"><?php esc_html_e( 'BTC Reference', 'bitmomo-btc-intelligence' ); ?></span>
						<strong>$<?php echo esc_html( number_format_i18n( (float) $snapshot['btc_reference_price'], 0 ) ); ?></strong>
					</div>
					<div class="bm-bi__metric">
						<span class="bm-bi__kicker"><?php esc_html_e( 'Market State', 'bitmomo-btc-intelligence' ); ?></span>
						<strong><?php echo esc_html( $regime_label ); ?></strong>
					</div>
				</div>

				<?php
				/**
				 * MARKET DIRECTION SPECTRUM -- the hero intelligence
				 * instrument. Spatial position always comes from the
				 * canonical direction_strength zone resolved above; it is
				 * never influenced by Confidence. Confidence is rendered
				 * as a separate visual channel (marker opacity + an
				 * explicit text badge) below the track, never as spectrum
				 * position -- see the class docblock's CRITICAL SEMANTIC
				 * SEPARATION note. The marker is always precise/
				 * single-zone: the adapter's fail-closed contract means
				 * this branch never runs with strength unresolved.
				 */
				$zone           = $strength_zone_index[ $raw_strength ];
				$marker_left    = $zone * 20;
				$marker_classes = array( 'bm-bi__spectrum-marker', 'bm-bi__spectrum-marker--precise', 'is-' . $raw_bias, 'is-confidence-' . $confidence_key );
				?>
				<div class="bm-bi__spectrum">
					<p class="bm-bi__spectrum-label"><?php esc_html_e( 'MARKET DIRECTION SPECTRUM', 'bitmomo-btc-intelligence' ); ?></p>
					<div class="bm-bi__spectrum-track">
						<div class="bm-bi__spectrum-zone bm-bi__spectrum-zone--strong-bear"></div>
						<div class="bm-bi__spectrum-zone bm-bi__spectrum-zone--bear"></div>
						<div class="bm-bi__spectrum-zone bm-bi__spectrum-zone--neutral"></div>
						<div class="bm-bi__spectrum-zone bm-bi__spectrum-zone--bull"></div>
						<div class="bm-bi__spectrum-zone bm-bi__spectrum-zone--strong-bull"></div>
						<div class="<?php echo esc_attr( implode( ' ', $marker_classes ) ); ?>" style="left:<?php echo esc_attr( $marker_left ); ?>%;width:20%;" role="img" aria-label="<?php echo esc_attr( sprintf(
							/* translators: 1: directional strength label, 2: confidence label */
							__( 'Arah %1$s, Confidence %2$s', 'bitmomo-btc-intelligence' ),
							$strength_labels[ $raw_strength ],
							$confidence_label
						) ); ?>"></div>
					</div>
					<div class="bm-bi__spectrum-scale" aria-hidden="true">
						<span><?php esc_html_e( 'Strong Bear', 'bitmomo-btc-intelligence' ); ?></span>
						<span><?php esc_html_e( 'Bear', 'bitmomo-btc-intelligence' ); ?></span>
						<span><?php esc_html_e( 'Neutral', 'bitmomo-btc-intelligence' ); ?></span>
						<span><?php esc_html_e( 'Bull', 'bitmomo-btc-intelligence' ); ?></span>
						<span><?php esc_html_e( 'Strong Bull', 'bitmomo-btc-intelligence' ); ?></span>
					</div>
					<p class="bm-bi__spectrum-readout is-<?php echo esc_attr( $raw_bias ); ?>">
						<strong><?php echo esc_html( $strength_labels[ $raw_strength ] ); ?></strong>
					</p>
					<div class="bm-bi__confidence-badge">
						<span class="bm-bi__kicker"><?php esc_html_e( 'Confidence', 'bitmomo-btc-intelligence' ); ?></span>
						<span class="bm-bi__confidence-meter is-<?php echo esc_attr( $confidence_key ); ?>" aria-hidden="true"><i></i><i></i><i></i></span>
						<strong><?php echo esc_html( $confidence_label ); ?></strong>
					</div>
					<p class="bm-bi__confidence-note"><?php esc_html_e( 'Confidence menunjukkan seberapa kuat keyakinan sistem terhadap insight saat ini berdasarkan konsistensi dan kualitas evidence yang mendukungnya. Confidence bukan probabilitas keberhasilan.', 'bitmomo-btc-intelligence' ); ?></p>
				</div>

				<div class="bm-bi__driver">
					<span class="bm-bi__kicker"><?php esc_html_e( 'Faktor Utama', 'bitmomo-btc-intelligence' ); ?></span>
					<?php if ( $key_drivers ) : ?>
						<ul class="bm-bi__driver-list">
							<?php foreach ( $key_drivers as $driver ) : ?>
								<li><?php echo esc_html( $driver ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="bm-bi__driver-empty"><?php esc_html_e( 'Faktor utama belum tersedia untuk snapshot ini.', 'bitmomo-btc-intelligence' ); ?></p>
					<?php endif; ?>
				</div>

				<p class="bm-bi__freshness">
					<?php
					if ( $updated ) {
						printf(
							/* translators: %s: human-readable local date/time of the snapshot */
							esc_html__( 'Data terkini — diperbarui %s.', 'bitmomo-btc-intelligence' ),
							esc_html( date_i18n( 'd M Y, H:i', $updated ) )
						);
					} else {
						esc_html_e( 'Waktu pembaruan belum tersedia.', 'bitmomo-btc-intelligence' );
					}
					?>
				</p>
			<?php endif; ?>
			<p class="bm-bi__snapshot-note"><?php esc_html_e( 'Market State dan Directional Bias adalah dua hal berbeda — lihat "Cara Membaca Bitmomo Intelligence" di bawah.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 3. HOW BITMOMO READS THE MARKET -- static
	 * ================================================================== */
	private function render_how_it_works() {
		?>
		<section class="bm-bi__section--editorial bm-bi__how">
			<h2 class="bm-bi__section-title">BAGAIMANA BITMOMO MEMBACA PASAR</h2>
			<p><?php esc_html_e( 'Setiap analisis BTC dibangun dari lima axis intelligence yang deterministik, digabungkan menjadi satu Market State, satu Directional Bias, dan satu level Confidence — bukan lima opini terpisah yang harus kamu tafsirkan sendiri.', 'bitmomo-btc-intelligence' ); ?></p>
			<p class="bm-bi__disclaimer-line"><?php esc_html_e( 'Ini bukan lima "agent" AI yang independen — ini lima dimensi deterministik, proses yang sama setiap kali.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 4. FIVE INTELLIGENCE AXES -- static
	 * ================================================================== */
	private function render_five_axes() {
		$axes = array(
			array(
				'label'   => 'Direction',
				'q'       => 'Ke arah mana tekanan harga BTC saat ini lebih mungkin condong?',
				'body'    => 'Menangkap momentum dan arah pergerakan harga jangka pendek — axis paling langsung terkait dengan Directional Bias.',
			),
			array(
				'label'   => 'Volatility',
				'q'       => 'Seberapa besar dan cepat harga BTC bergerak saat ini dibanding biasanya?',
				'body'    => 'Menangkap intensitas pergerakan pasar. Volatilitas tinggi dapat mengubah seberapa lebar rentang harga yang realistis, dan seberapa cepat kondisi bisa berubah.',
			),
			array(
				'label'   => 'Carry',
				'q'       => 'Berapa besar biaya atau insentif untuk menahan posisi leverage saat ini (funding, cost-of-carry)?',
				'body'    => 'Menangkap tekanan struktural dari pasar derivatif/leverage. Carry yang ekstrem bisa jadi sinyal struktural meningkatnya risiko pembalikan atau unwind posisi.',
			),
			array(
				'label'   => 'Structure',
				'q'       => 'Bagaimana bentuk struktur harga BTC saat ini — level mana yang berfungsi sebagai support/resistance?',
				'body'    => 'Menangkap konteks teknikal/struktural di balik pergerakan harga — membantu membedakan level yang berarti dari noise jangka pendek.',
			),
			array(
				'label'   => 'Crowding',
				'q'       => 'Seberapa banyak pelaku pasar sudah berada di sisi yang sama saat ini?',
				'body'    => 'Menangkap konsentrasi positioning. Crowding ekstrem dapat meningkatkan risiko pergerakan tajam ke arah berlawanan jika posisi mulai dipaksa keluar.',
			),
		);
		?>
		<section class="bm-bi__section--editorial bm-bi__axes">
			<h2 class="bm-bi__section-title">LIMA INTELLIGENCE AXES</h2>
			<div class="bm-bi__axes-grid">
				<?php foreach ( $axes as $axis ) : ?>
					<div class="bm-bi__axis-card">
						<h3><?php echo esc_html( $axis['label'] ); ?></h3>
						<p class="bm-bi__axis-q"><?php echo esc_html( $axis['q'] ); ?></p>
						<p><?php echo esc_html( $axis['body'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="bm-bi__signature"><?php esc_html_e( 'Prosesnya sama setiap kali: konsisten, deterministik, tidak berubah-ubah tergantung mood pasar.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 5. HOW TO READ BITMOMO INTELLIGENCE -- static, locked vocabulary
	 * ================================================================== */
	private function render_how_to_read() {
		?>
		<section class="bm-bi__section--editorial bm-bi__section--quiet bm-bi__how-to-read">
			<h2 class="bm-bi__section-title">CARA MEMBACA BITMOMO INTELLIGENCE</h2>

			<h3><?php esc_html_e( 'Market State bukan Directional Bias', 'bitmomo-btc-intelligence' ); ?></h3>
			<p><?php esc_html_e( 'Market State menjelaskan struktur kondisi pasar saat ini — Akumulasi, Ekspansi, Distribusi, Kapitulasi, atau Transisi. Directional Bias menjelaskan arah yang lebih mungkin — Bullish, Neutral, atau Bearish. Keduanya bisa berbeda, dan Bitmomo tidak pernah menggabungkan atau menyimpulkan salah satu dari yang lain.', 'bitmomo-btc-intelligence' ); ?></p>

			<h3><?php esc_html_e( 'Confidence bukan probabilitas', 'bitmomo-btc-intelligence' ); ?></h3>
			<p><?php esc_html_e( 'Confidence ditampilkan sebagai Rendah, Sedang, atau Tinggi — kekuatan bukti di balik analisis, bukan probabilitas statistik. "Tinggi" berarti kelima axis mengarah ke kesimpulan yang sama, bukan "70% kemungkinan benar."', 'bitmomo-btc-intelligence' ); ?></p>

			<p class="bm-bi__disclaimer-line"><?php esc_html_e( 'Bukan sinyal beli/jual, bukan target harga, bukan jaminan hasil — ini bantuan memahami konteks, bukan pengganti keputusan kamu.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 6. HISTORICAL MARKET STATE / BIAS -- wired via the regime plugin's
	 * own [bitmomo_market_regime_history] shortcode. This class never
	 * reads Bitmomo_Regime_State_Store's raw records itself for history.
	 * ================================================================== */
	private function render_historical_regime() {
		?>
		<section class="bm-bi__section--editorial bm-bi__history">
			<h2 class="bm-bi__section-title">RIWAYAT MARKET STATE &amp; BIAS</h2>
			<p><?php esc_html_e( 'Setiap analisis dicatat dengan timestamp — bukan ditulis ulang setelah kejadian. Riwayat berikut menunjukkan Market State dan Bias yang sudah tercatat.', 'bitmomo-btc-intelligence' ); ?></p>
			<div class="bm-bi__history-embed">
				<?php
				if ( shortcode_exists( 'bitmomo_market_regime_history' ) ) {
					echo do_shortcode( '[bitmomo_market_regime_history days="30"]' );
				} else {
					echo '<div class="bm-bi__blocked"><p>' . esc_html__( 'Riwayat Market State belum tersedia.', 'bitmomo-btc-intelligence' ) . '</p></div>';
				}
				?>
			</div>
			<p class="bm-bi__history-note"><?php esc_html_e( 'Riwayat terus bertambah — makin panjang, makin bisa dipercaya evaluasi performa di bawah.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Shared boundary renderer for every section that depends on the
	 * not-yet-built public evaluation adapter. Renders the real section
	 * shell (heading + framing copy, both approved/locked) plus an honest
	 * "belum tersedia" note -- never a fabricated number, never the
	 * INSUFFICIENT SAMPLE label (that's a specific backend-owned status
	 * for a *known* small n; here the adapter itself doesn't exist yet,
	 * which is a different, more upstream kind of "not available").
	 */
	private function render_blocked_boundary( $note = '' ) {
		if ( '' === $note ) {
			$note = __( 'Data evaluasi publik untuk bagian ini belum tersedia — tidak ada angka yang direkayasa di sini.', 'bitmomo-btc-intelligence' );
		}
		echo '<div class="bm-bi__blocked" role="status">';
		echo '<span class="bm-bi__badge bm-bi__badge--muted">' . esc_html__( 'Belum Tersedia', 'bitmomo-btc-intelligence' ) . '</span>';
		echo '<p>' . esc_html( $note ) . '</p>';
		echo '</div>';
	}

	/**
	 * Small "these are shown separately, never merged" note -- rendered
	 * only when evaluation_summary() actually carries more than one
	 * version for the section calling it, so the version-grouping rule
	 * is visible on the page itself, not just honored silently.
	 */
	private function render_version_separation_note( array $versions ) {
		if ( count( $versions ) <= 1 ) {
			return;
		}
		echo '<p class="bm-bi__version-note">' . esc_html__( 'Versi engine/classifier yang berbeda tidak digabungkan -- setiap versi ditampilkan terpisah.', 'bitmomo-btc-intelligence' ) . '</p>';
	}

	/* =====================================================================
	 * 7. PERFORMANCE / TRACK RECORD -- wired to
	 * Bitmomo_Public_Intelligence_Adapter::evaluation_summary()
	 * ['directional_evaluation'] (PR #69 / 056e488)
	 * ================================================================== */
	private function render_track_record() {
		$summary  = $this->adapter_evaluation_summary();
		$versions = is_array( $summary['directional_evaluation'] ?? null ) ? $summary['directional_evaluation'] : array();
		?>
		<section class="bm-bi__section--editorial bm-bi__track-record">
			<h2 class="bm-bi__section-title">TRACK RECORD</h2>
			<p><?php esc_html_e( 'Performa Bitmomo Intelligence dipecah berdasarkan tiga kategori berikut, dibandingkan dengan apa yang benar-benar terjadi.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( empty( $versions ) ) : ?>
				<?php
				$this->render_adapter_pending_boundary(
					$this->adapter_evaluation_summary_available(),
					'',
					__( 'Data ringkasan Track Record belum tersedia.', 'bitmomo-btc-intelligence' )
				);
				?>
			<?php else : ?>
				<?php $this->render_version_separation_note( $versions ); ?>
				<?php foreach ( $versions as $version => $metrics ) : ?>
					<div class="bm-bi__version-group">
						<?php if ( count( $versions ) > 1 ) : ?>
							<p class="bm-bi__version-tag"><?php echo esc_html( sprintf( __( 'Versi: %s', 'bitmomo-btc-intelligence' ), (string) $version ) ); ?></p>
						<?php endif; ?>
						<div class="bm-bi__metric-row">
							<?php
							$this->render_public_metric( __( 'Semua Waktu', 'bitmomo-btc-intelligence' ), $metrics['all'] ?? array() );
							$this->render_public_metric( __( 'Rolling 30', 'bitmomo-btc-intelligence' ), $metrics['rolling_30'] ?? array() );
							$by_direction = is_array( $metrics['by_direction'] ?? null ) ? $metrics['by_direction'] : array();
							foreach ( array(
								'bullish' => __( 'Bullish', 'bitmomo-btc-intelligence' ),
								'bearish' => __( 'Bearish', 'bitmomo-btc-intelligence' ),
								'neutral' => __( 'Neutral', 'bitmomo-btc-intelligence' ),
							) as $key => $bias_label ) {
								$this->render_public_metric( $bias_label, $by_direction[ $key ] ?? array() );
							}
							?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
			<ul class="bm-bi__breakdown-labels">
				<li><?php esc_html_e( 'Akurasi berdasarkan Bias (Bullish / Neutral / Bearish)', 'bitmomo-btc-intelligence' ); ?></li>
				<li><?php esc_html_e( 'Akurasi berdasarkan sesi (Morning vs US Session)', 'bitmomo-btc-intelligence' ); ?></li>
				<li><?php esc_html_e( 'Akurasi berdasarkan Market State / regime', 'bitmomo-btc-intelligence' ); ?></li>
			</ul>
			<?php if ( ! empty( $versions ) ) : ?>
				<p class="bm-bi__editorial-note"><?php esc_html_e( 'Breakdown per sesi (Morning vs US Session) belum tersedia secara publik -- breakdown per Market State/regime ada di bagian "Performa Berdasarkan Regime" di bawah.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	/* =====================================================================
	 * 8. CONFIDENCE / OUTCOME EVALUATION -- wired to evaluation_summary()
	 * ['directional_evaluation'][version]['confidence_buckets'] (backend-
	 * owned bucket ranges -- this class never re-buckets Confidence
	 * itself, it only renders whatever buckets the backend returns)
	 * ================================================================== */
	private function render_confidence_evaluation() {
		$summary  = $this->adapter_evaluation_summary();
		$versions = is_array( $summary['directional_evaluation'] ?? null ) ? $summary['directional_evaluation'] : array();
		?>
		<section class="bm-bi__section--editorial bm-bi__confidence-eval">
			<h2 class="bm-bi__section-title">HUBUNGAN CONFIDENCE DENGAN AKURASI</h2>
			<p class="bm-bi__confidence-headline"><?php esc_html_e( 'Apakah Confidence yang lebih tinggi benar-benar menghasilkan akurasi yang lebih konsisten?', 'bitmomo-btc-intelligence' ); ?></p>
			<p><?php esc_html_e( 'Kami membandingkan hasil aktual di setiap level Confidence untuk melihat apakah perbedaan tingkat keyakinan sistem benar-benar tercermin pada performanya.', 'bitmomo-btc-intelligence' ); ?></p>
			<p class="bm-bi__disclaimer-line"><?php esc_html_e( 'Confidence menunjukkan seberapa kuat keyakinan sistem terhadap insight saat ini berdasarkan konsistensi dan kualitas evidence yang mendukungnya. Confidence bukan probabilitas keberhasilan.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( empty( $versions ) ) : ?>
				<div class="bm-bi__confidence-buckets">
					<?php foreach ( array( 'Rendah', 'Sedang', 'Tinggi' ) as $bucket ) : ?>
						<div class="bm-bi__confidence-bucket">
							<span class="bm-bi__kicker"><?php echo esc_html( $bucket ); ?></span>
							<?php $this->render_adapter_pending_boundary( $this->adapter_evaluation_summary_available(), __( 'Belum tersedia.', 'bitmomo-btc-intelligence' ) ); ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<?php $this->render_version_separation_note( $versions ); ?>
				<?php foreach ( $versions as $version => $metrics ) : ?>
					<div class="bm-bi__version-group">
						<?php if ( count( $versions ) > 1 ) : ?>
							<p class="bm-bi__version-tag"><?php echo esc_html( sprintf( __( 'Versi: %s', 'bitmomo-btc-intelligence' ), (string) $version ) ); ?></p>
						<?php endif; ?>
						<div class="bm-bi__confidence-buckets">
							<?php
							$buckets = is_array( $metrics['confidence_buckets'] ?? null ) ? $metrics['confidence_buckets'] : array();
							if ( empty( $buckets ) ) {
								$this->render_adapter_pending_boundary( true, __( 'Belum tersedia.', 'bitmomo-btc-intelligence' ) );
							}
							foreach ( $buckets as $bucket ) :
								$range = is_array( $bucket ) && isset( $bucket['range'] ) ? (string) $bucket['range'] : __( 'Rentang tidak diketahui', 'bitmomo-btc-intelligence' );
								?>
								<div class="bm-bi__confidence-bucket">
									<span class="bm-bi__kicker"><?php echo esc_html( $range ); ?></span>
									<?php $this->render_public_metric( '', $bucket ); ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/* =====================================================================
	 * 9. EXPECTED RANGE PERFORMANCE -- historical hit-rate only (public),
	 * wired to evaluation_summary()['expected_range_evaluation']; current/
	 * live Expected Range stays Pro-only, never rendered here.
	 * ================================================================== */
	private function render_expected_range_performance() {
		$summary  = $this->adapter_evaluation_summary();
		$range    = is_array( $summary['expected_range_evaluation'] ?? null ) ? $summary['expected_range_evaluation'] : array();
		$versions = is_array( $range['versions'] ?? null ) ? $range['versions'] : array();
		?>
		<section class="bm-bi__section--editorial bm-bi__expected-range">
			<h2 class="bm-bi__section-title">PERFORMA EXPECTED RANGE</h2>
			<p><?php esc_html_e( 'Expected Range adalah proyeksi rentang harga BTC dari Bitmomo Pro. Bagian ini menunjukkan seberapa sering harga aktual berada di dalam rentang tersebut secara historis.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( empty( $versions ) ) : ?>
				<?php $this->render_adapter_pending_boundary( $this->adapter_evaluation_summary_available() ); ?>
			<?php else : ?>
				<?php $this->render_version_separation_note( $versions ); ?>
				<?php foreach ( $versions as $version => $metric ) : ?>
					<div class="bm-bi__version-group">
						<?php if ( count( $versions ) > 1 ) : ?>
							<p class="bm-bi__version-tag"><?php echo esc_html( sprintf( __( 'Versi: %s', 'bitmomo-btc-intelligence' ), (string) $version ) ); ?></p>
						<?php endif; ?>
						<div class="bm-bi__metric-row">
							<?php
							$this->render_public_metric( __( 'Range Hit Rate', 'bitmomo-btc-intelligence' ), $metric, 'range_hit_pct' );
							$this->render_public_metric( __( 'Breach Bawah', 'bitmomo-btc-intelligence' ), $metric, 'low_breach_pct' );
							$this->render_public_metric( __( 'Breach Atas', 'bitmomo-btc-intelligence' ), $metric, 'high_breach_pct' );
							?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
			<p class="bm-bi__editorial-note"><?php esc_html_e( 'Rentang yang berlaku hari ini hanya untuk anggota Bitmomo Pro — bagian ini hanya menunjukkan akurasi historisnya. Evaluasi ini hanya memakai rentang asli yang dibekukan sebelum outcome diketahui.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 10. PERFORMANCE BY MARKET REGIME -- wired to evaluation_summary()
	 * ['regime_performance']
	 * ================================================================== */
	private function render_regime_performance() {
		$summary    = $this->adapter_evaluation_summary();
		$regime_eval = is_array( $summary['regime_performance'] ?? null ) ? $summary['regime_performance'] : array();
		$versions   = is_array( $regime_eval['versions'] ?? null ) ? $regime_eval['versions'] : array();
		?>
		<section class="bm-bi__section--editorial bm-bi__regime-performance">
			<h2 class="bm-bi__section-title">PERFORMA BERDASARKAN REGIME</h2>
			<p><?php esc_html_e( 'Performa Bitmomo Intelligence bisa berbeda di tiap regime pasar. Bagian ini memecah akurasi per kategori Market State.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( empty( $versions ) ) : ?>
				<?php $this->render_adapter_pending_boundary( $this->adapter_evaluation_summary_available() ); ?>
			<?php else : ?>
				<?php
				if ( isset( $regime_eval['transition_frequency_pct'] ) && null !== $regime_eval['transition_frequency_pct'] ) {
					printf(
						'<p class="bm-bi__editorial-note">%s</p>',
						esc_html(
							sprintf(
								/* translators: 1: append-only observation count, 2: transition frequency percentage */
								__( 'Berdasarkan %1$d observasi regime append-only; frekuensi transisi %2$s%%.', 'bitmomo-btc-intelligence' ),
								(int) ( $regime_eval['append_only_n'] ?? 0 ),
								number_format_i18n( (float) $regime_eval['transition_frequency_pct'], 1 )
							)
						)
					);
				}
				$this->render_version_separation_note( $versions );
				?>
				<?php foreach ( $versions as $version => $regimes ) : ?>
					<div class="bm-bi__version-group">
						<?php if ( count( $versions ) > 1 ) : ?>
							<p class="bm-bi__version-tag"><?php echo esc_html( sprintf( __( 'Versi classifier: %s', 'bitmomo-btc-intelligence' ), (string) $version ) ); ?></p>
						<?php endif; ?>
						<div class="bm-bi__metric-row">
							<?php
							$regimes = is_array( $regimes ) ? $regimes : array();
							foreach ( $regimes as $regime_key => $metric ) {
								$label = ( class_exists( 'Bitmomo_Regime_Taxonomy' ) && 'unknown' !== $regime_key )
									? Bitmomo_Regime_Taxonomy::regime_label_id( sanitize_key( (string) $regime_key ) )
									: __( 'Tidak diketahui', 'bitmomo-btc-intelligence' );
								$this->render_public_metric( $label, $metric );
							}
							?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/* =====================================================================
	 * 11. DATA QUALITY / FRESHNESS -- freshness/timestamp reuses the
	 * section-2 adapter snapshot; deeper quality metrics wired to
	 * evaluation_summary()['data_quality']
	 * ================================================================== */
	private function render_data_quality() {
		$snapshot = $this->adapter_snapshot();
		$updated  = ( is_array( $snapshot ) && ! empty( $snapshot['freshness']['timestamp_iso'] ) )
			? strtotime( (string) $snapshot['freshness']['timestamp_iso'] )
			: false;
		$summary      = $this->adapter_evaluation_summary();
		$data_quality = is_array( $summary['data_quality'] ?? null ) ? $summary['data_quality'] : array();
		?>
		<section class="bm-bi__section--editorial bm-bi__data-quality">
			<h2 class="bm-bi__section-title">KUALITAS &amp; KESEGARAN DATA</h2>
			<p><?php esc_html_e( 'Intelligence hanya sebaik data di baliknya. Bagian ini menunjukkan seberapa segar data saat kamu melihatnya — bukan klaim real-time yang belum benar-benar live.', 'bitmomo-btc-intelligence' ); ?></p>
			<div class="bm-bi__quality-row">
				<div class="bm-bi__metric">
					<span class="bm-bi__kicker"><?php esc_html_e( 'Data terkini', 'bitmomo-btc-intelligence' ); ?></span>
					<strong>
						<?php
						if ( $updated ) {
							echo esc_html( date_i18n( 'd M Y, H:i', $updated ) );
						} else {
							esc_html_e( 'Belum tersedia', 'bitmomo-btc-intelligence' );
						}
						?>
					</strong>
				</div>
			</div>
			<?php if ( empty( $data_quality ) ) : ?>
				<?php
				$this->render_adapter_pending_boundary(
					$this->adapter_evaluation_summary_available(),
					__( 'Metrik kualitas data yang lebih mendalam (konsistensi, cakupan sumber) belum tersedia secara publik.', 'bitmomo-btc-intelligence' )
				);
				?>
			<?php else : ?>
				<div class="bm-bi__metric-row">
					<?php
					$this->render_public_metric( __( 'Stale Rate', 'bitmomo-btc-intelligence' ), $data_quality, 'stale_rate_pct' );
					$this->render_public_metric( __( 'Blocked/Degraded Rate', 'bitmomo-btc-intelligence' ), $data_quality, 'blocked_degraded_rate_pct' );
					$this->render_public_metric( __( 'Missing Data Rate', 'bitmomo-btc-intelligence' ), $data_quality, 'missing_data_rate_pct' );
					$this->render_public_metric( __( 'Settlement Completeness', 'bitmomo-btc-intelligence' ), $data_quality, 'settlement_completeness_pct' );
					?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/* =====================================================================
	 * 12. METHODOLOGY / ACCOUNTABILITY -- static
	 * ================================================================== */
	private function render_methodology() {
		?>
		<section class="bm-bi__section--editorial bm-bi__section--quiet bm-bi__methodology">
			<h2 class="bm-bi__section-title">METODOLOGI &amp; AKUNTABILITAS</h2>
			<p><?php esc_html_e( 'Lima axis intelligence yang deterministik. Tanpa spekulasi ke arah yang belum bisa dijelaskan. Setiap hasil dicatat sebelum outcome diketahui, lalu dievaluasi terhadap apa yang benar-benar terjadi — bukan dinilai ulang setelah fakta agar terlihat lebih baik.', 'bitmomo-btc-intelligence' ); ?></p>
			<p><?php esc_html_e( 'Halaman ini menunjukkan hasilnya apa adanya — termasuk saat meleset, dan termasuk ketika sampelnya masih terlalu kecil untuk disimpulkan.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 13. BITMOMO PRO CONVERSION -- locked CTA option 1 (recommended v1)
	 * ================================================================== */
	private function render_pro_cta() {
		?>
		<section class="bm-bi__section bm-bi__section--peak bm-bi__pro-cta">
			<p class="bm-bi__eyebrow">BITMOMO PRO</p>
			<h2 class="bm-bi__section-title bm-bi__section-title--climax"><?php esc_html_e( 'Ketahui apa yang perlu diperhatikan berikutnya.', 'bitmomo-btc-intelligence' ); ?></h2>
			<p><?php esc_html_e( 'Bitmomo Pro membantu kamu memahami skenario pasar yang paling relevan, kondisi yang dapat mengubah thesis, dan perubahan penting yang layak mendapat perhatian.', 'bitmomo-btc-intelligence' ); ?></p>
			<p class="bm-bi__signature"><?php esc_html_e( 'Lebih sedikit waktu memantau noise. Lebih banyak fokus pada perubahan yang benar-benar penting.', 'bitmomo-btc-intelligence' ); ?></p>
			<div class="bm-bi__pro-cta-actions">
				<a class="bm-bi__cta-primary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Bitmomo Pro', 'bitmomo-btc-intelligence' ); ?></a>
				<a class="bm-bi__cta-secondary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Lihat cara kerja Bitmomo Pro', 'bitmomo-btc-intelligence' ); ?></a>
			</div>
		</section>
		<?php
	}
}
