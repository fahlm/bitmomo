<?php
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
function check_founding149_contract( $label, $condition ) {
	if ( $condition ) {
		$GLOBALS['__pass']++;
		echo "[PASS] {$label}\n";
		return;
	}
	$GLOBALS['__fail']++;
	echo "[FAIL] {$label}\n";
}

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-founding149-acquisition.php' );
$whitelist = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-whitelist.php' );
$hardening = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-launch-hardening.php' );
$plugin = file_get_contents( dirname( __DIR__ ) . '/bitmomo-pro.php' );
$account = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-account.php' );

check_founding149_contract( 'Campaign id is the single approved Founding 149 Telegram motion', false !== strpos( $source, "const CAMPAIGN = 'founding149_tlw_v1';" ) );
check_founding149_contract( 'Scoreboard reuses canonical whitelist post type and UTM campaign metadata', false !== strpos( $source, 'Bitmomo_Pro_Whitelist::POST_TYPE' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN' ) );
check_founding149_contract( 'Scoreboard reuses canonical validation/status metadata', false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_STATUS' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::STATUSES' ) );
check_founding149_contract( 'Test/internal records are excluded from external demand count', false !== strpos( $source, "array( 'test', 'internal' )" ) && false !== strpos( $source, "out['excluded']++" ) );
check_founding149_contract( 'Scoreboard is founder/admin-only presentation and adds no public endpoint', false !== strpos( $source, "current_user_can( 'manage_options' )" ) && false === strpos( $source, 'register_post_type' ) && false === strpos( $source, 'register_rest_route' ) && false === strpos( $source, 'wp_ajax_' ) );
check_founding149_contract( 'Canonical whitelist remains private and non-REST', false !== strpos( $whitelist, "'public'              => false" ) && false !== strpos( $whitelist, "'show_in_rest'        => false" ) );

check_founding149_contract( 'Launch hardening is bootstrapped by Bitmomo Pro', false !== strpos( $plugin, "class-bitmomo-pro-launch-hardening.php" ) && false !== strpos( $plugin, 'Bitmomo_Pro_Launch_Hardening::instance()' ) );
check_founding149_contract( 'Whitelist acquisition pages fail safe against cached nonces', false !== strpos( $hardening, "define( 'DONOTCACHEPAGE', true )" ) && false !== strpos( $hardening, "litespeed_control_set_nocache" ) && false !== strpos( $hardening, "is_page( 'pro' )" ) );
check_founding149_contract( 'Non-production hosts receive an HTTP noindex boundary', false !== strpos( $hardening, 'X-Robots-Tag: noindex, nofollow, noarchive' ) && false !== strpos( $hardening, "'bitmomo.id', 'www.bitmomo.id'" ) );
check_founding149_contract( 'Anonymous WordPress user enumeration is closed', false !== strpos( $hardening, "rest_endpoints" ) && false !== strpos( $hardening, "current_user_can( 'list_users' )" ) && false !== strpos( $hardening, '#^/wp/v2/users(?:/|$)#' ) );
check_founding149_contract( 'Generic public title identity is canonical Bitmomo', false !== strpos( $hardening, "\$parts['site'] = 'Bitmomo';" ) );
check_founding149_contract( 'Account login button uses natural Indonesian copy', false !== strpos( $account, "'label_log_in' => __( 'Masuk', 'bitmomo-pro' )" ) );

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );
