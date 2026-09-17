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

check_founding149_contract( 'Campaign id is the single approved Founding 149 Telegram motion', false !== strpos( $source, "const CAMPAIGN = 'founding149_tlw_v1';" ) );
check_founding149_contract( 'Scoreboard reuses canonical whitelist post type and UTM campaign metadata', false !== strpos( $source, 'Bitmomo_Pro_Whitelist::POST_TYPE' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN' ) );
check_founding149_contract( 'Scoreboard reuses canonical validation/status metadata', false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_STATUS' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::STATUSES' ) );
check_founding149_contract( 'Test/internal records are excluded from external demand count', false !== strpos( $source, "array( 'test', 'internal' )" ) && false !== strpos( $source, "out['excluded']++" ) );
check_founding149_contract( 'Scoreboard is founder/admin-only presentation and adds no public endpoint', false !== strpos( $source, "current_user_can( 'manage_options' )" ) && false === strpos( $source, 'register_post_type' ) && false === strpos( $source, 'register_rest_route' ) && false === strpos( $source, 'wp_ajax_' ) );
check_founding149_contract( 'Canonical whitelist remains private and non-REST', false !== strpos( $whitelist, "'public'              => false" ) && false !== strpos( $whitelist, "'show_in_rest'        => false" ) );
check_founding149_contract( 'Whitelist embeds a WordPress nonce and therefore must not rely on long-lived public page cache', false !== strpos( $whitelist, 'wp_create_nonce( self::NONCE_ACTION )' ) && false !== strpos( $whitelist, "wp_nonce_field( self::NONCE_ACTION, 'bm_wl_nonce' )" ) );
check_founding149_contract( 'Cache boundary disables page cache on homepage and sales/whitelist surfaces while checkout is unavailable', false !== strpos( $cache, 'is_whitelist_conversion_surface' ) && false !== strpos( $cache, 'is_front_page() || is_home()' ) && false !== strpos( $cache, "has_shortcode( \$post->post_content, 'bitmomo_pro_sales' )" ) && false !== strpos( $cache, "has_shortcode( \$post->post_content, 'bitmomo_pro_whitelist' )" ) && false !== strpos( $cache, 'bitmomo_pro_get_checkout_url' ) );
check_founding149_contract( 'Whitelist cache boundary sends WordPress and LiteSpeed no-cache signals', false !== strpos( $cache, "define( 'DONOTCACHEPAGE', true )" ) && false !== strpos( $cache, "do_action( 'litespeed_control_set_nocache'" ) && false !== strpos( $cache, "header( 'X-LiteSpeed-Cache-Control: no-cache' )" ) );

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );
