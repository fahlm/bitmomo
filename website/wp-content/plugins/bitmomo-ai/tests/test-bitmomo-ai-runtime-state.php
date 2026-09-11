<?php
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['runtime_options'] = [];
function get_option($name, $default = false) { return $GLOBALS['runtime_options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null) { $GLOBALS['runtime_options'][$name] = $value; return true; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function __($value, $domain = null) { return $value; }
function human_time_diff($from, $to) { return (string) max(0, $to - $from); }
function apply_filters($tag, $value) { return $value; }
function plugin_dir_path($file) { return rtrim(dirname($file), '/') . '/'; }
function plugin_dir_url($file) { return 'https://example.test/'; }

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct($code, $message, $data = null) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

$plugin_source = file_get_contents(__DIR__ . '/../bitmomo-ai.php');
$boundary = strpos($plugin_source, 'require_once BITMOMO_AI_DIR');
eval('?>' . substr($plugin_source, 0, $boundary));
require __DIR__ . '/../includes/class-bitmomo-ai-runtime-state.php';
require __DIR__ . '/../includes/class-bitmomo-ai-signal-engine.php';
require __DIR__ . '/../includes/class-bitmomo-ai-key-drivers.php';

$checks = [];
function runtime_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }
function runtime_record($age_hours, $id = 'bitmomo-ai:test:valid') {
    $data = [
        'close' => 65000,
        'timestamp' => gmdate('c', time() - ($age_hours * HOUR_IN_SECONDS)),
        'direction' => ['adx' => 30, 'plus_di' => 30, 'minus_di' => 10],
        'carry' => ['funding_rate' => 0, 'basis_pct' => 0],
        'structure' => ['state' => 'range'],
        'crowding' => ['oi_change_24h_pct' => 0, 'price_change_24h_pct' => 0],
        'volatility' => ['regime' => 'normal', 'atr_pct_1h' => 1],
        'quality' => ['status' => 'complete', 'completeness_pct' => 100],
    ];
    return [
        'data' => $data,
        'evaluation' => Bitmomo_AI_Signal_Engine::evaluate($data),
        'time' => gmdate('c', time() - ($age_hours * HOUR_IN_SECONDS)),
        'generated_at' => gmdate('c', time() - ($age_hours * HOUR_IN_SECONDS)),
        'edition' => 'morning',
        'source_record_id' => $id,
        'provenance' => 'recorded_live',
    ];
}

$passed_gate = ['status' => 'passed', 'checked_at' => gmdate('c'), 'failed_keys' => [], 'critical_failures' => [], 'hard_blocked' => false];
$blocked_gate = ['status' => 'blocked', 'checked_at' => gmdate('c'), 'failed_keys' => ['completeness'], 'critical_failures' => ['completeness'], 'hard_blocked' => true];

$valid = runtime_record(1);
Bitmomo_AI_Runtime_State::record_valid_snapshot($valid, $passed_gate);
Bitmomo_AI_Runtime_State::record_attempt('morning', 'success', $valid['data'], $passed_gate);
runtime_check('successful current generation selects the canonical snapshot', Bitmomo_AI_Intelligence::free_projection()['status'] === 'fresh');

$original_time = $valid['generated_at'];
$diagnostics = [
    ['requested_input' => 'candles_1h', 'provider' => 'binance_usdm', 'success' => true, 'observed_at' => gmdate('c'), 'age_minutes' => 1, 'freshness' => 'fresh', 'fallback_attempted' => false, 'fallback_success' => false, 'error_category' => '', 'provider_status' => null],
    ['requested_input' => 'funding', 'provider' => 'binance_usdm', 'success' => false, 'observed_at' => '', 'age_minutes' => null, 'freshness' => 'unavailable', 'fallback_attempted' => true, 'fallback_success' => false, 'error_category' => 'http', 'provider_status' => 451],
];
$blocked_data = ['quality' => ['completeness_pct' => 60], 'source_diagnostics' => $diagnostics];
$blocked_error = new WP_Error('bitmomo_quality_gate_failed', 'completeness blocked');
Bitmomo_AI_Runtime_State::record_attempt('morning', 'blocked', $blocked_data, $blocked_gate, $blocked_error);
runtime_check('blocked attempt preserves the previous valid snapshot', Bitmomo_AI_Runtime_State::latest_valid_snapshot()['source_record_id'] === 'bitmomo-ai:test:valid');
runtime_check('blocked attempt with snapshot at or below six hours stays fresh', Bitmomo_AI_Intelligence::free_projection()['status'] === 'fresh');
runtime_check('blocked attempt never becomes canonical', Bitmomo_AI_Runtime_State::latest_valid_snapshot()['data']['close'] === 65000);
runtime_check('original valid-snapshot timestamp remains unchanged', Bitmomo_AI_Runtime_State::latest_valid_snapshot()['generated_at'] === $original_time);
runtime_check('missing mandatory source diagnostics are retained', Bitmomo_AI_Runtime_State::latest_attempt()['missing_inputs'] === ['funding']);
runtime_check('source failure reason and safe provider status are retained', Bitmomo_AI_Runtime_State::latest_attempt()['source_diagnostics'][1]['error_category'] === 'http' && Bitmomo_AI_Runtime_State::latest_attempt()['source_diagnostics'][1]['provider_status'] === 451);
runtime_check('no fabricated fallback is recorded', Bitmomo_AI_Runtime_State::latest_attempt()['source_diagnostics'][1]['fallback_success'] === false);

Bitmomo_AI_Runtime_State::record_valid_snapshot(runtime_record(7), $passed_gate);
runtime_check('blocked attempt with snapshot over six and at most thirty hours is delayed', Bitmomo_AI_Intelligence::free_projection()['status'] === 'delayed');
runtime_check('projection API exposes delayed freshness state', Bitmomo_AI_Runtime_State::latest_valid_metadata()['freshness_state'] === 'delayed');

Bitmomo_AI_Runtime_State::record_valid_snapshot(runtime_record(31), $passed_gate);
runtime_check('snapshot older than thirty hours is unavailable', Bitmomo_AI_Intelligence::free_projection()['status'] === 'unavailable');

unset($GLOBALS['runtime_options'][Bitmomo_AI_Runtime_State::VALID_OPTION], $GLOBALS['runtime_options']['bitmomo_ai_latest_preview']);
runtime_check('no valid canonical snapshot is unavailable', Bitmomo_AI_Intelligence::free_projection()['status'] === 'unavailable');

$GLOBALS['runtime_options']['bitmomo_regime_history'] = ['independent'];
Bitmomo_AI_Runtime_State::record_attempt('morning', 'blocked', $blocked_data, $blocked_gate, $blocked_error);
runtime_check('Regime history remains semantically separate', $GLOBALS['runtime_options']['bitmomo_regime_history'] === ['independent'] && Bitmomo_AI_Intelligence::free_projection()['status'] === 'unavailable');

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " runtime-state checks passed.\n";

