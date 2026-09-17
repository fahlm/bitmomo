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

$source          = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-founding149-acquisition.php' );
$whitelist       = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-whitelist.php' );
$cache           = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-cache.php' );
$theme_functions = file_get_contents( dirname( __DIR__, 3 ) . '/themes/bitmomo-child-v3/functions.php' );

check_founding149_contract( 'Campaign id is the single approved Founding 149 Telegram motion', false !== strpos( $source, "const CAMPAIGN = 'founding149_tlw_v1';" ) );
check_founding149_contract( 'Scoreboard reuses canonical whitelist post type and UTM campaign metadata', false !== strpos( $source, 'Bitmomo_Pro_Whitelist::POST_TYPE' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN' ) );
check_founding149_contract( 'Scoreboard reuses canonical validation/status metadata', false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_STATUS' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::STATUSES' ) );
check_founding149_contract( 'Test/internal records are excluded from external demand count', false !== strpos( $source, "array( 'test', 'internal' )" ) && false !== strpos( $source, "out['excluded']++" ) );
check_founding149_contract( 'Scoreboard is founder/admin-only presentation and adds no public endpoint', false !== strpos( $source, "current_user_can( 'manage_options' )" ) && false === strpos( $source, 'register_post_type' ) && false === strpos( $source, 'register_rest_route' ) && false === strpos( $source, 'wp_ajax_' ) );
check_founding149_contract( 'Canonical whitelist remains private and non-REST', false !== strpos( $whitelist, "'public'              => false" ) && false !== strpos( $whitelist, "'show_in_rest'        => false" ) );
check_founding149_contract(
	'Nonce-bearing whitelist surfaces explicitly bypass page cache while checkout is unavailable',
	false !== strpos( $cache, "has_shortcode( \$post->post_content, 'bitmomo_pro_sales' )" ) &&
	false !== strpos( $cache, "'bitmomo_pro_whitelist'" ) &&
	false !== strpos( $cache, 'is_front_page() && $this->whitelist_is_live()' ) &&
	false !== strpos( $cache, "header( 'X-LiteSpeed-Cache-Control: no-cache' )" ) &&
	false !== strpos( $cache, "do_action( 'litespeed_control_set_nocache'" )
);
check_founding149_contract(
	'Only canonical production hosts may remain indexable',
	false !== strpos( $cache, "array( 'bitmomo.id', 'www.bitmomo.id' )" ) &&
	false !== strpos( $cache, "X-Robots-Tag: noindex, nofollow, noarchive" ) &&
	false !== strpos( $cache, "User-agent: *\\nDisallow: /\\n" ) &&
	false !== strpos( $cache, "add_filter( 'wp_robots'" ) &&
	false !== strpos( $cache, "add_filter( 'rank_math/frontend/robots'" )
);
check_founding149_contract(
	'Anonymous WordPress REST user enumeration is removed while authenticated sessions remain supported',
	false !== strpos( $theme_functions, "function bitmomo_hide_public_rest_users" ) &&
	false !== strpos( $theme_functions, "if (is_user_logged_in()) return \$endpoints;" ) &&
	false !== strpos( $theme_functions, "'/wp/v2/users'" ) &&
	false !== strpos( $theme_functions, "add_filter('rest_endpoints', 'bitmomo_hide_public_rest_users', 20);" )
);

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );
