<?php
require __DIR__ . '/wp-stubs.php';

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
function check_telegram_brief( $label, $cond ) {
	if ( $cond ) {
		$GLOBALS['__pass']++;
		echo "[PASS] $label\n";
	} else {
		$GLOBALS['__fail']++;
		echo "[FAIL] $label\n";
	}
}

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-telegram-brief.php';

$fresh = array(
	'status' => 'fresh',
	'btc_reference_price' => 65000,
	'directional_bias' => 'bullish',
	'confidence' => array( 'value' => 82, 'label' => 'high' ),
	'freshness' => array( 'timestamp_iso' => '2026-09-12T20:10:07+00:00' ),
	'key_drivers' => array(
		'Momentum BTC menguat.',
		'Driver kedua tidak diperlukan untuk brief Telegram.',
	),
	'session_intelligence' => array(
		'what_changed' => array(
			array( 'field' => 'directional_bias', 'from' => 'neutral', 'to' => 'bullish' ),
			array( 'field' => 'confidence', 'from' => 61, 'to' => 82 ),
			array( 'field' => 'private_field', 'from' => 'PRIVATE_FROM', 'to' => 'PRIVATE_TO' ),
		),
		'why_it_matters' => array(
			'directional_context_changed',
			'evidence_strength_changed',
			'PRIVATE_REASON_SENTINEL',
		),
		'what_to_watch' => array(
			'PRIVATE_WATCH_SENTINEL',
			'directional_consistency',
			'structure_continuity',
		),
		'monitoring_conditions' => array( 'PRO_MONITORING_SENTINEL' ),
		'scenario_contract' => array( 'base' => 'PRO_SCENARIO_SENTINEL' ),
		'expected_range' => 'PRO_RANGE_SENTINEL',
		'invalidation' => 'PRO_INVALIDATION_SENTINEL',
		'derivatives_context' => array(
			'funding_rate' => 'RAW_FUNDING_SENTINEL',
			'open_interest' => 'RAW_OI_SENTINEL',
		),
	),
);

$result = Bitmomo_Btc_Telegram_Brief::build( $fresh );
$text = $result['text'] ?? '';

check_telegram_brief( 'Fresh canonical snapshot produces a brief', ! empty( $result['ok'] ) && 'ready' === ( $result['reason'] ?? '' ) );
check_telegram_brief( 'Brief exposes current public decision context', false !== strpos( $text, 'Bias: Bullish · Confidence: 82%' ) && false !== strpos( $text, 'BTC: $65,000' ) );
check_telegram_brief( 'Brief uses one public-safe driver', false !== strpos( $text, 'Driver utama: Momentum BTC menguat.' ) );
check_telegram_brief( 'Brief humanizes allowlisted changes', false !== strpos( $text, 'Bias berubah dari Netral menjadi Bullish.' ) && false !== strpos( $text, 'Confidence berubah dari 61% menjadi 82%.' ) );
check_telegram_brief( 'Brief humanizes one public meaning', false !== strpos( $text, 'Arah dominan pasar berubah.' ) );
check_telegram_brief( 'Brief renders exactly one allowlisted watch', 1 === substr_count( $text, 'PANTAU BERIKUTNYA' ) && false !== strpos( $text, 'Apakah kekuatan arah tetap konsisten.' ) && false === strpos( $text, 'Apakah struktur harga tetap mendukung bias saat ini.' ) );
check_telegram_brief( 'Brief includes the first-placement attributed Founding CTA', false !== strpos( $text, 'utm_campaign=founding149_tlw_v1' ) );
check_telegram_brief( 'Brief does not leak Pro protected fields', false === strpos( $text, 'PRO_MONITORING_SENTINEL' ) && false === strpos( $text, 'PRO_SCENARIO_SENTINEL' ) && false === strpos( $text, 'PRO_RANGE_SENTINEL' ) && false === strpos( $text, 'PRO_INVALIDATION_SENTINEL' ) );
check_telegram_brief( 'Brief does not leak private reasons, watches or raw derivative metrics', false === strpos( $text, 'PRIVATE_REASON_SENTINEL' ) && false === strpos( $text, 'PRIVATE_WATCH_SENTINEL' ) && false === strpos( $text, 'RAW_FUNDING_SENTINEL' ) && false === strpos( $text, 'RAW_OI_SENTINEL' ) );
check_telegram_brief( 'Brief remains comfortably below Telegram message limit', strlen( $text ) < Bitmomo_Btc_Telegram_Brief::MAX_TEXT_LENGTH );

$delayed = $fresh;
$delayed['status'] = 'delayed';
$delayed_result = Bitmomo_Btc_Telegram_Brief::build( $delayed );
check_telegram_brief( 'Delayed canonical snapshot fails closed', empty( $delayed_result['ok'] ) && 'snapshot_not_fresh' === ( $delayed_result['reason'] ?? '' ) && '' === ( $delayed_result['text'] ?? '' ) );

$incomplete = $fresh;
$incomplete['session_intelligence']['what_to_watch'] = array( 'PRIVATE_WATCH_SENTINEL' );
$incomplete_result = Bitmomo_Btc_Telegram_Brief::build( $incomplete );
check_telegram_brief( 'Missing allowlisted public watch fails closed', empty( $incomplete_result['ok'] ) && 'brief_contract_incomplete' === ( $incomplete_result['reason'] ?? '' ) );

$bad_url_result = Bitmomo_Btc_Telegram_Brief::build( $fresh, 'javascript:alert(1)' );
check_telegram_brief( 'Unsafe CTA URL is omitted rather than emitted', ! empty( $bad_url_result['ok'] ) && false === strpos( $bad_url_result['text'], 'javascript:' ) && false === strpos( $bad_url_result['text'], 'Founding 149:' ) );

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );
