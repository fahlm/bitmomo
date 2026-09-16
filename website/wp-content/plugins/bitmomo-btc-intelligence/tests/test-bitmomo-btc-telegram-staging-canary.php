<?php
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );
define( 'BITMOMO_STAGING_SIDE_EFFECTS_DISABLED', true );
define( 'BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED', true );
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', true );
define( 'BITMOMO_TELEGRAM_BOT_TOKEN', '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcd' );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		public function __construct( $code ) { $this->code = $code; }
		public function get_error_code() { return $this->code; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
}

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
function check_telegram_canary( $label, $condition ) {
	if ( $condition ) {
		$GLOBALS['__pass']++;
		echo "[PASS] {$label}\n";
		return;
	}
	$GLOBALS['__fail']++;
	echo "[FAIL] {$label}\n";
}

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-telegram-transport.php';
$guard = new WP_Error( Bitmomo_Btc_Telegram_Transport::STAGING_GUARD_ERROR );
$token = BITMOMO_TELEGRAM_BOT_TOKEN;

check_telegram_canary( 'Staging canary requires all explicit staging and delivery flags', true === Bitmomo_Btc_Telegram_Transport::staging_canary_enabled() );
check_telegram_canary(
	'Canonical Telegram getMe is allowed through the staging guard',
	false === Bitmomo_Btc_Telegram_Transport::allow_staging_canary_request( $guard, array( 'method' => 'GET' ), 'https://api.telegram.org/bot' . $token . '/getMe' )
);
check_telegram_canary(
	'Canonical Telegram getChatMember is allowed through the staging guard',
	false === Bitmomo_Btc_Telegram_Transport::allow_staging_canary_request( $guard, array( 'method' => 'GET' ), 'https://api.telegram.org/bot' . $token . '/getChatMember?chat_id=%40bitmomodaily&user_id=123' )
);
check_telegram_canary(
	'Canonical Telegram sendMessage POST is allowed through the staging guard',
	false === Bitmomo_Btc_Telegram_Transport::allow_staging_canary_request( $guard, array( 'method' => 'POST' ), 'https://api.telegram.org/bot' . $token . '/sendMessage' )
);
check_telegram_canary(
	'Wrong HTTP method remains blocked',
	$guard === Bitmomo_Btc_Telegram_Transport::allow_staging_canary_request( $guard, array( 'method' => 'GET' ), 'https://api.telegram.org/bot' . $token . '/sendMessage' )
);
check_telegram_canary(
	'Other Telegram methods remain blocked',
	$guard === Bitmomo_Btc_Telegram_Transport::allow_staging_canary_request( $guard, array( 'method' => 'POST' ), 'https://api.telegram.org/bot' . $token . '/deleteMessage' )
);
check_telegram_canary(
	'Non-Telegram destinations remain blocked',
	$guard === Bitmomo_Btc_Telegram_Transport::allow_staging_canary_request( $guard, array( 'method' => 'POST' ), 'https://example.com/bot' . $token . '/sendMessage' )
);
$other_guard = new WP_Error( 'some_other_guard' );
check_telegram_canary(
	'Unrelated WP HTTP errors are never cleared',
	$other_guard === Bitmomo_Btc_Telegram_Transport::allow_staging_canary_request( $other_guard, array( 'method' => 'POST' ), 'https://api.telegram.org/bot' . $token . '/sendMessage' )
);

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );
