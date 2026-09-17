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
$cache = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-cache.php' );
$main = file_get_contents( dirname( __DIR__ ) . '/bitmomo-pro.php' );

check_founding149_contract( 'Campaign id is the single approved Founding 149 Telegram motion', false !== strpos( $source, "const CAMPAIGN = 'founding149_tlw_v1';" ) );
check_founding149_contract( 'Scoreboard reuses canonical whitelist post type and UTM campaign metadata', false !== strpos( $source, 'Bitmomo_Pro_Whitelist::POST_TYPE' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN' ) );
check_founding149_contract( 'Scoreboard reuses canonical validation/status metadata', false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_STATUS' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::STATUSES' ) );
check_founding149_contract( 'Test/internal records are excluded from external demand count', false !== strpos( $source, "array( 'test', 'internal' )" ) && false !== strpos( $source, "out['excluded']++" ) );
check_founding149_contract( 'Scoreboard is founder/admin-only presentation and adds no public endpoint', false !== strpos( $source, "current_user_can( 'manage_options' )" ) && false === strpos( $source, 'register_post_type' ) && false === strpos( $source, 'register_rest_route' ) && false === strpos( $source, 'wp_ajax_' ) );
check_founding149_contract( 'Canonical whitelist remains private and non-REST', false !== strpos( $whitelist, "'public'              => false" ) && false !== strpos( $whitelist, "'show_in_rest'        => false" ) );
check_founding149_contract( 'Whitelist form remains nonce protected', false !== strpos( $whitelist, 'wp_create_nonce( self::NONCE_ACTION )' ) && false !== strpos( $whitelist, "check_ajax_referer( self::NONCE_ACTION, 'bm_wl_nonce' )" ) );
check_founding149_contract( 'Homepage and Pro acquisition surfaces fail closed from page cache while whitelist nonce is live', false !== strpos( $cache, 'is_front_page()' ) && false !== strpos( $cache, "'bitmomo_pro_sales', 'bitmomo_pro_whitelist'" ) && false !== strpos( $cache, 'X-LiteSpeed-Cache-Control: no-cache' ) && false !== strpos( $cache, 'no-store' ) );
check_founding149_contract( 'Anonymous WordPress user enumeration is blocked', false !== strpos( $main, 'bitmomo_pro_block_public_user_enumeration' ) && false !== strpos( $main, "#^/wp/v2/users(?:/|$)#" ) && false !== strpos( $main, "array( 'status' => 404 )" ) );

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );
