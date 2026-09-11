<?php
/**
 * Bitmomo_AI_Intelligence::free_projection() + key_drivers wiring contract
 * (KEY DRIVERS CUSTOMER-FACING COPY build).
 *
 * Proves the additive data-contract change end-to-end: key_drivers is
 * present alongside the untouched primary_driver, the fail-closed/quality-
 * valid-snapshot boundary is preserved, and no Pro-only field leaks into the Free
 * shape — using the REAL Bitmomo_AI_Intelligence class body (sliced
 * verbatim out of the real bitmomo-ai.php at test time, not hand-copied,
 * so this test can never silently drift from the shipped file) plus the
 * real Bitmomo_AI_Key_Drivers and Bitmomo_AI_Signal_Engine classes.
 *
 * WordPress itself is stubbed (only the handful of functions the sliced
 * class body actually calls: get_option/sanitize_key/sanitize_text_field/
 * __/human_time_diff/apply_filters), same convention as
 * tests/test-bitmomo-watchtower-alert-outbox-contract.php.
 *
 * Run: php tests/test-bitmomo-ai-free-projection-key-drivers-contract.php
 */

define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['__fake_options'] = array();

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return array_key_exists($name, $GLOBALS['__fake_options']) ? $GLOBALS['__fake_options'][$name] : $default;
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $key));
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return trim((string) $text);
    }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}
if (!function_exists('human_time_diff')) {
    function human_time_diff($from, $to) {
        return round(max(0, $to - $from) / 3600, 1) . ' jam';
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return rtrim(dirname($file), '/') . '/';
    }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'https://example.test/wp-content/plugins/bitmomo-ai/';
    }
}

// --- Slice the real Bitmomo_AI_Intelligence class body verbatim out of the
// --- real, shipped bitmomo-ai.php (everything before its bootstrap
// --- require_once tail), so this test exercises the actual file that ships
// --- rather than a hand-maintained copy that could drift from it.
$plugin_source = file_get_contents(__DIR__ . '/../bitmomo-ai.php');
$boundary = strpos($plugin_source, "require_once BITMOMO_AI_DIR");
if (false === $boundary) {
    fwrite(STDERR, "Could not locate the bootstrap require_once boundary in bitmomo-ai.php\n");
    exit(1);
}
$class_only_source = substr($plugin_source, 0, $boundary);
// The sliced source still opens with "if (!defined('ABSPATH')) exit;" and
// references HOUR_IN_SECONDS/MINUTE_IN_SECONDS — both satisfied above.
eval('?>' . $class_only_source);

if (!class_exists('Bitmomo_AI_Intelligence')) {
    fwrite(STDERR, "Bitmomo_AI_Intelligence did not load from the sliced source.\n");
    exit(1);
}

require __DIR__ . '/../includes/class-bitmomo-ai-signal-engine.php';
require __DIR__ . '/../includes/class-bitmomo-ai-key-drivers.php';
require __DIR__ . '/../includes/class-bitmomo-ai-session-intelligence.php';

$checks = array();
function check_fp($label, $condition) {
    global $checks;
    $checks[] = array($label, (bool) $condition);
}

function fp_signal_input($overrides = array()) {
    $defaults = array(
        'close' => 65000,
        'timestamp' => gmdate('c'),
        'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'bullish', 'bias_4h' => 'bullish', 'bias_1d' => 'bullish'),
        'carry' => array('funding_rate' => 0.0001, 'basis_pct' => 0.02),
        'structure' => array('state' => 'range', 'state_1d' => 'range', 'last_swing_low' => 63000, 'last_swing_high' => 67000, 'near_support' => 64000, 'near_resistance' => 66000),
        'crowding' => array('oi_change_24h_pct' => 0.5, 'price_change_24h_pct' => 0.1, 'global_long_short_ratio' => 1.0, 'taker_buy_sell_ratio' => 1.0),
        'volatility' => array('atr_percentile_1d' => 50, 'regime' => 'normal', 'atr_pct_4h' => 1.0, 'atr_pct_1h' => 0.5, 'bb_width_pct_4h' => 2.0),
        'quality' => array('status' => 'complete'),
    );
    foreach ($overrides as $key => $value) {
        $defaults[$key] = is_array($value) ? array_merge($defaults[$key], $value) : $value;
    }
    return $defaults;
}

function set_preview($data, $gate_status = 'passed', $time = null) {
    $evaluation = Bitmomo_AI_Signal_Engine::evaluate($data);
    $GLOBALS['__fake_options']['bitmomo_ai_latest_preview'] = array(
        'time' => $time ?: gmdate('c'),
        'data' => $data,
        'evaluation' => $evaluation,
        'edition' => 'us_session',
        'source_record_id' => 'bitmomo-ai:test:1',
        'comparison_source_record_id' => '',
    );
    $GLOBALS['__fake_options']['bitmomo_ai_latest_quality_gate'] = array('status' => $gate_status);
    return $evaluation;
}

// --- Case 1: fresh, valid, multi-material data -> key_drivers present
// --- alongside untouched primary_driver, both non-empty.
set_preview(fp_signal_input());
$result = Bitmomo_AI_Intelligence::free_projection();
check_fp('valid fresh data: status is fresh (existing contract unchanged)', 'fresh' === ($result['status'] ?? null));
check_fp('valid fresh data: primary_driver is still a non-empty string (untouched, backward compatible)', is_string($result['primary_driver'] ?? null) && '' !== $result['primary_driver']);
check_fp('valid fresh data: key_drivers is present and is an array', isset($result['key_drivers']) && is_array($result['key_drivers']));
check_fp('valid fresh data: key_drivers has between 1 and 6 entries', count($result['key_drivers']) >= 1 && count($result['key_drivers']) <= 6);
check_fp('valid fresh data: every key_drivers entry is a non-empty string', array_reduce($result['key_drivers'], function ($carry, $line) { return $carry && is_string($line) && '' !== $line; }, true));

// --- Case 2: no Pro-only / internal fields leak into the Free shape.
$pro_only_keys = array('axes', 'score', 'risk', 'support_zone', 'resistance_zone', 'invalidation', 'edition', 'source_record_id', 'comparison_source_record_id');
$leaked_keys = array_intersect($pro_only_keys, array_keys($result));
check_fp('no Pro-only fields present in free_projection() output', empty($leaked_keys));

// --- Case 3: stale (beyond DELAYED_AGE_SECONDS) data fails closed — same
// --- as the pre-existing behavior for primary_driver, key_drivers must
// --- also be absent rather than showing stale conclusions as current.
set_preview(fp_signal_input(), 'passed', gmdate('c', time() - (31 * HOUR_IN_SECONDS)));
$stale_result = Bitmomo_AI_Intelligence::free_projection();
check_fp('stale data: status is unavailable', 'unavailable' === ($stale_result['status'] ?? null));
check_fp('stale data: key_drivers is absent (fail-closed, matches primary_driver)', !array_key_exists('key_drivers', $stale_result));
check_fp('stale data: primary_driver is absent (existing fail-closed behavior unchanged)', !array_key_exists('primary_driver', $stale_result));

// --- Case 4: a later blocked gate cannot invalidate a previously accepted
// --- canonical preview. The blocked attempt is tracked independently.
set_preview(fp_signal_input(), 'blocked');
$blocked_result = Bitmomo_AI_Intelligence::free_projection();
check_fp('later blocked gate: previous valid snapshot remains fresh', 'fresh' === ($blocked_result['status'] ?? null));
check_fp('later blocked gate: previous valid key_drivers remain present', isset($blocked_result['key_drivers']) && is_array($blocked_result['key_drivers']));

// --- Case 5: degraded quality gate still passes through (existing
// --- behavior) and still carries key_drivers.
set_preview(fp_signal_input(), 'degraded');
$degraded_result = Bitmomo_AI_Intelligence::free_projection();
check_fp('degraded quality gate: still resolves (not unavailable)', 'unavailable' !== ($degraded_result['status'] ?? null));
check_fp('degraded quality gate: key_drivers still present', isset($degraded_result['key_drivers']) && is_array($degraded_result['key_drivers']));

// --- Case 6: conflicting evidence surfaced end-to-end through the real
// --- free_projection() call, not just the pure Key_Drivers unit test.
set_preview(fp_signal_input(array(
    'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'bullish', 'bias_4h' => 'bullish', 'bias_1d' => 'bullish'),
    'structure' => array('state' => 'lh_ll', 'state_1d' => 'lh_ll'),
)));
$conflict_result = Bitmomo_AI_Intelligence::free_projection();
$has_momentum = (bool) array_filter($conflict_result['key_drivers'], function ($l) { return false !== strpos($l, 'Momentum harga BTC'); });
$has_structure = (bool) array_filter($conflict_result['key_drivers'], function ($l) { return false !== strpos($l, 'Struktur harga'); });
check_fp('end-to-end: conflicting direction/structure both surfaced through free_projection()', $has_momentum && $has_structure);

// --- Case 7: end-to-end language hardening — carry/crowding-heavy market,
// --- checked through the real free_projection() call (not just the pure
// --- Key_Drivers unit test) for banned terminology and forward-looking /
// --- conditional-consequence language.
set_preview(fp_signal_input(array(
    'carry' => array('funding_rate' => 0.0008, 'basis_pct' => 0.30),
    'crowding' => array('oi_change_24h_pct' => 3, 'price_change_24h_pct' => 1, 'global_long_short_ratio' => 0.7, 'taker_buy_sell_ratio' => 1.2),
)));
$language_result = Bitmomo_AI_Intelligence::free_projection();
$banned_terms = array('pembacaan', 'assessment', 'positioning derivatif', 'crowded positioning', 'crowding', 'carry', 'composite', 'terkonsentrasi', 'kondisi netral');
$forward_looking_markers = array('jika', 'apabila', 'berpotensi', 'potensial', 'membuka ruang', 'nantinya', 'ke depan', 'dilepas', 'unwind', 'trigger', 'skenario', 'invalidasi');
$banned_hit = false;
$forward_looking_hit = false;
foreach ($language_result['key_drivers'] as $line) {
    foreach ($banned_terms as $term) {
        if (false !== stripos($line, $term)) $banned_hit = true;
    }
    foreach ($forward_looking_markers as $marker) {
        if (false !== stripos($line, $marker)) $forward_looking_hit = true;
    }
}
check_fp('end-to-end: no banned internal-analyst terminology in free_projection() key_drivers', !$banned_hit);
check_fp('end-to-end: no forward-looking / conditional-consequence language in free_projection() key_drivers', !$forward_looking_hit);

// --- Case 8: end-to-end semantic-accuracy — the specific overclaims
// --- review flagged (a literal trader head-count / "dominant side" claim
// --- from funding+basis alone, and a "crowded/many traders" conclusion
// --- from the crowding composite) must never reappear through the real
// --- free_projection() call, and the futures-pricing / account-ratio
// --- wording must come through correctly end-to-end.
set_preview(fp_signal_input(array(
    'carry' => array('funding_rate' => 0.0008, 'basis_pct' => 0.30),
    'crowding' => array('oi_change_24h_pct' => 0.5, 'price_change_24h_pct' => 0.1, 'global_long_short_ratio' => 1.3, 'taker_buy_sell_ratio' => 1.0),
)));
$accuracy_result = Bitmomo_AI_Intelligence::free_projection();
$overclaim_terms = array('sangat dominan', 'banyak trader', 'mengambil posisi', 'cukup padat', 'banyak pelaku pasar', 'crowded', 'concentrat');
$overclaim_hit = false;
foreach ($accuracy_result['key_drivers'] as $line) {
    foreach ($overclaim_terms as $term) {
        if (false !== stripos($line, $term)) $overclaim_hit = true;
    }
}
check_fp('end-to-end: no removed carry/crowding overclaims (headcount / "dominant side" / "crowded") reappear through free_projection()', !$overclaim_hit);
check_fp(
    'end-to-end: carry copy describes futures-market pricing ("diperdagangkan ... harga spot" / "biaya mempertahankan posisi")',
    0 < count(array_filter($accuracy_result['key_drivers'], function ($l) { return false !== stripos($l, 'diperdagangkan') || false !== stripos($l, 'biaya mempertahankan posisi'); }))
);
check_fp(
    'end-to-end: crowding account-ratio wording explicitly says "akun" (account proportion), not a position-size claim',
    0 < count(array_filter($accuracy_result['key_drivers'], function ($l) { return false !== stripos($l, 'proporsi akun trader'); }))
);

// --- Case 9: fail-closed carry data-availability guard, end-to-end. Real
// --- Signal_Engine::carry_score() never reads data_status, so a feed that
// --- reports data_status='unavailable' while still carrying elevated
// --- funding/basis numbers (a malformed or stale payload) will produce a
// --- materially-scored carry axis purely from those numbers -- exactly
// --- the case Bitmomo_AI_Key_Drivers' explicit fail-closed guard exists
// --- to catch. Other axes (direction here) must remain unaffected.
set_preview(fp_signal_input(array(
    'carry' => array('funding_rate' => 0.0009, 'basis_pct' => 0.35, 'data_status' => 'unavailable'),
)));
$carry_unavailable_result = Bitmomo_AI_Intelligence::free_projection();
$has_carry_like_line = (bool) array_filter($carry_unavailable_result['key_drivers'], function ($l) {
    return false !== stripos($l, 'futures') || false !== stripos($l, 'spot') || false !== stripos($l, 'biaya');
});
$has_direction_line = (bool) array_filter($carry_unavailable_result['key_drivers'], function ($l) { return false !== strpos($l, 'Momentum harga BTC'); });
check_fp('end-to-end: data_status=unavailable carry (with elevated funding/basis) never surfaces a carry Key Driver through free_projection()', !$has_carry_like_line);
check_fp('end-to-end: other material drivers (direction) still surface normally when carry is suppressed for unavailability', $has_direction_line);

$passed = count(array_filter($checks, function ($row) { return $row[1]; }));
foreach ($checks as $row) {
    printf("[%s] %s\n", $row[1] ? 'PASS' : 'FAIL', $row[0]);
}
printf("\n%d/%d passed.\n", $passed, count($checks));
exit($passed === count($checks) ? 0 : 1);
