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
check_telegram_transport_contract( 'Delivery is disabled by default when runtime flag is absent', false === Bitmomo_Btc_Telegram_Transport::enabled() );
check_telegram_transport_contract( 'Transport is not configured without runtime enablement/token', false === Bitmomo_Btc_Telegram_Transport::configured() );

$preflight = Bitmomo_Btc_Telegram_Transport::preflight();
check_telegram_transport_contract( 'Preflight fails closed while delivery is disabled', is_array( $preflight ) && false === $preflight['ok'] && 'disabled' === $preflight['status'] );
$result = Bitmomo_Btc_Telegram_Transport::send_current_brief();
check_telegram_transport_contract( 'Disabled transport fails closed before any HTTP call', is_array( $result ) && false === $result['ok'] && 'disabled' === $result['status'] );

check_telegram_transport_contract(
	'Bot token is runtime-only and format-validated',
	false !== strpos( $source, 'BITMOMO_TELEGRAM_BOT_TOKEN' )
		&& false === strpos( $source, "define( 'BITMOMO_TELEGRAM_BOT_TOKEN'" )
		&& false === strpos( $source, "get_option( 'bitmomo_telegram_bot_token'" )
		&& false !== strpos( $source, "preg_match( '/^\\d+:[A-Za-z0-9_-]{20,}$/'" )
);
check_telegram_transport_contract(
	'Live preflight verifies identity and posting access before sendMessage',
	false !== strpos( $source, '/getMe' )
		&& false !== strpos( $source, '/getChatMember' )
		&& false !== strpos( $source, "'can_post_messages'" )
		&& false !== strpos( $source, "'bot_identity_mismatch'" )
		&& false !== strpos( $source, "'channel_post_permission_missing'" )
		&& strpos( $source, 'preflight()' ) < strrpos( $source, '/sendMessage' )
);
check_telegram_transport_contract(
	'Duplicate canonical brief guard persists only a non-secret message hash',
	'bitmomo_btc_telegram_last_sent_hash' === Bitmomo_Btc_Telegram_Transport::LAST_SENT_HASH_OPTION
		&& false !== strpos( $source, "hash( 'sha256'" )
		&& false !== strpos( $source, "'duplicate_brief'" )
		&& false !== strpos( $source, 'hash_equals' )
);
check_telegram_transport_contract(
	'Transport delegates message construction to the canonical Telegram formatter',
	false !== strpos( $source, 'Bitmomo_Btc_Telegram_Brief::current()' )
		&& false === strpos( $source, 'Bitmomo_Public_Intelligence_Adapter::snapshot()' )
);
check_telegram_transport_contract(
	'Transport creates no cron or autonomous scheduler',
	false === strpos( $source, 'wp_schedule_event' )
		&& false === strpos( $source, 'wp_schedule_single_event' )
		&& false === strpos( $source, 'wp_next_scheduled' )
);
check_telegram_transport_contract(
	'Staging exception is explicit, narrow, and tied to the canonical guard error',
	false !== strpos( $source, 'BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED' )
		&& false !== strpos( $source, "BITMOMO_STAGING_SIDE_EFFECTS_DISABLED" )
		&& Bitmomo_Btc_Telegram_Transport::STAGING_GUARD_ERROR === 'bitmomo_staging_outbound_write_disabled'
		&& false !== strpos( $source, "'api.telegram.org'" )
		&& false !== strpos( $source, "'getMe'         => 'GET'" )
		&& false !== strpos( $source, "'getChatMember' => 'GET'" )
		&& false !== strpos( $source, "'sendMessage'   => 'POST'" )
);

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );
