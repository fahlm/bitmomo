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
require __DIR__ . '/../../bitmomo-regime/includes/class-bitmomo-regime-taxonomy.php';
require __DIR__ . '/../../bitmomo-regime/includes/class-bitmomo-regime-history.php';

class Bitmomo_Regime_State_Store {
    public static $records = [];
    public static function instance() { return new self(); }
    public function get_latest() { return self::$records[0] ?? null; }
    public function get_recent($limit) { return array_slice(self::$records, 0, $limit); }
}
class Bitmomo_AI_Opportunity_Store {
    public static $public = [
        'status' => 'available',
        'state' => 'LOW',
        'methodology_version' => 'opportunity-v1',
        'knowledge_time' => '',
        'record_id' => 'fast-private-record',
        'changed' => true,
    ];
    public static function public_latest() { return self::$public; }
}
class Bitmomo_AI_Scorecard_Repository {
    public static function build() {
        $metric = ['n' => 4, 'conclusive_n' => 3, 'correct' => 3, 'incorrect' => 0, 'inconclusive' => 1, 'accuracy_pct' => 100, 'sample_status' => 'INSUFFICIENT SAMPLE', 'private_note' => 'must-not-leak'];
        return [
            'version_policy' => 'SEPARATED_INCOMPATIBLE_VERSIONS',
            'sample_rules' => ['minimum' => 10, 'strong' => 30],
            'versions' => [
                'engine-v1 | classifier-v1 | observed-close-24h-v2' => [
                    'latest_generated_at' => '2026-09-12T20:10:00+00:00',
                    'outcome_methodology' => 'observed-close-24h-v2',
                    'all' => $metric,
                    'rolling_30' => $metric,
                    'by_direction' => ['bullish' => $metric],
                    'confidence_calibration' => [array_merge($metric, ['range' => '0–100'])],
                    'baselines' => ['internal' => $metric],
                ],
            ],
            'expected_range' => [
                'policy' => 'FROZEN_VERSIONED_ORIGINAL_ONLY',
                'version_policy' => 'SINGLE_VERSION',
                'versions' => ['engine-v1 | range-model-v1' => array_merge($metric, ['range_hit_pct' => 50])],
            ],
            'regime_evaluation' => ['append_only_n' => 4, 'transition_n' => 1, 'transition_frequency_pct' => 33.3, 'versions' => ['classifier-v1 | observed-close-24h-v2' => ['accumulation' => array_merge($metric, ['average_forward_return_pct' => 1.2])]]],
            'data_quality' => array_merge($metric, ['stale_rate_pct' => 0, 'settlement_n' => 3, 'settlement_evaluated_n' => 2, 'settlement_missed_n' => 1, 'settlement_pending_n' => 1, 'settlement_completeness_pct' => 66.7]),
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
$canonical_id = 'bitmomo-ai:2026-09-12T201000-0400:us_post_close:bbbb222222';
$GLOBALS['adapter_options']['bitmomo_ai_latest_preview'] = [
    'time' => gmdate('c'), 'data' => $input, 'evaluation' => $evaluation,
    'edition' => 'us_post_close', 'edition_id' => $canonical_id, 'source_record_id' => $canonical_id,
    'session_intelligence' => [
        'opportunity' => [
            'status' => 'available',
            'record_id' => 'opportunity-v1:20260912T201500Z',
            'state' => 'HIGH',
            'methodology_version' => 'opportunity-v1',
            'knowledge_time' => '2026-09-12T20:15:00+00:00',
            'changed' => true,
        ],
    ],
];
$GLOBALS['adapter_options']['bitmomo_ai_latest_quality_gate'] = ['status' => 'passed'];
Bitmomo_Regime_State_Store::$records = [
    ['as_of' => '2026-09-13 00:59:59', 'source_record_id' => $canonical_id, 'regime' => 'expansion', 'directional_bias' => 'bullish', 'regime_confidence' => 80, 'classifier_version' => 'classifier-v1', 'edition' => 'us_session', 'provenance' => 'recorded_live', 'evidence' => ['secret']],
    ['as_of' => '2026-09-12 12:59:59', 'source_record_id' => 'bitmomo-ai:2026-09-12T081000-0400:us_pre_open:aaaa111111', 'regime' => 'accumulation', 'directional_bias' => 'neutral', 'regime_confidence' => 55, 'classifier_version' => 'classifier-v1', 'edition' => 'morning', 'provenance' => 'recorded_live'],
    ['as_of' => '2026-09-12 00:59:59', 'source_record_id' => 'bitmomo-ai:2026-09-11T201000-0400:us_post_close:cccc333333', 'regime' => 'transition', 'directional_bias' => 'neutral', 'regime_confidence' => 0, 'classifier_version' => '', 'edition' => 'us_session', 'provenance' => 'recorded_live'],
    ['as_of' => '2026-09-11 00:59:59', 'source_record_id' => 'bitmomo-ai:2026-09-10T201000-0400:us_post_close:dddd444444', 'regime' => 'distribution', 'directional_bias' => 'bearish', 'regime_confidence' => 66, 'classifier_version' => 'classifier-v1', 'edition' => 'us_session', 'provenance' => 'recorded_live'],
    ['as_of' => '2026-09-10 00:59:59', 'source_record_id' => 'bitmomo-ai:2026-09-09T201000-0400:us_post_close:eeee555555', 'regime' => 'capitulation', 'directional_bias' => 'bearish', 'regime_confidence' => 90, 'classifier_version' => 'classifier-v0', 'edition' => 'us_session', 'provenance' => 'historical_reconstruction'],
];
Bitmomo_AI_Opportunity_Store::$public['knowledge_time'] = gmdate('c', time() - 300);

$checks = [];
function adapter_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }
$snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
$history = Bitmomo_Public_Intelligence_Adapter::history();
$summary = Bitmomo_Public_Intelligence_Adapter::evaluation_summary();
$current_version = 'engine-v1 | classifier-v1 | observed-close-24h-v2';

adapter_check('snapshot binds Market State to exact canonical edition', $snapshot['market_state'] === 'expansion' && ($snapshot['market_state_certainty'] ?? null) === 80 && $snapshot['direction_strength'] === $evaluation['direction_strength']);
adapter_check('snapshot exposes public-safe source/as-of/timezone', ($snapshot['provenance']['source'] ?? '') === 'Binance public market data' && ($snapshot['provenance']['as_of'] ?? '') !== '' && ($snapshot['provenance']['timezone'] ?? '') === 'Asia/Jakarta');
adapter_check('snapshot explicit allowlist hides internal data', !isset($snapshot['source_record_id'], $snapshot['axes'], $snapshot['score'], $snapshot['risk'], $snapshot['edition']));
adapter_check('snapshot top-level Opportunity uses independent fast clock', ($snapshot['opportunity']['status'] ?? '') === 'available' && ($snapshot['opportunity']['state'] ?? '') === 'LOW' && !isset($snapshot['opportunity']['record_id'], $snapshot['opportunity']['knowledge_time']));
adapter_check('canonical Opportunity remains frozen inside Major Brief lineage', ($snapshot['session_intelligence']['opportunity']['status'] ?? '') === 'available' && ($snapshot['session_intelligence']['opportunity']['state'] ?? '') === 'HIGH' && isset($snapshot['session_intelligence']['opportunity']['knowledge_time']));
adapter_check('UTC-crossing morning/post-close editions collapse to one official market day', $history['available_days'] === 3 && count($history['days']) === 3 && $history['days'][2]['date'] === '2026-09-12');
adapter_check('history prefers post-close record for duplicated US market date', $history['days'][2]['market_state'] === 'expansion');
adapter_check('history exposes bounded Market State certainty', ($history['days'][2]['market_state_certainty'] ?? null) === 80 && ($history['days'][1]['market_state_certainty'] ?? null) === 0);
adapter_check('history excludes reconstructed records', !in_array('2026-09-09', array_column($history['days'], 'date'), true));
adapter_check('history does not fabricate missing direction strength', !isset($history['days'][1]['direction_strength']));
adapter_check('history preserves unknown classifier version', $history['days'][1]['version_group'] === 'unknown');
adapter_check('evaluation preserves outcome-methodology version separation', $summary['version_policy'] === 'SEPARATED_INCOMPATIBLE_VERSIONS' && isset($summary['directional_evaluation'][$current_version]));
adapter_check('evaluation exposes safe outcome methodology metadata', $summary['directional_evaluation'][$current_version]['outcome_methodology'] === 'observed-close-24h-v2' && $summary['directional_evaluation'][$current_version]['latest_generated_at'] !== '');
adapter_check('evaluation preserves conclusive denominator and sample status', $summary['directional_evaluation'][$current_version]['all']['conclusive_n'] === 3 && $summary['directional_evaluation'][$current_version]['all']['sample_status'] === 'INSUFFICIENT SAMPLE');
adapter_check('insufficient sample accuracy is withheld at public adapter boundary', !array_key_exists('accuracy_pct', $summary['directional_evaluation'][$current_version]['all']));
adapter_check('settlement proof exposes matured/evaluated/missed/pending counts', $summary['data_quality']['settlement_n'] === 3 && $summary['data_quality']['settlement_evaluated_n'] === 2 && $summary['data_quality']['settlement_missed_n'] === 1 && $summary['data_quality']['settlement_pending_n'] === 1 && $summary['data_quality']['settlement_completeness_pct'] === 66.7);
adapter_check('Expected Range proof requires frozen versioned originals', $summary['expected_range_evaluation']['policy'] === 'FROZEN_VERSIONED_ORIGINAL_ONLY' && isset($summary['expected_range_evaluation']['versions']['engine-v1 | range-model-v1']) && !isset($summary['expected_range_evaluation']['current_range']));

$encoded = json_encode([$snapshot, $history, $summary]);
foreach (['axes', 'risk', 'source_record_id', 'record_id', 'source_diagnostics', 'evidence', 'private_note', 'baselines'] as $forbidden) adapter_check("no {$forbidden} leak", strpos($encoded, '"' . $forbidden . '"') === false);

$matching = Bitmomo_Regime_State_Store::$records[0];
Bitmomo_Regime_State_Store::$records = [Bitmomo_Regime_State_Store::$records[2]];
$without_match = Bitmomo_Public_Intelligence_Adapter::snapshot();
adapter_check('missing matching Regime fails only Market State closed', is_array($without_match) && $without_match['market_state'] === null && $without_match['market_state_certainty'] === null);

$invalid = $matching;
$invalid['regime'] = 'invented_state';
Bitmomo_Regime_State_Store::$records = [$invalid];
$invalid_state = Bitmomo_Public_Intelligence_Adapter::snapshot();
adapter_check('unrecognized Market State is rejected by public enum', is_array($invalid_state) && $invalid_state['market_state'] === null);
Bitmomo_Regime_State_Store::$records = [$matching];

$GLOBALS['adapter_options']['bitmomo_ai_latest_preview']['data']['quality']['source'] = 'Binance public market data + Bybit linear perpetual fallback';
$fallback_snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
adapter_check('Bybit fallback is disclosed without diagnostics', ($fallback_snapshot['provenance']['source'] ?? '') === 'Binance public market data + Bybit derivatives fallback');

$GLOBALS['adapter_options']['bitmomo_ai_latest_preview']['data']['quality']['source'] = 'internal-provider-do-not-publish';
adapter_check('unrecognized provider fails closed', Bitmomo_Public_Intelligence_Adapter::snapshot() === null);
$GLOBALS['adapter_options']['bitmomo_ai_latest_preview']['data']['quality']['source'] = 'Binance public market data + Binance USD-M';

$original_time = $GLOBALS['adapter_options']['bitmomo_ai_latest_preview']['time'];
$GLOBALS['adapter_options']['bitmomo_ai_latest_preview']['time'] = gmdate('c', time() - (7 * HOUR_IN_SECONDS));
$delayed = Bitmomo_Public_Intelligence_Adapter::snapshot();
adapter_check('delayed snapshot remains visible as provenance, not current intelligence',
    is_array($delayed) &&
    ($delayed['status'] ?? '') === 'delayed' &&
    ($delayed['btc_reference_price'] ?? 0) > 0 &&
    ($delayed['provenance']['as_of'] ?? '') !== '' &&
    ($delayed['freshness']['state'] ?? '') === 'delayed'
);
adapter_check('delayed Major Brief withholds slow-clock assessment while fast Opportunity stays independent',
    ($delayed['market_state'] ?? null) === null &&
    ($delayed['market_state_certainty'] ?? null) === null &&
    ($delayed['directional_bias'] ?? null) === null &&
    ($delayed['direction_strength'] ?? null) === null &&
    ($delayed['confidence']['value'] ?? null) === null &&
    ($delayed['confidence']['label'] ?? '') === '' &&
    ($delayed['key_drivers'] ?? []) === [] &&
    ($delayed['session_intelligence'] ?? []) === [] &&
    ($delayed['opportunity']['status'] ?? '') === 'available' &&
    ($delayed['opportunity']['state'] ?? '') === 'LOW' &&
    !isset($delayed['opportunity']['knowledge_time'])
);
$GLOBALS['adapter_options']['bitmomo_ai_latest_preview']['time'] = $original_time;

$GLOBALS['adapter_options']['bitmomo_ai_latest_quality_gate'] = ['status' => 'blocked'];
adapter_check('new blocked attempt does not hide previous valid snapshot', Bitmomo_Public_Intelligence_Adapter::snapshot() !== null);

$GLOBALS['adapter_options']['bitmomo_ai_latest_preview']['session_intelligence']['opportunity']['state'] = 'BROKEN';
$broken_opportunity_snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
adapter_check('malformed canonical Opportunity fails closed inside Major Brief lineage without corrupting fast clock',
    is_array($broken_opportunity_snapshot) &&
    ($broken_opportunity_snapshot['session_intelligence']['opportunity']['status'] ?? '') === 'unavailable' &&
    ($broken_opportunity_snapshot['opportunity']['state'] ?? '') === 'LOW'
);

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " public-adapter checks passed.\n";
