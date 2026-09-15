<?php
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('BITMOMO_AI_VERSION', 'test');
define('BITMOMO_AI_FILE', __FILE__);

$GLOBALS['health_options'] = [];
$GLOBALS['health_schedule'] = [];
function get_option($name, $default = false) { return $GLOBALS['health_options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null) { $GLOBALS['health_options'][$name] = $value; return true; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function absint($value) { return abs((int) $value); }
function __($value, $domain = null) { return $value; }
function wp_next_scheduled($hook) { return $GLOBALS['health_schedule'][$hook] ?? 0; }
function wp_schedule_single_event($timestamp, $hook) { $GLOBALS['health_schedule'][$hook] = $timestamp; return true; }
function wp_schedule_event($timestamp, $recurrence, $hook) { $GLOBALS['health_schedule'][$hook] = $timestamp; return true; }
function wp_clear_scheduled_hook($hook) { unset($GLOBALS['health_schedule'][$hook]); return true; }
function wp_json_encode($value) { return json_encode($value); }
function do_action($hook, ...$args) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
class WP_Error {
    private $code;
    private $message;
    public function __construct($code = '', $message = '') { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return null; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }

final class Bitmomo_AI_Intelligence {
    const FRESH_AGE_SECONDS = 6 * HOUR_IN_SECONDS;
    const DELAYED_AGE_SECONDS = 30 * HOUR_IN_SECONDS;
}

require __DIR__ . '/../includes/class-bitmomo-ai-session-intelligence.php';
require __DIR__ . '/../includes/class-bitmomo-ai-opportunity.php';
require __DIR__ . '/../includes/class-bitmomo-ai-runtime-state.php';
require __DIR__ . '/../includes/class-bitmomo-ai-scheduler.php';

$checks = [];
function health_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

$now = strtotime('2026-09-15T21:00:00-04:00');
$GLOBALS['health_schedule'][Bitmomo_AI_Opportunity::HOOK] = $now + 60;
$GLOBALS['health_schedule'][Bitmomo_AI_Scheduler::MORNING_HOOK] = $now + HOUR_IN_SECONDS;
$GLOBALS['health_schedule'][Bitmomo_AI_Scheduler::HOOK] = $now + (12 * HOUR_IN_SECONDS);
$GLOBALS['health_schedule'][Bitmomo_AI_Scheduler::SETTLEMENT_HOOK] = $now + (6 * HOUR_IN_SECONDS);

$GLOBALS['health_options'][Bitmomo_AI_Opportunity::LAST_RUN_OPTION] = [
    'status' => 'success',
    'time' => gmdate('c', $now - (5 * MINUTE_IN_SECONDS)),
];
$GLOBALS['health_options'][Bitmomo_AI_Opportunity_Store::LATEST_OPTION] = [
    'methodology_version' => Bitmomo_AI_Opportunity::METHODOLOGY_VERSION,
    'evaluated_at' => gmdate('c', $now - (5 * MINUTE_IN_SECONDS)),
    'knowledge_time' => gmdate('c', $now - (15 * MINUTE_IN_SECONDS)),
    'state' => 'HIGH',
    'range_60m_pct' => 1.2,
    'activity_percentile' => 88.8,
    'reference_observation_count' => Bitmomo_AI_Opportunity::REFERENCE_OBSERVATIONS,
    'source' => Bitmomo_AI_Opportunity::SOURCE,
    'source_last_close_time' => gmdate('c', $now - (15 * MINUTE_IN_SECONDS)),
];
$GLOBALS['health_options'][Bitmomo_AI_Scheduler::SESSION_RUNS_OPTION] = [
    Bitmomo_AI_Session_Intelligence::PRE_OPEN => ['status' => 'success', 'time' => '2026-09-15T12:12:00+00:00'],
    Bitmomo_AI_Session_Intelligence::POST_CLOSE => ['status' => 'success', 'time' => '2026-09-16T00:12:00+00:00'],
];
$GLOBALS['health_options']['bitmomo_ai_last_settlement'] = [
    'status' => 'success',
    'settled' => 2,
    'time' => gmdate('c', $now - (3 * HOUR_IN_SECONDS)),
];
$GLOBALS['health_options'][Bitmomo_AI_Runtime_State::ATTEMPT_OPTION] = [
    'attempted_at' => gmdate('c', $now - (10 * MINUTE_IN_SECONDS)),
    'status' => 'blocked',
    'source_diagnostics' => [
        ['requested_input' => 'candles_1h', 'success' => true, 'freshness' => 'fresh'],
        ['requested_input' => 'funding', 'success' => false, 'freshness' => 'unavailable'],
    ],
];

$matrix = Bitmomo_AI_Scheduler::automation_health_matrix($now);
health_check('matrix exposes all institutional clocks and source freshness', array_keys($matrix) === ['market_pulse', 'us_pre_open', 'us_post_close', 'settlement', 'source_freshness']);
health_check('Market Pulse health is independent and fresh', $matrix['market_pulse']['state'] === 'healthy' && $matrix['market_pulse']['freshness_budget_seconds'] === 30 * MINUTE_IN_SECONDS);
health_check('US Pre-Open health uses its own session run', $matrix['us_pre_open']['state'] === 'healthy' && $matrix['us_pre_open']['last_status'] === 'success');
health_check('US Post-Close health uses its own session run', $matrix['us_post_close']['state'] === 'healthy' && $matrix['us_post_close']['last_status'] === 'success');
health_check('settlement health is tracked separately', $matrix['settlement']['state'] === 'healthy' && $matrix['settlement']['freshness_budget_seconds'] === 30 * HOUR_IN_SECONDS);
health_check('source freshness degrades without overriding clock health', $matrix['source_freshness']['state'] === 'degraded' && $matrix['market_pulse']['state'] === 'healthy');

$GLOBALS['health_options']['bitmomo_ai_last_run'] = ['status' => 'success', 'time' => '2026-09-16T00:12:00+00:00'];
unset($GLOBALS['health_options'][Bitmomo_AI_Scheduler::SESSION_RUNS_OPTION][Bitmomo_AI_Session_Intelligence::POST_CLOSE]);
$legacy_post_close = Bitmomo_AI_Scheduler::automation_health_matrix($now);
health_check('Post-Close health remains backward-compatible with legacy last_run', $legacy_post_close['us_post_close']['state'] === 'healthy');

unset($GLOBALS['health_schedule'][Bitmomo_AI_Opportunity::HOOK]);
$without_pulse_schedule = Bitmomo_AI_Scheduler::automation_health_matrix($now);
health_check('missing Market Pulse schedule fails that component closed', $without_pulse_schedule['market_pulse']['state'] === 'not_scheduled');

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " observability health-matrix checks passed.\n";
