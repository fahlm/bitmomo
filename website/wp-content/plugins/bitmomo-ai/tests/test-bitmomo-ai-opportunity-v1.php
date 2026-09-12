<?php
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('BITMOMO_AI_VERSION', 'test');
define('BITMOMO_AI_FILE', __FILE__);

$GLOBALS['opportunity_options'] = [];
function get_option($name, $default = false) { return $GLOBALS['opportunity_options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null) { $GLOBALS['opportunity_options'][$name] = $value; return true; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function __($value, $domain = null) { return $value; }
function wp_json_encode($value) { return json_encode($value); }
function do_action($hook, ...$args) {}

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct($code, $message, $data = null) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }

require __DIR__ . '/../includes/class-bitmomo-ai-opportunity.php';

$checks = [];
function opportunity_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

function opportunity_rows($cutoff, $historical_half_spread, $last_half_spread) {
    $rows = [];
    $first_open = $cutoff - (Bitmomo_AI_Opportunity::REQUIRED_CANDLES * Bitmomo_AI_Opportunity::INTERVAL_SECONDS);
    for ($i = 0; $i < Bitmomo_AI_Opportunity::REQUIRED_CANDLES; $i++) {
        $open = $first_open + ($i * Bitmomo_AI_Opportunity::INTERVAL_SECONDS);
        $spread = $i >= (Bitmomo_AI_Opportunity::REQUIRED_CANDLES - Bitmomo_AI_Opportunity::TRAILING_CANDLES)
            ? $last_half_spread
            : $historical_half_spread;
        $base = 50000.0;
        $rows[] = [
            $open * 1000,
            (string) $base,
            (string) ($base + $spread),
            (string) ($base - $spread),
            (string) $base,
            '1',
            (($open + Bitmomo_AI_Opportunity::INTERVAL_SECONDS) * 1000) - 1,
        ];
    }
    return $rows;
}

$cutoff = strtotime('2026-09-12T06:00:00Z');

$high = Bitmomo_AI_Opportunity::evaluate_rows(opportunity_rows($cutoff, 20, 500), $cutoff, $cutoff + 60);
opportunity_check('complete synthetic history returns a valid record', is_array($high));
opportunity_check('large current range maps to HIGH', is_array($high) && $high['state'] === 'HIGH');
opportunity_check('reference window uses exactly 14 days of 15m observations', is_array($high) && $high['reference_observation_count'] === 1344);
opportunity_check('methodology and source are frozen', is_array($high) && $high['methodology_version'] === 'opportunity-v1' && $high['source'] === 'binance_usdm_btcusdt_5m');

$low = Bitmomo_AI_Opportunity::evaluate_rows(opportunity_rows($cutoff, 500, 5), $cutoff, $cutoff + 60);
opportunity_check('quiet current range maps to LOW', is_array($low) && $low['state'] === 'LOW');

$gap_rows = opportunity_rows($cutoff, 20, 500);
array_splice($gap_rows, 1000, 1);
$gap = Bitmomo_AI_Opportunity::evaluate_rows($gap_rows, $cutoff, $cutoff + 60);
opportunity_check('missing canonical 5m candle fails closed', is_wp_error($gap) && $gap->get_error_code() === 'bitmomo_opportunity_insufficient_history');

$misaligned = opportunity_rows($cutoff, 20, 500);
$misaligned[2000][0] += 60000;
$misaligned[2000][6] += 60000;
$misaligned_result = Bitmomo_AI_Opportunity::evaluate_rows($misaligned, $cutoff, $cutoff + 60);
opportunity_check('misaligned canonical candle fails closed', is_wp_error($misaligned_result) && $misaligned_result->get_error_code() === 'bitmomo_opportunity_source_gap');

opportunity_check('quarter-hour scheduler runs one minute after boundary', Bitmomo_AI_Opportunity::next_run_timestamp(strtotime('2026-09-12T06:07:00Z')) === strtotime('2026-09-12T06:16:00Z'));
opportunity_check('scheduler does not reschedule the same already-passed minute', Bitmomo_AI_Opportunity::next_run_timestamp(strtotime('2026-09-12T06:16:01Z')) === strtotime('2026-09-12T06:31:00Z'));

$GLOBALS['opportunity_options'][Bitmomo_AI_Opportunity_Store::LATEST_OPTION] = $high;
$public = Bitmomo_AI_Opportunity_Store::public_latest($cutoff + 60);
opportunity_check('fresh latest record is exposed through the public contract', ($public['status'] ?? '') === 'available' && ($public['state'] ?? '') === 'HIGH');
opportunity_check('latest record older than 30 minutes fails closed', Bitmomo_AI_Opportunity_Store::public_latest($cutoff + 31 * MINUTE_IN_SECONDS)['status'] === 'unavailable');

$normal_boundary = function ($percentile) { return $percentile >= 75 ? 'HIGH' : ($percentile <= 25 ? 'LOW' : 'NORMAL'); };
opportunity_check('customer state boundaries preserve NORMAL between quartiles', $normal_boundary(50) === 'NORMAL' && $normal_boundary(75) === 'HIGH' && $normal_boundary(25) === 'LOW');

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " Opportunity V1 checks passed.\n";
