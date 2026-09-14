<?php
/** End-to-end free_projection() + key_drivers contract. */

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
    function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $key)); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) { return trim((string) $text); }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('human_time_diff')) {
    function human_time_diff($from, $to) { return round(max(0, $to - $from) / 3600, 1) . ' jam'; }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) { return $value; }
}
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) { return rtrim(dirname($file), '/') . '/'; }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/bitmomo-ai/'; }
}

$plugin_source = file_get_contents(__DIR__ . '/../bitmomo-ai.php');
$boundary = strpos($plugin_source, "require_once BITMOMO_AI_DIR");
if (false === $boundary) {
    fwrite(STDERR, "Could not locate the bootstrap require_once boundary in bitmomo-ai.php\n");
    exit(1);
}
eval('?>' . substr($plugin_source, 0, $boundary));
if (!class_exists('Bitmomo_AI_Intelligence')) {
    fwrite(STDERR, "Bitmomo_AI_Intelligence did not load from shipped source.\n");
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

// Fresh valid output retains the backward-compatible primary driver and adds
// a bounded public key_drivers array.
set_preview(fp_signal_input());
$result = Bitmomo_AI_Intelligence::free_projection();
check_fp('fresh status preserved', 'fresh' === ($result['status'] ?? null));
check_fp('primary_driver remains present', is_string($result['primary_driver'] ?? null) && '' !== $result['primary_driver']);
check_fp('key_drivers present and bounded', isset($result['key_drivers']) && is_array($result['key_drivers']) && count($result['key_drivers']) >= 1 && count($result['key_drivers']) <= 6);
check_fp('every key driver is non-empty text', array_reduce($result['key_drivers'], function ($carry, $line) { return $carry && is_string($line) && '' !== trim($line); }, true));

$pro_only_keys = array('axes', 'score', 'risk', 'support_zone', 'resistance_zone', 'invalidation', 'edition', 'source_record_id', 'comparison_source_record_id');
check_fp('no Pro/internal fields leak into free projection', empty(array_intersect($pro_only_keys, array_keys($result))));

// Stale public intelligence fails closed.
set_preview(fp_signal_input(), 'passed', gmdate('c', time() - (31 * HOUR_IN_SECONDS)));
$stale_result = Bitmomo_AI_Intelligence::free_projection();
check_fp('stale data becomes unavailable', 'unavailable' === ($stale_result['status'] ?? null));
check_fp('stale data exposes no key_drivers', !array_key_exists('key_drivers', $stale_result));
check_fp('stale data exposes no primary_driver', !array_key_exists('primary_driver', $stale_result));

set_preview(fp_signal_input(), 'passed', gmdate('c'));
$GLOBALS['__fake_options']['bitmomo_ai_latest_preview']['generated_at'] = gmdate('c', time() - (31 * HOUR_IN_SECONDS));
$canonical_stale_result = Bitmomo_AI_Intelligence::free_projection();
check_fp('canonical generated_at wins freshness precedence', 'unavailable' === ($canonical_stale_result['status'] ?? null));

// A later blocked attempt cannot erase the last accepted public snapshot.
set_preview(fp_signal_input(), 'blocked');
$blocked_result = Bitmomo_AI_Intelligence::free_projection();
check_fp('blocked later gate keeps previous accepted snapshot', 'fresh' === ($blocked_result['status'] ?? null) && isset($blocked_result['key_drivers']));

set_preview(fp_signal_input(), 'degraded');
$degraded_result = Bitmomo_AI_Intelligence::free_projection();
check_fp('degraded gate can still publish accepted intelligence', 'unavailable' !== ($degraded_result['status'] ?? null) && isset($degraded_result['key_drivers']));

// Conflicting evidence remains visible rather than being collapsed.
set_preview(fp_signal_input(array(
    'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'bullish', 'bias_4h' => 'bullish', 'bias_1d' => 'bullish'),
    'structure' => array('state' => 'lh_ll', 'state_1d' => 'lh_ll'),
)));
$conflict_result = Bitmomo_AI_Intelligence::free_projection();
$has_momentum = (bool) array_filter($conflict_result['key_drivers'], function ($line) { return false !== strpos($line, 'Momentum harga'); });
$has_structure = (bool) array_filter($conflict_result['key_drivers'], function ($line) { return false !== strpos($line, 'Struktur harga'); });
check_fp('conflicting direction and structure both survive free projection', $has_momentum && $has_structure);

// Institutional wording remains descriptive/current-state only.
set_preview(fp_signal_input(array(
    'carry' => array('funding_rate' => 0.0008, 'basis_pct' => 0.30),
    'crowding' => array('oi_change_24h_pct' => 3, 'price_change_24h_pct' => 1, 'global_long_short_ratio' => 0.7, 'taker_buy_sell_ratio' => 1.2),
)));
$language_result = Bitmomo_AI_Intelligence::free_projection();
$banned_terms = array('assessment', 'positioning derivatif', 'crowded positioning', 'crowding', 'carry', 'composite', 'terkonsentrasi', 'cukup kuat ke arah', 'menembus level penting');
$forward_terms = array('jika', 'apabila', 'berpotensi', 'potensial', 'membuka ruang', 'ke depan', 'trigger', 'skenario', 'invalidasi');
$banned_hit = false;
$forward_hit = false;
foreach ($language_result['key_drivers'] as $line) {
    foreach ($banned_terms as $term) if (false !== stripos($line, $term)) $banned_hit = true;
    foreach ($forward_terms as $term) if (false !== stripos($line, $term)) $forward_hit = true;
}
check_fp('public factors contain no internal/casual legacy wording', !$banned_hit);
check_fp('public factors contain no scenario/forward-looking leakage', !$forward_hit);

// Carry must describe futures pricing, not trader headcount.
set_preview(fp_signal_input(array(
    'carry' => array('funding_rate' => 0.0008, 'basis_pct' => 0.30),
    'crowding' => array('oi_change_24h_pct' => 0.5, 'price_change_24h_pct' => 0.1, 'global_long_short_ratio' => 1.3, 'taker_buy_sell_ratio' => 1.0),
)));
$accuracy_result = Bitmomo_AI_Intelligence::free_projection();
$overclaim_terms = array('sangat dominan', 'banyak trader', 'mengambil posisi', 'cukup padat', 'banyak pelaku pasar', 'crowded', 'concentrat');
$overclaim_hit = false;
foreach ($accuracy_result['key_drivers'] as $line) {
    foreach ($overclaim_terms as $term) if (false !== stripos($line, $term)) $overclaim_hit = true;
}
check_fp('no carry/crowding headcount overclaims', !$overclaim_hit);
check_fp('carry uses premium/discount/funding market language', 0 < count(array_filter($accuracy_result['key_drivers'], function ($line) {
    return false !== stripos($line, 'premi terhadap') || false !== stripos($line, 'diskon terhadap') || false !== stripos($line, 'biaya funding');
})));
check_fp('account-ratio wording explicitly refers to account proportion', 0 < count(array_filter($accuracy_result['key_drivers'], function ($line) {
    return false !== stripos($line, 'proporsi akun');
})));

// An unavailable carry feed is suppressed even when stale-looking values are material.
set_preview(fp_signal_input(array(
    'carry' => array('funding_rate' => 0.0009, 'basis_pct' => 0.35, 'data_status' => 'unavailable'),
)));
$carry_unavailable = Bitmomo_AI_Intelligence::free_projection();
$carry_line = (bool) array_filter($carry_unavailable['key_drivers'], function ($line) {
    return false !== stripos($line, 'funding') || false !== stripos($line, 'premi terhadap') || false !== stripos($line, 'diskon terhadap');
});
check_fp('unavailable carry feed is fail-closed end-to-end', !$carry_line);

$pass = 0;
foreach ($checks as $check) {
    list($label, $ok) = $check;
    echo '[' . ($ok ? 'PASS' : 'FAIL') . '] ' . $label . "\n";
    if ($ok) $pass++;
}

echo $pass . '/' . count($checks) . " free_projection/key_drivers checks passed\n";
exit($pass === count($checks) ? 0 : 1);
