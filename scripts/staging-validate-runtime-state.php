<?php
/** Run with `wp eval-file` on Bitmomo staging only. */

if (!defined('WP_CLI') || !WP_CLI || get_option('siteurl') !== 'https://seagreen-snail-158456.hostingersite.com') {
    fwrite(STDERR, "Refusing non-staging runtime-state validation.\n");
    exit(2);
}
if (!defined('BITMOMO_STAGING_SIDE_EFFECTS_DISABLED') || !BITMOMO_STAGING_SIDE_EFFECTS_DISABLED) {
    fwrite(STDERR, "Refusing validation without the staging side-effect guard.\n");
    exit(2);
}

$option_names = [
    Bitmomo_AI_Runtime_State::ATTEMPT_OPTION,
    Bitmomo_AI_Runtime_State::VALID_OPTION,
    'bitmomo_ai_latest_preview',
    'bitmomo_ai_latest_quality_gate',
];
$original = [];
foreach ($option_names as $name) {
    $original[$name] = ['exists' => false, 'value' => null];
    $value = get_option($name, '__bitmomo_option_missing__');
    if ($value !== '__bitmomo_option_missing__') $original[$name] = ['exists' => true, 'value' => $value];
}

$checks = [];
$check = static function ($name, $passed) use (&$checks) {
    $checks[$name] = (bool) $passed;
    echo $name . '=' . ($passed ? 'PASS' : 'FAIL') . "\n";
};
$record = static function ($age_hours) {
    $generated_at = gmdate('c', time() - ($age_hours * HOUR_IN_SECONDS));
    return [
        'data' => [
            'close' => 65000,
            'timestamp' => $generated_at,
            'quality' => ['status' => 'complete', 'completeness_pct' => 100],
        ],
        'evaluation' => [
            'bias' => 'neutral',
            'score' => 0,
            'confidence' => 60,
            'direction_strength' => 'neutral',
            'quality' => ['status' => 'complete', 'completeness_pct' => 100],
            'axes' => [],
        ],
        'time' => $generated_at,
        'generated_at' => $generated_at,
        'edition' => 'morning',
        'source_record_id' => 'bitmomo-ai:staging-validation:morning',
        'comparison_source_record_id' => '',
        'quality' => ['status' => 'complete'],
        'provenance' => 'recorded_live',
    ];
};
$passed_gate = ['status' => 'passed', 'checked_at' => gmdate('c'), 'failed_keys' => [], 'critical_failures' => [], 'hard_blocked' => false];
$blocked_gate = ['status' => 'blocked', 'checked_at' => gmdate('c'), 'failed_keys' => ['completeness'], 'critical_failures' => ['completeness'], 'hard_blocked' => true];

try {
    $healthy = $record(1);
    Bitmomo_AI_Runtime_State::record_valid_snapshot($healthy, $passed_gate);
    Bitmomo_AI_Runtime_State::record_attempt('morning', 'success', $healthy['data'], $passed_gate);
    $projection = Bitmomo_AI_Intelligence::free_projection();
    $check('healthy_snapshot_selected', ($projection['status'] ?? '') === 'fresh');

    $timestamp = $projection['timestamp_iso'] ?? '';
    Bitmomo_AI_Runtime_State::record_attempt('morning', 'blocked', ['quality' => ['completeness_pct' => 60]], $blocked_gate, new WP_Error('bitmomo_quality_gate_failed', 'controlled staging fixture'));
    $projection = Bitmomo_AI_Intelligence::free_projection();
    $check('blocked_attempt_recorded', (Bitmomo_AI_Runtime_State::latest_attempt()['status'] ?? '') === 'blocked');
    $check('blocked_attempt_preserves_valid_snapshot', ($projection['status'] ?? '') === 'fresh');
    $check('blocked_attempt_preserves_timestamp', ($projection['timestamp_iso'] ?? '') === $timestamp);

    Bitmomo_AI_Runtime_State::record_valid_snapshot($record(7), $passed_gate);
    $check('delayed_snapshot_selected', (Bitmomo_AI_Intelligence::free_projection()['status'] ?? '') === 'delayed');

    Bitmomo_AI_Runtime_State::record_valid_snapshot($record(31), $passed_gate);
    $check('expired_snapshot_unavailable', (Bitmomo_AI_Intelligence::free_projection()['status'] ?? '') === 'unavailable');
} finally {
    foreach ($original as $name => $state) {
        if ($state['exists']) update_option($name, $state['value'], false);
        else delete_option($name);
    }
}

if (in_array(false, $checks, true)) exit(1);
echo 'runtime_state_restored=PASS' . "\n";

