#!/usr/bin/env bash
set -euo pipefail

: "${BITMOMO_STAGING_ROOT:?BITMOMO_STAGING_ROOT is required}"

host="${BITMOMO_STAGING_HOST:-bitmomo-staging}"
site="${BITMOMO_STAGING_SITE:-seagreen-snail-158456.hostingersite.com}"
profile="${BITMOMO_RELEASE_PROFILE:-whitelist}"
expected_site="seagreen-snail-158456.hostingersite.com"

if [[ "$profile" != "whitelist" && "$profile" != "paid" ]]; then
  echo "Unsupported BITMOMO_RELEASE_PROFILE=$profile (expected whitelist or paid)." >&2
  exit 2
fi

if [[ "$site" != "$expected_site" || "$BITMOMO_STAGING_ROOT" != *"/domains/$expected_site/public_html" ]]; then
  echo "Refusing non-staging target." >&2
  exit 2
fi

ssh "$host" "BITMOMO_STAGING_ROOT=$(printf '%q' "$BITMOMO_STAGING_ROOT") BITMOMO_RELEASE_PROFILE=$(printf '%q' "$profile") bash -s" <<'REMOTE'
set -euo pipefail
cd "$BITMOMO_STAGING_ROOT"

test "$(wp option get siteurl --path="$BITMOMO_STAGING_ROOT")" = "https://seagreen-snail-158456.hostingersite.com"
test "$(wp option get home --path="$BITMOMO_STAGING_ROOT")" = "https://seagreen-snail-158456.hostingersite.com"
test "$(wp option get blog_public --path="$BITMOMO_STAGING_ROOT")" = "0"
test -f wp-content/mu-plugins/bitmomo-staging-safety.php

wp eval '
require_once ABSPATH . "wp-admin/includes/plugin.php";

$profile = getenv("BITMOMO_RELEASE_PROFILE") ?: "whitelist";

$page_published = static function ( $slug ) {
    $page = get_page_by_path( $slug, OBJECT, "page" );
    return $page && "publish" === get_post_status( $page );
};

$qualified_market_research = 0;
if ( function_exists( "bitmomo_post_is_market_research" ) ) {
    $query = new WP_Query(
        array(
            "post_type"      => "post",
            "post_status"    => "publish",
            "posts_per_page" => 100,
            "fields"         => "ids",
            "no_found_rows"  => true,
        )
    );
    foreach ( $query->posts as $post_id ) {
        if ( bitmomo_post_is_market_research( (int) $post_id ) ) {
            ++$qualified_market_research;
        }
    }
}

$snapshot = function_exists( "bitmomo_public_snapshot_contract" )
    ? bitmomo_public_snapshot_contract()
    : array( "available" => false, "status" => "unavailable" );

$btc_status = sanitize_key( (string) ( $snapshot["status"] ?? "unavailable" ) );
$btc_operational = ! empty( $snapshot["available"] ) && in_array( $btc_status, array( "fresh", "delayed" ), true );

$whitelist_operational = class_exists( "Bitmomo_Pro_Whitelist" )
    && shortcode_exists( "bitmomo_pro_whitelist" )
    && false !== has_action( "wp_ajax_nopriv_" . Bitmomo_Pro_Whitelist::AJAX_ACTION );

$checkout_url = function_exists( "bitmomo_pro_get_checkout_url" ) ? (string) bitmomo_pro_get_checkout_url() : "";
$mailpoet_active = is_plugin_active( "mailpoet/mailpoet.php" );

$report = array(
    "profile"                    => $profile,
    "theme_version"              => defined( "BM_VERSION" ) ? BM_VERSION : "",
    "btc_plugin_active"          => is_plugin_active( "bitmomo-btc-intelligence/bitmomo-btc-intelligence.php" ),
    "pro_plugin_active"          => is_plugin_active( "bitmomo-pro/bitmomo-pro.php" ),
    "ai_plugin_active"           => is_plugin_active( "bitmomo-ai/bitmomo-ai.php" ),
    "mailpoet_active"            => $mailpoet_active,
    "privacy_published"          => $page_published( "kebijakan-privasi" ),
    "disclaimer_published"       => $page_published( "disclaimer" ),
    "terms_published"            => $page_published( "syarat-layanan" ),
    "qualified_market_research"  => $qualified_market_research,
    "btc_snapshot_available"     => ! empty( $snapshot["available"] ),
    "btc_snapshot_status"        => $btc_status,
    "btc_operational"            => $btc_operational,
    "whitelist_operational"      => $whitelist_operational,
    "checkout_configured"        => "" !== trim( $checkout_url ),
);

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

$blocking = array(
    "btc_plugin_active"         => $report["btc_plugin_active"],
    "pro_plugin_active"         => $report["pro_plugin_active"],
    "ai_plugin_active"          => $report["ai_plugin_active"],
    "privacy_published"         => $report["privacy_published"],
    "disclaimer_published"      => $report["disclaimer_published"],
    "qualified_market_research" => $qualified_market_research >= 1,
    "btc_operational"           => $btc_operational,
);

if ( "whitelist" === $profile ) {
    $blocking["whitelist_operational"] = $whitelist_operational;
    $blocking["checkout_not_live"] = "" === trim( $checkout_url );
} else {
    $blocking["terms_published"] = $report["terms_published"];
    $blocking["checkout_configured"] = "" !== trim( $checkout_url );
}

$failed = array();
foreach ( $blocking as $name => $passed ) {
    if ( ! $passed ) {
        $failed[] = $name;
    }
}

if ( $failed ) {
    fwrite( STDERR, "STAGING PRODUCT READINESS FAIL: " . implode( ", ", $failed ) . "\n" );
    exit( 1 );
}

echo "STAGING PRODUCT READINESS PASS ($profile)\n";
' --path="$BITMOMO_STAGING_ROOT"
REMOTE
