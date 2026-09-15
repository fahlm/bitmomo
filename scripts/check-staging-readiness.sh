#!/usr/bin/env bash
set -euo pipefail

: "${BITMOMO_STAGING_ROOT:?BITMOMO_STAGING_ROOT is required}"

host="${BITMOMO_STAGING_HOST:-bitmomo-staging}"
site="${BITMOMO_STAGING_SITE:-seagreen-snail-158456.hostingersite.com}"
profile="${BITMOMO_RELEASE_PROFILE:-whitelist}"
expected_site="seagreen-snail-158456.hostingersite.com"
max_btc_age_hours="${BITMOMO_MAX_BTC_AGE_HOURS:-30}"
min_qualified_research="${BITMOMO_MIN_QUALIFIED_RESEARCH:-2}"

if [[ "$profile" != "whitelist" && "$profile" != "paid" ]]; then
  echo "Unsupported BITMOMO_RELEASE_PROFILE=$profile (expected whitelist or paid)." >&2
  exit 2
fi

if ! [[ "$max_btc_age_hours" =~ ^[0-9]+$ ]] || (( max_btc_age_hours < 1 || max_btc_age_hours > 168 )); then
  echo "BITMOMO_MAX_BTC_AGE_HOURS must be an integer between 1 and 168." >&2
  exit 2
fi

if ! [[ "$min_qualified_research" =~ ^[0-9]+$ ]] || (( min_qualified_research < 1 || min_qualified_research > 10 )); then
  echo "BITMOMO_MIN_QUALIFIED_RESEARCH must be an integer between 1 and 10." >&2
  exit 2
fi

if [[ "$site" != "$expected_site" || "$BITMOMO_STAGING_ROOT" != *"/domains/$expected_site/public_html" ]]; then
  echo "Refusing non-staging target." >&2
  exit 2
fi

ssh "$host" "BITMOMO_STAGING_ROOT=$(printf '%q' "$BITMOMO_STAGING_ROOT") BITMOMO_RELEASE_PROFILE=$(printf '%q' "$profile") BITMOMO_MAX_BTC_AGE_HOURS=$(printf '%q' "$max_btc_age_hours") BITMOMO_MIN_QUALIFIED_RESEARCH=$(printf '%q' "$min_qualified_research") bash -s" <<'REMOTE'
set -euo pipefail
cd "$BITMOMO_STAGING_ROOT"

test "$(wp option get siteurl --path="$BITMOMO_STAGING_ROOT")" = "https://seagreen-snail-158456.hostingersite.com"
test "$(wp option get home --path="$BITMOMO_STAGING_ROOT")" = "https://seagreen-snail-158456.hostingersite.com"
test "$(wp option get blog_public --path="$BITMOMO_STAGING_ROOT")" = "0"
test -f wp-content/mu-plugins/bitmomo-staging-safety.php

wp eval '
require_once ABSPATH . "wp-admin/includes/plugin.php";

$profile = getenv("BITMOMO_RELEASE_PROFILE") ?: "whitelist";
$max_btc_age_hours = max( 1, (int) ( getenv("BITMOMO_MAX_BTC_AGE_HOURS") ?: 30 ) );
$min_qualified_research = max( 1, (int) ( getenv("BITMOMO_MIN_QUALIFIED_RESEARCH") ?: 2 ) );

$page = static function ( $slug ) {
    $value = get_page_by_path( $slug, OBJECT, "page" );
    return $value && "publish" === get_post_status( $value ) ? $value : null;
};

$normalize = static function ( $value ) {
    $value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES | ENT_HTML5, "UTF-8" );
    $value = function_exists( "mb_strtolower" ) ? mb_strtolower( $value, "UTF-8" ) : strtolower( $value );
    return preg_replace( "/\\s+/u", " ", trim( $value ) );
};

$contains_all = static function ( $haystack, array $needles ) use ( $normalize ) {
    $haystack = $normalize( $haystack );
    foreach ( $needles as $needle ) {
        if ( false === strpos( $haystack, $normalize( $needle ) ) ) {
            return false;
        }
    }
    return true;
};

$privacy_page = $page( "kebijakan-privasi" );
$disclaimer_page = $page( "disclaimer" );
$terms_page = $page( "syarat-layanan" );

// These semantic markers intentionally track release-critical facts rather
// than HTML formatting. They prevent an old published legal page from being
// accepted merely because the slug exists.
$privacy_content_current = $privacy_page && $contains_all(
    $privacy_page->post_content,
    array(
        "Founding Whitelist dan Komunikasi Produk",
        "WhatsApp Jika Diaktifkan",
        "utm_source",
        "telemetri first-party",
        "Terakhir diperbarui: 14 September 2026",
    )
);
$disclaimer_content_current = $disclaimer_page && $contains_all(
    $disclaimer_page->post_content,
    array(
        "Market Intelligence, Bukan Sinyal Transaksi",
        "Decision Ledger",
        "Market Context",
        "Confidence bukan probabilitas",
        "Terakhir diperbarui: 13 September 2026",
    )
);

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
    : array( "available" => false, "status" => "unavailable", "as_of" => "" );

$btc_status = sanitize_key( (string) ( $snapshot["status"] ?? "unavailable" ) );
$btc_timestamp_iso = trim( (string) ( $snapshot["as_of"] ?? $snapshot["freshness"]["timestamp_iso"] ?? $snapshot["provenance"]["as_of"] ?? "" ) );
$btc_timestamp = $btc_timestamp_iso !== "" ? strtotime( $btc_timestamp_iso ) : false;
$btc_clock_skew_seconds = $btc_timestamp ? ( $btc_timestamp - time() ) : null;
$btc_age_seconds = $btc_timestamp ? max( 0, time() - $btc_timestamp ) : null;
$btc_age_hours = null !== $btc_age_seconds ? round( $btc_age_seconds / HOUR_IN_SECONDS, 2 ) : null;
$btc_timestamp_plausible = $btc_timestamp && $btc_clock_skew_seconds <= 300;
$btc_within_launch_age = $btc_timestamp_plausible && $btc_age_seconds <= ( $max_btc_age_hours * HOUR_IN_SECONDS );
$btc_operational = ! empty( $snapshot["available"] )
    && in_array( $btc_status, array( "fresh", "delayed" ), true )
    && $btc_within_launch_age;

$whitelist_operational = class_exists( "Bitmomo_Pro_Whitelist" )
    && shortcode_exists( "bitmomo_pro_whitelist" )
    && false !== has_action( "wp_ajax_nopriv_" . Bitmomo_Pro_Whitelist::AJAX_ACTION );
$whatsapp_opt_in_enabled = class_exists( "Bitmomo_Pro_Whitelist" )
    && method_exists( "Bitmomo_Pro_Whitelist", "whatsapp_opt_in_enabled" )
    && Bitmomo_Pro_Whitelist::whatsapp_opt_in_enabled();

// Exercise the real whitelist core write path rather than constructing a
// synthetic post manually: reject missing consent -> create -> persist ->
// dedupe. Capture the generated wp_mail arguments and short-circuit transport
// before PHPMailer so this probe can never send an external message.
$whitelist_persistence_contract = false;
$whitelist_confirmation_contract = false;
$whitelist_confirmation_capture_count = 0;
$whitelist_probe_cleaned = true;
if ( class_exists( "Bitmomo_Pro_Email_Service" ) && class_exists( "Bitmomo_Pro_Whitelist" ) ) {
    $captured_mails = array();
    add_filter(
        "wp_mail",
        static function ( $atts ) use ( &$captured_mails ) {
            $captured_mails[] = $atts;
            return $atts;
        },
        PHP_INT_MIN,
        1
    );
    add_filter( "pre_wp_mail", static function () { return true; }, PHP_INT_MIN, 2 );

    $probe_email = "bitmomo-staging-readiness+" . time() . "@example.com";
    $service = Bitmomo_Pro_Whitelist::instance();
    $common = array(
        "email"        => $probe_email,
        "first_name"   => "Staging",
        "source"       => "staging_readiness",
        "landing_page" => home_url( "/pro/" ),
        "utm_source"   => "staging-readiness",
        "utm_medium"   => "release-gate",
        "utm_campaign" => "whitelist-v1",
        "referrer"     => "",
    );

    $missing_consent = $service->submit_entry( array_merge( $common, array( "consent" => false ) ) );
    $missing_consent_rejected = empty( $missing_consent["ok"] )
        && "consent_required" === ( $missing_consent["error"] ?? "" )
        && 0 === $service->find_post_id_by_email( $probe_email );

    $created = $service->submit_entry( array_merge( $common, array( "consent" => true ) ) );
    $probe_post_id = ! empty( $created["ok"] ) ? (int) ( $created["post_id"] ?? 0 ) : 0;
    $duplicate = $service->submit_entry( array_merge( $common, array( "consent" => true ) ) );

    $persisted = $probe_post_id > 0
        && Bitmomo_Pro_Whitelist::POST_TYPE === get_post_type( $probe_post_id )
        && $probe_email === get_post_meta( $probe_post_id, Bitmomo_Pro_Whitelist::META_EMAIL_NORMALIZED, true )
        && "waiting" === get_post_meta( $probe_post_id, Bitmomo_Pro_Whitelist::META_STATUS, true )
        && "staging_readiness" === get_post_meta( $probe_post_id, Bitmomo_Pro_Whitelist::META_SOURCE, true )
        && "unclassified" === get_post_meta( $probe_post_id, Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true )
        && "" !== get_post_meta( $probe_post_id, Bitmomo_Pro_Whitelist::META_CONSENT_AT, true );

    $deduped = ! empty( $duplicate["ok"] )
        && "duplicate" === ( $duplicate["status"] ?? "" )
        && $probe_post_id === (int) ( $duplicate["post_id"] ?? 0 );

    $whitelist_confirmation_capture_count = count( $captured_mails );
    $mail = 1 === $whitelist_confirmation_capture_count ? $captured_mails[0] : array();
    $to = $mail["to"] ?? "";
    $recipient_matches = is_array( $to ) ? in_array( $probe_email, $to, true ) : $probe_email === (string) $to;
    $subject = (string) ( $mail["subject"] ?? "" );
    $message = (string) ( $mail["message"] ?? "" );

    $whitelist_persistence_contract = $missing_consent_rejected
        && "created" === ( $created["status"] ?? "" )
        && $persisted
        && $deduped;

    $whitelist_confirmation_contract = $recipient_matches
        && false !== strpos( $subject, "whitelist Bitmomo Pro" )
        && false !== strpos( $message, "Whitelist berhasil" )
        && false !== strpos( $message, "bitmomo.id" )
        && false !== strpos( $message, "belum menjamin tempat" )
        && false === strpos( $message, "Tambahkan nomor WhatsApp" );

    if ( $probe_post_id > 0 ) {
        wp_delete_post( $probe_post_id, true );
    }
    $whitelist_probe_cleaned = 0 === $service->find_post_id_by_email( $probe_email );
}

$checkout_url = function_exists( "bitmomo_pro_get_checkout_url" ) ? (string) bitmomo_pro_get_checkout_url() : "";
$mailpoet_active = is_plugin_active( "mailpoet/mailpoet.php" );

$report = array(
    "profile"                              => $profile,
    "theme_version"                        => defined( "BM_VERSION" ) ? BM_VERSION : "",
    "btc_plugin_active"                    => is_plugin_active( "bitmomo-btc-intelligence/bitmomo-btc-intelligence.php" ),
    "pro_plugin_active"                    => is_plugin_active( "bitmomo-pro/bitmomo-pro.php" ),
    "ai_plugin_active"                     => is_plugin_active( "bitmomo-ai/bitmomo-ai.php" ),
    "mailpoet_active"                      => $mailpoet_active,
    "privacy_published"                    => (bool) $privacy_page,
    "privacy_content_current"              => (bool) $privacy_content_current,
    "disclaimer_published"                 => (bool) $disclaimer_page,
    "disclaimer_content_current"           => (bool) $disclaimer_content_current,
    "terms_published"                      => (bool) $terms_page,
    "qualified_market_research"            => $qualified_market_research,
    "minimum_qualified_market_research"    => $min_qualified_research,
    "btc_snapshot_available"               => ! empty( $snapshot["available"] ),
    "btc_snapshot_status"                  => $btc_status,
    "btc_snapshot_timestamp"               => $btc_timestamp_iso,
    "btc_snapshot_age_hours"               => $btc_age_hours,
    "btc_max_launch_age_hours"              => $max_btc_age_hours,
    "btc_operational"                      => $btc_operational,
    "whitelist_operational"                => $whitelist_operational,
    "whatsapp_opt_in_enabled"              => $whatsapp_opt_in_enabled,
    "whitelist_persistence_contract"       => $whitelist_persistence_contract,
    "whitelist_confirmation_contract"      => $whitelist_confirmation_contract,
    "whitelist_confirmation_capture_count" => $whitelist_confirmation_capture_count,
    "whitelist_probe_cleaned"              => $whitelist_probe_cleaned,
    "checkout_configured"                  => "" !== trim( $checkout_url ),
);

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

$blocking = array(
    "btc_plugin_active"                  => $report["btc_plugin_active"],
    "pro_plugin_active"                  => $report["pro_plugin_active"],
    "ai_plugin_active"                   => $report["ai_plugin_active"],
    "privacy_published"                  => $report["privacy_published"],
    "privacy_content_current"            => $report["privacy_content_current"],
    "disclaimer_published"               => $report["disclaimer_published"],
    "disclaimer_content_current"         => $report["disclaimer_content_current"],
    "qualified_market_research"          => $qualified_market_research >= $min_qualified_research,
    "btc_operational_within_age_budget"  => $btc_operational,
);

if ( "whitelist" === $profile ) {
    $blocking["whitelist_operational"] = $whitelist_operational;
    $blocking["whatsapp_opt_in_fail_closed"] = ! $whatsapp_opt_in_enabled;
    $blocking["whitelist_persistence_contract"] = $whitelist_persistence_contract;
    $blocking["whitelist_confirmation_contract"] = $whitelist_confirmation_contract;
    $blocking["whitelist_probe_cleaned"] = $whitelist_probe_cleaned;
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
