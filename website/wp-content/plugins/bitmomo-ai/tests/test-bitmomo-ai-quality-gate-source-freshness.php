<?php
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function __($value, $domain = null) { return $value; }
function update_option($name, $value, $autoload = null) { return true; }
class WP_Error { public function __construct($code, $message, $data = null) {} }

require __DIR__ . '/../includes/class-bitmomo-ai-quality-gate.php';

$checks = [];
function source_freshness_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

function valid_gate_data($diagnostics) {
    return [
        'timestamp' => gmdate('c'), 'close' => 100,
        'volatility' => ['regime' => 'normal'], 'direction' => ['adx' => 30],
        'carry' => ['funding_rate' => 0.0001], 'structure' => ['state' => 'range'],
        'crowding' => ['oi_change_24h_pct' => 2],
        'quality' => ['status' => 'complete', 'completeness_pct' => 100, 'data_age_minutes' => 1],
        'source_diagnostics' => $diagnostics,
    ];
}
function valid_gate_evaluation() {
    return [
        'bias' => 'bullish', 'score' => 40,
        'risk' => [
            'support_zone_low' => 94, 'support_zone_high' => 96,
            'resistance_zone_low' => 104, 'resistance_zone_high' => 106,
            'invalidation' => 93,
        ],
    ];
}
function derivative_diagnostics($overrides = []) {
    $rows = [
        ['requested_input' => 'funding', 'success' => true, 'age_minutes' => 500],
        ['requested_input' => 'premium_index', 'success' => true, 'age_minutes' => 5],
        ['requested_input' => 'open_interest', 'success' => true, 'age_minutes' => 60],
        ['requested_input' => 'global_long_short_ratio', 'success' => true, 'age_minutes' => 60],
        ['requested_input' => 'taker_buy_sell_ratio', 'success' => true, 'age_minutes' => 60],
    ];
    foreach ($overrides as $name => $values) {
        foreach ($rows as &$row) if ($row['requested_input'] === $name) $row = array_merge($row, $values);
        unset($row);
    }
    return $rows;
}

$clean = Bitmomo_AI_Quality_Gate::inspect(valid_gate_data(derivative_diagnostics()), valid_gate_evaluation());
source_freshness_check('fresh derivative observations pass the release gate', $clean['status'] === 'passed' && !in_array('source_freshness', $clean['failed_keys'], true));

$stale_oi = Bitmomo_AI_Quality_Gate::inspect(valid_gate_data(derivative_diagnostics(['open_interest' => ['age_minutes' => 151]])), valid_gate_evaluation());
source_freshness_check('stale hourly open interest hard-blocks the reading', $stale_oi['status'] === 'blocked' && in_array('source_freshness', $stale_oi['critical_failures'], true));

$stale_funding = Bitmomo_AI_Quality_Gate::inspect(valid_gate_data(derivative_diagnostics(['funding' => ['age_minutes' => 601]])), valid_gate_evaluation());
source_freshness_check('funding has a wider cadence-aware freshness limit but still fails closed when too old', $stale_funding['status'] === 'blocked');

$unknown_age = Bitmomo_AI_Quality_Gate::inspect(valid_gate_data(derivative_diagnostics(['premium_index' => ['age_minutes' => null]])), valid_gate_evaluation());
source_freshness_check('successful derivative input without a trusted observation age fails closed', $unknown_age['status'] === 'blocked');

$failed_optional = Bitmomo_AI_Quality_Gate::inspect(valid_gate_data(derivative_diagnostics(['taker_buy_sell_ratio' => ['success' => false, 'age_minutes' => null]])), valid_gate_evaluation());
source_freshness_check('an unavailable optional source is not misclassified as a stale source', !in_array('source_freshness', $failed_optional['failed_keys'], true));

$legacy_fixture = Bitmomo_AI_Quality_Gate::inspect(valid_gate_data([]), valid_gate_evaluation());
source_freshness_check('legacy/manual fixtures without diagnostics remain backward-compatible', !in_array('source_freshness', $legacy_fixture['failed_keys'], true));

$failed = array_filter($checks, function($row) { return !$row[1]; });
foreach ($checks as $row) printf("[%s] %s\n", $row[1] ? 'PASS' : 'FAIL', $row[0]);
printf("\n%d/%d passed.\n", count($checks) - count($failed), count($checks));
exit($failed ? 1 : 0);
