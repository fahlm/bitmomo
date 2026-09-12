<?php
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['adapter_options'] = [];
function get_option($name, $default = false) { return $GLOBALS['adapter_options'][$name] ?? $default; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function __($value, $domain = null) { return $value; }
function human_time_diff($from, $to) { return '1 jam'; }
function apply_filters($tag, $value) { return $value; }
function plugin_dir_path($file) { return rtrim(dirname($file), '/') . '/'; }
function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/bitmomo-ai/'; }

require __DIR__ . '/../includes/class-bitmomo-ai-signal-engine.php';
require __DIR__ . '/../includes/class-bitmomo-ai-key-drivers.php';

class Bitmomo_Regime_State_Store {
    public static $records = [];
    public static function instance() { return new self(); }
    public function get_latest() { return self::$records[0] ?? null; }
    public function get_recent($limit) { return array_slice(self::$records, 0, $limit); }
}
class Bitmomo_AI_Scorecard_Repository {
    public static function build() {
        $metric = ['n' => 4, 'correct' => 3, 'accuracy_pct' => 75, 'sample_status' => 'INSUFFICIENT SAMPLE', 'private_note' => 'must-not-leak'];
        return [
            'version_policy' => 'SEPARATED_INCOMPATIBLE_VERSIONS',
            'sample_rules' => ['minimum' => 10, 'strong' => 30],
            'versions' => ['engine-v1 | classifier-v1' => ['all' => $metric, 'rolling_30' => $metric, 'by_direction' => ['bullish' => $metric], 'confidence_calibration' => [array_merge($metric, ['range' => '0–100'])], 'baselines' => ['internal' => $metric]]],
            'expected_range' => ['policy' => 'FROZEN_ORIGINAL_ONLY', 'version_policy' => 'SINGLE_VERSION', 'versions' => ['engine-v1' => array_merge($metric, ['range_hit_pct' => 50])]],
            'regime_evaluation' => ['append_only_n' => 4, 'transition_n' => 1, 'transition_frequency_pct' => 33.3, 'versions' => ['classifier-v1' => ['accumulation' => array_merge($metric, ['average_forward_return_pct' => 1.2])]]],
            'data_quality' => array_merge($metric, ['stale_rate_pct' => 0]),
        ];
    }
}

$plugin_source = file_get_contents(__DIR__ . '/../bitmomo-ai.php');
$boundary = strpos($plugin_source, 'require_once BITMOMO_AI_DIR');
eval('?>' . substr($plugin_source, 0, $boundary));
require __DIR__ . '/../includes/class-bitmomo-ai-session-intelligence.php';
require __DIR__ . '/../includes/class-bitmomo-public-intelligence-adapter.php';

$input = [
    'close' => 65000, 'timestamp' => gmdate('c'),
    'direction' => ['adx' => 30, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'bullish', 'bias_4h' => 'bullish', 'bias_1d' => 'bullish'],
    'carry' => ['funding_rate' => 0, 'basis_pct' => 0], 'structure' => ['state' => 'range'],
    'crowding' => ['oi_change_24h_pct' => 0, 'price_change_24h_pct' => 0],
    'volatility' => ['regime' => 'normal', 'atr_pct_1h' => 1],
    'quality' => ['status' => 'complete', 'source' => 'Binance public market data + Binance USD-M'],
];
$evaluation = Bitmomo_AI_Signal_Engine::evaluate($input);
$GLOBALS['adapter_options']['bitmomo_ai_latest_preview'] = ['time' => gmdate('c'), 'data' => $input, 'evaluation' => $evaluation, 'edition' => 'morning', 'source_record_id' => 'secret-id'];
$GLOBALS['adapter_options']['bitmomo_ai_latest_quality_gate'] = ['status' => 'passed'];
Bitmomo_Regime_State_Store::$records = [
    ['as_of' => '2026-09-03 19:10:00', 'regime' => 'accumulation', 'directional_bias' => 'bullish', 'regime_confidence' => 70, 'classifier_version' => 'classifier-v1', 'edition' => 'us_session', 'provenance' => 'recorded_live', 'evidence' => ['secret']],
    ['as_of' => '2026-09-03 07:10:00', 'regime' => 'distribution', 'directional_bias' => 'bearish', 'classifier_version' => 'classifier-v1', 'edition' => 'morning', 'provenance' => 'recorded_live'],
    ['as_of' => '2026-09-02 19:10:00', 'regime' => 'transition', 'directional_bias' => 'neutral', 'classifier_version' => '', 'edition' => 'us_session', 'provenance' => 'recorded_live'],
    ['as_of' => '2026-09-01 19:10:00', 'regime' => 'expansion', 'directional_bias' => 'bullish', 'classifier_version' => 'classifier-v0', 'direction_strength' => 'strong_bullish', 'edition' => 'us_session', 'provenance' => 'historical_reconstruction'],
];

$checks = [];
function adapter_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }
$snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
$history = Bitmomo_Public_Intelligence_Adapter::history();
$summary = Bitmomo_Public_Intelligence_Adapter::evaluation_summary();
adapter_check('snapshot resolves canonical fields', $snapshot['market_state'] === 'accumulation' && $snapshot['direction_strength'] === $evaluation['direction_strength']);
adapter_check('snapshot exposes public-safe source/as-of/timezone', ($snapshot['provenance']['source'] ?? '') === 'Binance public market data' && ($snapshot['provenance']['as_of'] ?? '') !== '' && ($snapshot['provenance']['timezone'] ?? '') === 'Asia/Jakarta');
adapter_check('snapshot explicit allowlist hides internal data', !isset($snapshot['source_record_id'], $snapshot['axes'], $snapshot['score'], $snapshot['risk'], $snapshot['edition']));
adapter_check('history has one official row per date', $history['available_days'] === 2 && count($history['days']) === 2);
adapter_check('history prefers US Session official row', $history['days'][1]['market_state'] === 'accumulation');
adapter_check('history exposes bounded market-state certainty for public charts', ($history['days'][1]['market_state_certainty'] ?? null) === 70 && ($history['days'][0]['market_state_certainty'] ?? null) === 0);
adapter_check('history excludes reconstructed records', !in_array('2026-09-01', array_column($history['days'], 'date'), true));
adapter_check('history does not fabricate missing strength', !isset($history['days'][0]['direction_strength']));
adapter_check('history preserves unknown version', $history['days'][0]['version_group'] === 'unknown');
adapter_check('evaluation preserves version separation', $summary['version_policy'] === 'SEPARATED_INCOMPATIBLE_VERSIONS' && isset($summary['directional_evaluation']['engine-v1 | classifier-v1']));
adapter_check('evaluation preserves backend sample status', $summary['directional_evaluation']['engine-v1 | classifier-v1']['all']['sample_status'] === 'INSUFFICIENT SAMPLE');
adapter_check('Expected Range is aggregate frozen-original evaluation only', $summary['expected_range_evaluation']['policy'] === 'FROZEN_ORIGINAL_ONLY' && !isset($summary['expected_range_evaluation']['current_range']));
$encoded = json_encode([$snapshot, $history, $summary]);
foreach (['axes', 'risk', 'source_record_id', 'source_diagnostics', 'evidence', 'private_note', 'baselines'] as $forbidden) adapter_check("no {$forbidden} leak", strpos($encoded, '"' . $forbidden . '"') === false);

$GLOBALS['adapter_options']['bitmomo_ai_latest_preview']['data']['quality']['source'] = 'Binance public market data + Bybit linear perpetual fallback';
$fallback_snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
adapter_check('Bybit fallback is disclosed without exposing diagnostics', ($fallback_snapshot['provenance']['source'] ?? '') === 'Binance public market data + Bybit derivatives fallback');

$GLOBALS['adapter_options']['bitmomo_ai_latest_preview']['data']['quality']['source'] = 'internal-provider-do-not-publish';
adapter_check('unrecognized provider fails closed instead of guessing a source label', Bitmomo_Public_Intelligence_Adapter::snapshot() === null);
$GLOBALS['adapter_options']['bitmomo_ai_latest_preview']['data']['quality']['source'] = 'Binance public market data + Binance USD-M';

$GLOBALS['adapter_options']['bitmomo_ai_latest_quality_gate'] = ['status' => 'blocked'];
adapter_check('new blocked attempt does not hide the previous valid snapshot', Bitmomo_Public_Intelligence_Adapter::snapshot() !== null);

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " public-adapter checks passed.\n";
