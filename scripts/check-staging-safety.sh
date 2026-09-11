#!/usr/bin/env bash
set -euo pipefail

: "${BITMOMO_STAGING_ROOT:?BITMOMO_STAGING_ROOT is required}"

host="${BITMOMO_STAGING_HOST:-bitmomo-staging}"
site="${BITMOMO_STAGING_SITE:-seagreen-snail-158456.hostingersite.com}"
expected_site="seagreen-snail-158456.hostingersite.com"

if [[ "$site" != "$expected_site" || "$BITMOMO_STAGING_ROOT" != *"/domains/$expected_site/public_html" ]]; then
	echo "Refusing non-staging target." >&2
	exit 2
fi

ssh "$host" "BITMOMO_STAGING_ROOT=$(printf '%q' "$BITMOMO_STAGING_ROOT") bash -s" <<'REMOTE'
set -euo pipefail
cd "$BITMOMO_STAGING_ROOT"

test "$(wp option get siteurl --path="$BITMOMO_STAGING_ROOT")" = "https://seagreen-snail-158456.hostingersite.com"
test "$(wp option get home --path="$BITMOMO_STAGING_ROOT")" = "https://seagreen-snail-158456.hostingersite.com"
test "$(wp option get blog_public --path="$BITMOMO_STAGING_ROOT")" = "0"
test -f wp-content/mu-plugins/bitmomo-staging-safety.php

wp eval '
$checks = array(
    "environment_guard" => defined("BITMOMO_STAGING_SIDE_EFFECTS_DISABLED") && BITMOMO_STAGING_SIDE_EFFECTS_DISABLED,
    "auto_publish" => defined("BITMOMO_AI_AUTO_PUBLISH") && ! BITMOMO_AI_AUTO_PUBLISH,
    "checkout" => function_exists("bitmomo_pro_get_checkout_url") && "" === bitmomo_pro_get_checkout_url(),
    "email" => false === wp_mail("blocked@example.invalid", "staging guard", "must not send"),
);
$http = wp_remote_post("https://example.com/", array("body" => array("probe" => "blocked")));
$checks["outbound_http_write"] = is_wp_error($http) && "bitmomo_staging_outbound_write_disabled" === $http->get_error_code();
foreach ($checks as $name => $passed) {
    echo $name . "=" . ($passed ? "disabled" : "UNSAFE") . "\n";
    if (!$passed) exit(1);
}
' --path="$BITMOMO_STAGING_ROOT"
REMOTE

