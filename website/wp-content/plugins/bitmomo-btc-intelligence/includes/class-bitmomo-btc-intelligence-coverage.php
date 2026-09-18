<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public operational-coverage proof for Major Brief cadence.
 *
 * Decision Ledger answers whether matured market views were right or wrong.
 * This component answers a different question: did the schema-v2 Major Brief
 * scheduler actually leave a valid record at each expected session anchor?
 * Keeping the two contracts separate prevents missing operational runs from
 * becoming fabricated market-outcome rows.
 */
final class Bitmomo_Btc_Intelligence_Coverage {
	const SCHEMA_VERSION = '2.0';
	const LOOKBACK_DAYS  = 7;
	const GRACE_MINUTES  = 30;

	public static function init() {
		add_shortcode( 'bitmomo_major_brief_coverage', array( __CLASS__, 'shortcode' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_to_btc_intelligence' ), 12 );
	}

	/**
	 * The canonical BTC page is shortcode-owned. Append one first-party
	 * operational proof surface after that shortcode without altering its
	 * rendered output or recomputing market intelligence.
	 */
	public static function append_to_btc_intelligence( $content ) {
		if ( is_admin() || ! is_page( 'btc-intelligence' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! has_shortcode( (string) $content, 'bitmomo_btc_intelligence' ) || has_shortcode( (string) $content, 'bitmomo_major_brief_coverage' ) ) {
			return $content;
		}
		return rtrim( (string) $content ) . "\n\n[bitmomo_major_brief_coverage]";
	}

	public static function shortcode() {
		$coverage = self::coverage();
		ob_start();
		?>
		<section id="major-brief-coverage" class="bm-bi-coverage" aria-labelledby="bm-bi-coverage-title">
			<div class="bm-bi-coverage__head">
				<div>
					<p class="bm-bi-coverage__eyebrow">MAJOR BRIEF COVERAGE</p>
					<h2 id="bm-bi-coverage-title"><?php esc_html_e( 'Apakah dua brief harian benar-benar tercatat?', 'bitmomo-btc-intelligence' ); ?></h2>
				</div>
				<?php if ( ! empty( $coverage['available'] ) ) : ?>
					<strong><?php echo esc_html( sprintf( '%d / %d sesi', (int) $coverage['recorded_n'], (int) $coverage['expected_n'] ) ); ?></strong>
				<?php endif; ?>
			</div>

			<p class="bm-bi-coverage__intro"><?php esc_html_e( 'Coverage ini mengaudit jadwal US Pre-Open dan US Post-Close sejak schema sesi v2 aktif. Ia terpisah dari Decision Ledger: sesi yang hilang ditandai sebagai gap operasional, bukan dibuat menjadi hasil prediksi.', 'bitmomo-btc-intelligence' ); ?></p>

			<?php if ( empty( $coverage['available'] ) ) : ?>
				<div class="bm-bi-coverage__status is-unavailable" role="status">
					<strong><?php esc_html_e( 'Riwayat sesi v2 belum cukup untuk audit coverage.', 'bitmomo-btc-intelligence' ); ?></strong>
					<span><?php esc_html_e( 'Bitmomo tidak menganggap era legacy sebagai dua brief harian secara retrospektif.', 'bitmomo-btc-intelligence' ); ?></span>
				</div>
			<?php else : ?>
				<div class="bm-bi-coverage__summary">
					<div><span><?php esc_html_e( 'TERCATAT', 'bitmomo-btc-intelligence' ); ?></span><strong><?php echo esc_html( (int) $coverage['recorded_n'] ); ?></strong></div>
					<div><span><?php esc_html_e( 'SEHARUSNYA TERBIT', 'bitmomo-btc-intelligence' ); ?></span><strong><?php echo esc_html( (int) $coverage['expected_n'] ); ?></strong></div>
					<div><span><?php esc_html_e( 'GAP', 'bitmomo-btc-intelligence' ); ?></span><strong><?php echo esc_html( (int) $coverage['missing_n'] ); ?></strong></div>
				</div>

				<?php if ( ! empty( $coverage['missing'] ) ) : ?>
					<div class="bm-bi-coverage__missing" role="status">
						<strong><?php esc_html_e( 'Sesi yang tidak memiliki record valid', 'bitmomo-btc-intelligence' ); ?></strong>
						<ul>
							<?php foreach ( array_slice( $coverage['missing'], -8 ) as $missing ) : ?>
								<li><time datetime="<?php echo esc_attr( (string) $missing['anchor_iso'] ); ?>"><?php echo esc_html( (string) $missing['label'] ); ?></time></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( count( $coverage['missing'] ) > 8 ) : ?><small><?php echo esc_html( sprintf( __( '+%d gap lebih lama dalam window audit.', 'bitmomo-btc-intelligence' ), count( $coverage['missing'] ) - 8 ) ); ?></small><?php endif; ?>
					</div>
				<?php else : ?>
					<div class="bm-bi-coverage__status is-complete" role="status">
						<strong><?php esc_html_e( 'Tidak ada gap pada window audit yang sudah jatuh tempo.', 'bitmomo-btc-intelligence' ); ?></strong>
					</div>
				<?php endif; ?>

				<p class="bm-bi-coverage__note"><?php echo esc_html( sprintf( __( 'Window audit dimulai %s. Anchor yang belum melewati grace %d menit tidak dihitung sebagai missing.', 'bitmomo-btc-intelligence' ), (string) $coverage['start_label'], self::GRACE_MINUTES ) ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function coverage( $now = null ) {
		$empty = array(
			'available'  => false,
			'expected_n' => 0,
			'recorded_n' => 0,
			'missing_n'  => 0,
			'missing'    => array(),
			'start_label'=> '',
		);

		if ( ! class_exists( 'Bitmomo_AI_Session_Intelligence' ) || ! method_exists( 'Bitmomo_AI_Session_Intelligence', 'history' ) ) {
			return $empty;
		}

		$timezone = new DateTimeZone( Bitmomo_AI_Session_Intelligence::MARKET_TIMEZONE );
		$now = $now instanceof DateTimeImmutable ? $now->setTimezone( $timezone ) : new DateTimeImmutable( 'now', $timezone );
		$records = array();
		$earliest_anchor = null;

		foreach ( (array) Bitmomo_AI_Session_Intelligence::history() as $record ) {
			if ( ! is_array( $record ) ) continue;
			if ( self::SCHEMA_VERSION !== (string) ( $record['schema_version'] ?? '' ) ) continue;
			if ( 'valid' !== (string) ( $record['canonical_status'] ?? 'valid' ) ) continue;
			if ( 'recorded_live' !== (string) ( $record['provenance'] ?? '' ) ) continue;

			$raw_session = (string) ( $record['session_type'] ?? $record['edition'] ?? '' );
			if ( ! Bitmomo_AI_Session_Intelligence::is_supported_session_type( $raw_session ) ) continue;
			$session = Bitmomo_AI_Session_Intelligence::normalize_session_type( $raw_session );
			if ( ! in_array( $session, array( Bitmomo_AI_Session_Intelligence::PRE_OPEN, Bitmomo_AI_Session_Intelligence::POST_CLOSE ), true ) ) continue;

			$anchor_raw = trim( (string) ( $record['session_anchor'] ?? '' ) );
			try {
				$anchor = '' !== $anchor_raw ? ( new DateTimeImmutable( $anchor_raw ) )->setTimezone( $timezone ) : null;
			} catch ( Exception $e ) {
				$anchor = null;
			}
			if ( ! $anchor ) continue;

			$key = self::anchor_key( $anchor, $session );
			$records[ $key ] = true;
			if ( null === $earliest_anchor || $anchor < $earliest_anchor ) $earliest_anchor = $anchor;
		}

		if ( ! $earliest_anchor ) return $empty;

		$lookback_start = $now->modify( '-' . self::LOOKBACK_DAYS . ' days' )->setTime( 0, 0, 0 );
		$activation_start = $earliest_anchor->setTime( 0, 0, 0 );
		$start = $activation_start > $lookback_start ? $activation_start : $lookback_start;
		$grace_cutoff = $now->modify( '-' . self::GRACE_MINUTES . ' minutes' );
		$expected = array();
		$missing = array();
		$recorded_n = 0;

		for ( $day = $start; $day <= $now; $day = $day->modify( '+1 day' ) ) {
			foreach ( array(
				Bitmomo_AI_Session_Intelligence::PRE_OPEN  => array( 8, 10, 'US PRE-OPEN' ),
				Bitmomo_AI_Session_Intelligence::POST_CLOSE => array( 20, 10, 'US POST-CLOSE' ),
			) as $session => $parts ) {
				$anchor = $day->setTime( $parts[0], $parts[1], 0 );
				if ( $anchor < $earliest_anchor || $anchor > $grace_cutoff ) continue;
				$key = self::anchor_key( $anchor, $session );
				$expected[] = $key;
				if ( isset( $records[ $key ] ) ) {
					$recorded_n++;
					continue;
				}
				$missing[] = array(
					'anchor_iso' => $anchor->format( DateTimeInterface::ATOM ),
					'label'      => $anchor->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M Y · H:i' ) . ' WIB · ' . $parts[2],
				);
			}
		}

		return array(
			'available'   => true,
			'expected_n'  => count( $expected ),
			'recorded_n'  => $recorded_n,
			'missing_n'   => count( $missing ),
			'missing'     => $missing,
			'start_label' => $start->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M Y' ),
		);
	}

	private static function anchor_key( DateTimeImmutable $anchor, $session ) {
		return $anchor->format( 'Y-m-d\TH:i' ) . '|' . sanitize_key( (string) $session );
	}
}
