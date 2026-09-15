<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
function check_telegram_transport_contract( $label, $condition ) {
	if ( $condition ) {
		$GLOBALS['__pass']++;
		echo "[PASS] {$label}\n";
		return;
	}
	$GLOBALS['__fail']++;
	echo "[FAIL] {$label}\n";
}

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-telegram-transport.php';

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-telegram-transport.php' );

check_telegram_transport_contract(
	'Canonical public Telegram destination is @bitmomodaily',
	'@bitmomodaily' === Bitmomo_Btc_Telegram_Transport::CHANNEL_USERNAME
		&& 'https://t.me/bitmomodaily' === Bitmomo_Btc_Telegram_Transport::CHANNEL_URL
);

check_telegram_transport_contract(
	'Canonical Telegram posting bot is @Bitmomo_id_bot',
	'Bitmomo_id_bot' === Bitmomo_Btc_Telegram_Transport::BOT_USERNAME
		&& 'https://t.me/Bitmomo_id_bot' === Bitmomo_Btc_Telegram_Transport::BOT_URL
);

check_telegram_transport_contract(
	'Delivery is disabled by default when runtime flag is absent',
	false === Bitmomo_Btc_Telegram_Transport::enabled()
);

check_telegram_transport_contract(
	'Transport is not configured without runtime enablement/token',
	false === Bitmomo_Btc_Telegram_Transport::configured()
);

$result = Bitmomo_Btc_Telegram_Transport::send_current_brief();
check_telegram_transport_contract(
	'Disabled transport fails closed before any HTTP call',
	is_array( $result ) && false === $result['ok'] && 'disabled' === $result['status']
);

$identity = Bitmomo_Btc_Telegram_Transport::verify_bot_identity();
check_telegram_transport_contract(
	'Bot identity verification also fails closed while disabled',
	is_array( $identity ) && false === $identity['ok'] && 'disabled' === $identity['status']
);

check_telegram_transport_contract(
	'Bot token is read only from the runtime constant',
	false !== strpos( $source, "BITMOMO_TELEGRAM_BOT_TOKEN" )
		&& false === strpos( $source, "define( 'BITMOMO_TELEGRAM_BOT_TOKEN'" )
		&& false === strpos( $source, 'get_option( \'bitmomo_telegram_bot_token\'' )
);

check_telegram_transport_contract(
	'Every live send verifies token identity before sendMessage',
	false !== strpos( $source, '/getMe' )
		&& false !== strpos( $source, 'verify_bot_identity()' )
		&& false !== strpos( $source, "'bot_identity_mismatch'" )
		&& strpos( $source, 'verify_bot_identity()' ) < strrpos( $source, '/sendMessage' )
);

check_telegram_transport_contract(
	'Transport does not register cron or autonomous send hooks',
	false === strpos( $source, 'wp_schedule_event' )
		&& false === strpos( $source, 'wp_schedule_single_event' )
		&& false === strpos( $source, "add_action( 'bitmomo_telegram" )
);

check_telegram_transport_contract(
	'Transport delegates message construction to canonical Telegram brief formatter',
	false !== strpos( $source, 'Bitmomo_Btc_Telegram_Brief::current()' )
		&& false === strpos( $source, 'Bitmomo_Public_Intelligence_Adapter::snapshot()' )
);

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );
