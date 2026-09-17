<?php
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
}

final class Bitmomo_AI_Session_Intelligence {
	const PRE_OPEN = 'us_pre_open';
	const POST_CLOSE = 'us_post_close';
	const MARKET_TIMEZONE = 'America/New_York';
	public static $history = array();
	public static function history() { return self::$history; }
	public static function is_supported_session_type( $value ) {
		return in_array( sanitize_key( (string) $value ), array( self::PRE_OPEN, self::POST_CLOSE, 'morning', 'us_session' ), true );
	}
	public static function normalize_session_type( $value ) {
		$value = sanitize_key( (string) $value );
		if ( in_array( $value, array( self::PRE_OPEN, 'morning' ), true ) ) return self::PRE_OPEN;
		return self::POST_CLOSE;
	}
}

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-coverage.php';

$pass = 0;
$fail = 0;
function coverage_check( $label, $condition ) {
	global $pass, $fail;
	if ( $condition ) {
		$pass++;
		echo "[PASS] {$label}\n";
	} else {
		$fail++;
		echo "[FAIL] {$label}\n";
	}
}

function coverage_record( $anchor, $session, $schema = '2.0' ) {
	return array(
		'schema_version'   => $schema,
		'canonical_status' => 'valid',
		'provenance'       => 'recorded_live',
		'session_type'     => $session,
		'session_anchor'   => $anchor,
	);
}

Bitmomo_AI_Session_Intelligence::$history = array(
	'a' => coverage_record( '2026-09-15T08:10:00-04:00', 'us_pre_open' ),
	'b' => coverage_record( '2026-09-15T20:10:00-04:00', 'us_post_close' ),
	'c' => coverage_record( '2026-09-16T08:10:00-04:00', 'us_pre_open' ),
	// 16 Sep Post-Close intentionally absent.
	'd' => coverage_record( '2026-09-17T08:10:00-04:00', 'us_pre_open' ),
	// Legacy record must not define v2 coverage or satisfy a v2 anchor.
	'legacy' => coverage_record( '2026-09-14T20:10:00-04:00', 'us_session', '1.0' ),
);

$now = new DateTimeImmutable( '2026-09-17T09:00:00-04:00' );
$coverage = Bitmomo_Btc_Intelligence_Coverage::coverage( $now );

coverage_check( 'coverage activates only from earliest native schema-v2 anchor', ! empty( $coverage['available'] ) && '15 Sep 2026' === $coverage['start_label'] );
coverage_check( 'only anchors older than the 30-minute grace window become expected', 5 === (int) $coverage['expected_n'] );
coverage_check( 'recorded native v2 anchors are counted', 4 === (int) $coverage['recorded_n'] );
coverage_check( 'missing native v2 anchor is explicit rather than fabricated as an outcome', 1 === (int) $coverage['missing_n'] );
coverage_check( 'missing session preserves canonical session identity', false !== strpos( (string) ( $coverage['missing'][0]['label'] ?? '' ), 'US POST-CLOSE' ) );
coverage_check( 'missing session preserves the expected 20:10 New York anchor as 07:10 WIB next day', false !== strpos( (string) ( $coverage['missing'][0]['label'] ?? '' ), '17 Sep 2026 · 07:10 WIB' ) );

Bitmomo_AI_Session_Intelligence::$history = array(
	'legacy-only' => coverage_record( '2026-09-15T20:10:00-04:00', 'us_session', '1.0' ),
);
$legacy_only = Bitmomo_Btc_Intelligence_Coverage::coverage( $now );
coverage_check( 'legacy-only history does not retroactively create missing two-session obligations', empty( $legacy_only['available'] ) && 0 === (int) $legacy_only['missing_n'] );

printf( "\n%d/%d passed.\n", $pass, $pass + $fail );
exit( 0 === $fail ? 0 : 1 );
