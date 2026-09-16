<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
function check_telegram_publisher_contract( $label, $condition ) {
	if ( $condition ) {
		$GLOBALS['__pass']++;
		echo "[PASS] {$label}\n";
		return;
	}
	$GLOBALS['__fail']++;
	echo "[FAIL] {$label}\n";
}

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-telegram-publisher.php';
$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-telegram-publisher.php' );

check_telegram_publisher_contract(
	'Publisher is inert by default',
	false === Bitmomo_Btc_Telegram_Publisher::auto_publish_enabled()
);

$result = Bitmomo_Btc_Telegram_Publisher::after_canonical_daily_generation();
check_telegram_publisher_contract(
	'Disabled publisher fails closed without touching transport',
	is_array( $result ) && false === $result['ok'] && 'autopost_disabled' === $result['status']
);

check_telegram_publisher_contract(
	'Publisher subscribes only to canonical daily generation hook at later priority',
	'bitmomo_ai_daily_generation' === Bitmomo_Btc_Telegram_Publisher::CANONICAL_GENERATION_HOOK
		&& 20 === Bitmomo_Btc_Telegram_Publisher::PRIORITY
		&& false !== strpos( $source, 'CANONICAL_GENERATION_HOOK' )
);

check_telegram_publisher_contract(
	'Autopost requires its own explicit runtime flag',
	false !== strpos( $source, 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED' )
		&& false === strpos( $source, "define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED'" )
);

check_telegram_publisher_contract(
	'Publisher creates no second scheduler',
	false === strpos( $source, 'wp_schedule_event' )
		&& false === strpos( $source, 'wp_schedule_single_event' )
		&& false === strpos( $source, 'wp_next_scheduled' )
);

check_telegram_publisher_contract(
	'Publisher delegates sending and contains no market intelligence logic',
	false !== strpos( $source, 'Bitmomo_Btc_Telegram_Transport::send_current_brief()' )
		&& false === strpos( $source, 'Bitmomo_Public_Intelligence_Adapter::snapshot()' )
		&& false === strpos( $source, 'Bitmomo_AI_Binance')
);

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );
