<?php
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);

function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function add_action(...$args) {}
function update_option(...$args) { return true; }

class Bitmomo_AI_Content_Types { const SIGNAL = 'bm_signal'; }

$GLOBALS['__perf_posts'] = [];
$GLOBALS['__perf_meta'] = [];
function get_posts($args) { return array_keys($GLOBALS['__perf_posts']); }
function get_post_meta($post_id, $key, $single = true) { return $GLOBALS['__perf_meta'][$post_id][$key] ?? ''; }
function update_post_meta($post_id, $key, $value) { $GLOBALS['__perf_meta'][$post_id][$key] = $value; return true; }
function get_post_status($post_id) { return 'draft'; }
function get_post_time($format, $gmt, $post_id) { return 0; }

require __DIR__ . '/../includes/class-bitmomo-ai-performance.php';

$checks = [];
function perf_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }
function source_diag($provider = 'binance_usdm') {
    return [['requested_input' => 'candles_1h', 'provider' => $provider, 'success' => true]];
}
function signal_input($observed_at, $provider = 'binance_usdm') {
    return [
        'quality' => ['last_closed_candle' => $observed_at],
        'source_diagnostics' => source_diag($provider),
    ];
}
function seed_signal($id, $observed_at, $provider = 'binance_usdm', $bias = 'bullish') {
    $GLOBALS['__perf_posts'][$id] = true;
    $GLOBALS['__perf_meta'][$id] = [
        '_bm_input_snapshot' => json_encode(signal_input($observed_at, $provider)),
        '_bm_market_price' => '100',
        '_bm_direction' => $bias,
        '_bm_outcome_status' => 'pending',
        '_bm_support_high' => '99',
        '_bm_resistance_low' => '102',
        '_bm_resistance_high' => '103',
        '_bm_risk_level' => '98',
    ];
}

seed_signal(1, '2026-09-11T12:59:59+00:00'); // target exactly current observation.
seed_signal(2, '2026-09-11T00:59:59+00:00'); // target was 12h earlier: must not borrow current endpoint.
seed_signal(3, '2026-09-11T18:59:59+00:00'); // target still 6h in the future.
seed_signal(4, '2026-09-11T12:59:59+00:00', 'binance_spot'); // exact time, wrong price provider.

$market = [
    'close' => 101,
    'quality' => ['last_closed_candle' => '2026-09-12T12:59:59+00:00'],
    'source_diagnostics' => source_diag('binance_usdm'),
    'outcome_window' => ['high_24h' => 103, 'low_24h' => 97, 'closed_candles' => 24],
];

$settled = Bitmomo_AI_Performance::settle($market);
perf_check('only the exact same-provider 24h signal is evaluated', 1 === $settled);
perf_check('exact 24h signal is evaluated', 'evaluated' === get_post_meta(1, '_bm_outcome_status', true));
perf_check('exact outcome methodology is versioned', Bitmomo_AI_Performance::OUTCOME_METHOD === get_post_meta(1, '_bm_outcome_methodology', true));
perf_check('exact endpoint produces the directional result', 'correct' === get_post_meta(1, '_bm_outcome_direction', true));
perf_check('exact window stores 24 closed candles', '24' === get_post_meta(1, '_bm_outcome_closed_candles', true));
perf_check('exact window stores zero alignment drift', '0' === get_post_meta(1, '_bm_outcome_alignment_seconds', true));
perf_check('late signal is marked missed instead of borrowing a later endpoint', 'window_missed' === get_post_meta(2, '_bm_outcome_status', true) && 'exact_24h_snapshot_missed' === get_post_meta(2, '_bm_outcome_missed_reason', true));
perf_check('future signal remains pending', 'pending' === get_post_meta(3, '_bm_outcome_status', true));
perf_check('provider mismatch fails closed', 'window_missed' === get_post_meta(4, '_bm_outcome_status', true) && 'price_provider_mismatch' === get_post_meta(4, '_bm_outcome_missed_reason', true));

$GLOBALS['__perf_posts'] = [5 => true];
$GLOBALS['__perf_meta'] = [];
seed_signal(5, '2026-09-11T12:59:59+00:00');
$bad_window = $market;
$bad_window['outcome_window']['closed_candles'] = 23;
perf_check('incomplete 24h candle window is rejected globally', 0 === Bitmomo_AI_Performance::settle($bad_window));
perf_check('rejected incomplete window leaves signal pending', 'pending' === get_post_meta(5, '_bm_outcome_status', true));

$passed = count(array_filter($checks, function ($row) { return $row[1]; }));
foreach ($checks as $row) printf("[%s] %s\n", $row[1] ? 'PASS' : 'FAIL', $row[0]);
printf("\n%d/%d passed.\n", $passed, count($checks));
exit($passed === count($checks) ? 0 : 1);
