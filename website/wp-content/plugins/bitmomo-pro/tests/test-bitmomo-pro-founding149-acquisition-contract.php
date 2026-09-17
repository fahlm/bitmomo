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
$security = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-public-security.php' );
$main = file_get_contents( dirname( __DIR__ ) . '/bitmomo-pro.php' );
$repo_root = dirname( __DIR__, 5 );
$staging_guard = file_get_contents( $repo_root . '/config/staging/bitmomo-staging-safety.php' );

check_founding149_contract( 'Campaign id is the single approved Founding 149 Telegram motion', false !== strpos( $source, "const CAMPAIGN = 'founding149_tlw_v1';" ) );
check_founding149_contract( 'Scoreboard reuses canonical whitelist post type and UTM campaign metadata', false !== strpos( $source, 'Bitmomo_Pro_Whitelist::POST_TYPE' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN' ) );
check_founding149_contract( 'Scoreboard reuses canonical validation/status metadata', false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::META_STATUS' ) && false !== strpos( $source, 'Bitmomo_Pro_Whitelist::STATUSES' ) );
check_founding149_contract( 'Test/internal records are excluded from external demand count', false !== strpos( $source, "array( 'test', 'internal' )" ) && false !== strpos( $source, "out['excluded']++" ) );
check_founding149_contract( 'Scoreboard is founder/admin-only presentation and adds no public endpoint', false !== strpos( $source, "current_user_can( 'manage_options' )" ) && false === strpos( $source, 'register_post_type' ) && false === strpos( $source, 'register_rest_route' ) && false === strpos( $source, 'wp_ajax_' ) );
check_founding149_contract( 'Canonical whitelist remains private and non-REST', false !== strpos( $whitelist, "'public'              => false" ) && false !== strpos( $whitelist, "'show_in_rest'        => false" ) );
check_founding149_contract( 'Whitelist launch surface disables page cache while checkout is absent',
	false !== strpos( $security, 'disable_cache_for_whitelist_surface' ) &&
	false !== strpos( $security, "define( 'DONOTCACHEPAGE', true )" ) &&
	false !== strpos( $security, 'litespeed_control_set_nocache' ) &&
	false !== strpos( $security, "has_shortcode( \$content, 'bitmomo_pro_sales' )" ) &&
	false !== strpos( $security, "has_shortcode( \$content, 'bitmomo_pro_whitelist' )" )
);
check_founding149_contract( 'Anonymous WordPress user enumeration is removed without blocking authenticated REST use',
	false !== strpos( $security, 'hide_public_user_routes' ) &&
	false !== strpos( $security, 'is_user_logged_in()' ) &&
	false !== strpos( $security, "'/wp/v2/users'" )
);
check_founding149_contract( 'Public security boundary is bootstrapped by Bitmomo Pro',
	false !== strpos( $main, 'class-bitmomo-pro-public-security.php' ) &&
	false !== strpos( $main, 'Bitmomo_Pro_Public_Security::instance()' )
);
check_founding149_contract( 'Staging-only guard emits noindex header and deny-all robots policy',
	false !== strpos( $staging_guard, 'X-Robots-Tag: noindex, nofollow, noarchive' ) &&
	false !== strpos( $staging_guard, 'User-agent: *\\nDisallow: /' )
);

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );
