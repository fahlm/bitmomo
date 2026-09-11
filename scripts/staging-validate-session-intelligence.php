<?php
/** Run with `wp eval-file` on Bitmomo staging only. */

if (!defined('WP_CLI') || !WP_CLI || get_option('siteurl') !== 'https://seagreen-snail-158456.hostingersite.com') {
    fwrite(STDERR, "Refusing non-staging session validation.\n");
    exit(2);
}
if (!defined('BITMOMO_STAGING_SIDE_EFFECTS_DISABLED') || !BITMOMO_STAGING_SIDE_EFFECTS_DISABLED) {
    fwrite(STDERR, "Refusing validation without staging side-effect guards.\n");
    exit(2);
}

$option_names = [
    Bitmomo_AI_Runtime_State::ATTEMPT_OPTION,
    Bitmomo_AI_Runtime_State::VALID_OPTION,
    Bitmomo_AI_Session_Intelligence::HISTORY_OPTION,
    'bitmomo_ai_latest_preview',
];
$original = [];
foreach ($option_names as $name) {
    $value = get_option($name, '__bitmomo_option_missing__');
    $original[$name] = ['exists' => $value !== '__bitmomo_option_missing__', 'value' => $value];
}

$checks = [];
$check = static function ($name, $passed) use (&$checks) {
    $checks[$name] = (bool) $passed;
    echo $name . '=' . ($passed ? 'PASS' : 'FAIL') . "\n";
};
$fixture = static function ($session_type, $generated_at, array $history = [], $bias = 'bullish', $confidence = 70) {
    $data = [
        'timestamp' => $generated_at,
        'close' => 65000,
        'quality' => ['status' => 'complete', 'completeness_pct' => 100],
        'volatility' => ['regime' => 'normal'],
        'structure' => ['state' => $bias === 'bearish' ? 'lh_ll' : 'hh_hl'],
        'carry' => ['funding_rate' => 0.0001, 'basis_pct' => 0.05],
        'crowding' => ['price_change_24h_pct' => 1.25, 'oi_change_24h_pct' => 2.5],
        'source_diagnostics' => [['requested_input' => 'candles_1h', 'provider' => 'binance_usdm', 'observed_at' => $generated_at, 'freshness' => 'fresh']],
    ];
    $evaluation = [
        'bias' => $bias,
        'direction_strength' => $bias,
        'confidence' => $confidence,
        'quality' => $data['quality'],
        'axes' => [],
    ];
    return Bitmomo_AI_Session_Intelligence::build_record($session_type, $data, $evaluation, ['status' => 'passed'], $generated_at, $history);
};
$passed_gate = ['status' => 'passed', 'checked_at' => gmdate('c'), 'failed_keys' => [], 'critical_failures' => [], 'hard_blocked' => false];
$blocked_gate = ['status' => 'blocked', 'checked_at' => gmdate('c'), 'failed_keys' => ['completeness'], 'critical_failures' => ['completeness'], 'hard_blocked' => true];

try {
    $pre = $fixture('us_pre_open', '2026-09-10T08:10:00-04:00');
    $post = $fixture('us_post_close', '2026-09-10T20:10:00-04:00', [$pre['edition_id'] => $pre], 'bearish', 55);
    $check('pre_open_edition', $pre['session_type'] === 'us_pre_open' && $pre['session_anchor'] === '2026-09-10T08:10:00-04:00');
    $check('post_close_edition', $post['session_type'] === 'us_post_close' && $post['session_anchor'] === '2026-09-10T20:10:00-04:00');
    $check('opposite_session_comparison', $post['comparison_source_record_id'] === $pre['edition_id']);
    $check('structured_changes', !empty($post['session_intelligence']['what_changed']));

    $weekend = $fixture('us_pre_open', '2026-09-12T08:10:00-04:00', [$post['edition_id'] => $post]);
    $holiday = $fixture('us_pre_open', '2026-07-03T08:10:00-04:00');
    $check('weekend_generation', $weekend['canonical_status'] === 'valid' && $weekend['us_market_status'] === 'weekend');
    $check('holiday_generation', $holiday['canonical_status'] === 'valid' && $holiday['us_market_status'] === 'holiday');
    $check('closed_day_language', $weekend['session_label'] === 'US MARKETS CLOSED - BTC UPDATE');
    $check('crypto_24_7_context', $weekend['session_intelligence']['crypto_context']['market_status'] === 'trading_24_7');

    Bitmomo_AI_Runtime_State::record_valid_snapshot($pre, $passed_gate);
    $valid_id = Bitmomo_AI_Runtime_State::latest_valid_snapshot()['edition_id'];
    Bitmomo_AI_Runtime_State::record_attempt('us_post_close', 'blocked', ['quality' => ['completeness_pct' => 60]], $blocked_gate, new WP_Error('quality_blocked', 'controlled fixture'));
    $check('blocked_attempt_retains_valid', Bitmomo_AI_Runtime_State::latest_valid_snapshot()['edition_id'] === $valid_id);

    $delayed = $pre;
    $delayed['generated_at'] = gmdate('c', time() - (7 * HOUR_IN_SECONDS));
    Bitmomo_AI_Runtime_State::record_valid_snapshot($delayed, $passed_gate);
    $check('delayed_edition', Bitmomo_AI_Intelligence::free_projection()['status'] === 'delayed');

    $expired = $pre;
    $expired['generated_at'] = gmdate('c', time() - (31 * HOUR_IN_SECONDS));
    Bitmomo_AI_Runtime_State::record_valid_snapshot($expired, $passed_gate);
    $check('expired_edition', Bitmomo_AI_Intelligence::free_projection()['status'] === 'unavailable');

    $dst = Bitmomo_AI_Session_Intelligence::next_anchor('us_pre_open', new DateTimeImmutable('2026-03-09T07:00:00', new DateTimeZone('America/New_York')));
    $standard = Bitmomo_AI_Session_Intelligence::next_anchor('us_pre_open', new DateTimeImmutable('2026-01-12T07:00:00', new DateTimeZone('America/New_York')));
    $check('dst_anchor', $dst->format('H:i P') === '08:10 -04:00');
    $check('standard_anchor', $standard->format('H:i P') === '08:10 -05:00');
} finally {
    foreach ($original as $name => $state) {
        if ($state['exists']) update_option($name, $state['value'], false);
        else delete_option($name);
    }
}

if (in_array(false, $checks, true)) exit(1);
echo "session_state_restored=PASS\n";
