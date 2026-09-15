<?php
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);

function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }

class Bitmomo_AI_Session_Intelligence {
    const MARKET_TIMEZONE = 'America/New_York';
    public static function is_supported_session_type($value) {
        return in_array(sanitize_key((string) $value), ['us_pre_open', 'us_post_close', 'morning', 'us_session'], true);
    }
    public static function normalize_session_type($value) {
        $value = sanitize_key((string) $value);
        return in_array($value, ['us_pre_open', 'morning'], true) ? 'us_pre_open' : 'us_post_close';
    }
}

class Bitmomo_AI_Intelligence {
    public static $projection = [];
    public static function free_projection() { return self::$projection; }
}

class Bitmomo_Regime_State_Store {
    public static $records = [];
    public static function instance() { return new self(); }
    public function get_recent($limit) { return array_slice(self::$records, 0, $limit); }
}

class Bitmomo_AI_Opportunity {
    const METHODOLOGY_VERSION = 'opportunity-v1';
}

class Bitmomo_AI_Opportunity_Store {
    public static $public = ['status' => 'unavailable', 'methodology_version' => 'opportunity-v1'];
    public static function public_latest() { return self::$public; }
}

class Bitmomo_AI_Scorecard_Repository {
    public static function build() {
        $insufficient = [
            'n' => 8,
            'conclusive_n' => 6,
            'correct' => 5,
            'incorrect' => 1,
            'inconclusive' => 2,
            'accuracy_pct' => 83.3,
            'sample_status' => 'INSUFFICIENT SAMPLE',
        ];
        $early = array_merge($insufficient, [
            'n' => 14,
            'conclusive_n' => 12,
            'accuracy_pct' => 66.7,
            'sample_status' => 'EARLY SAMPLE',
        ]);
        return [
            'version_policy' => 'SINGLE_VERSION',
            'sample_rules' => ['minimum' => 10, 'strong' => 30],
            'versions' => [
                'engine-v1' => [
                    'latest_generated_at' => '2026-09-15T04:00:00+00:00',
                    'outcome_methodology' => 'observed-close-24h-v2',
                    'all' => $insufficient,
                    'rolling_30' => $early,
                    'by_direction' => ['bullish' => $insufficient],
                    'confidence_calibration' => [$insufficient],
                ],
            ],
            'expected_range' => [
                'policy' => 'FROZEN_VERSIONED_ORIGINAL_ONLY',
                'version_policy' => 'SINGLE_VERSION',
                'versions' => [],
            ],
            'regime_evaluation' => [],
            'data_quality' => $early,
        ];
    }
}

require __DIR__ . '/../includes/class-bitmomo-public-intelligence-adapter.php';

$checks = [];
function trust_check($label, $condition) {
    global $checks;
    $checks[] = [$label, (bool) $condition];
}

$canonical_id = 'bitmomo-ai:2026-09-14T201000-0400:us_post_close:test';
$base_projection = [
    'status' => 'fresh',
    'price' => 65000,
    'source' => 'Binance public market data',
    'bias' => 'bullish',
    'direction_strength' => 'strong_bullish',
    'confidence' => 82,
    'timestamp' => time() - 60,
    'timestamp_iso' => gmdate('c', time() - 60),
    'freshness_label' => 'fresh',
    'latest_attempt' => [],
    'edition_id' => $canonical_id,
    'session_type' => 'us_post_close',
    'session_label' => 'US POST-CLOSE',
    'session_anchor' => '2026-09-14T20:10:00-04:00',
    'us_market_status' => 'regular_session_day',
    'key_drivers' => ['Momentum menguat.'],
    'session_intelligence' => [
        'current_setup' => [],
        'opportunity' => [
            'status' => 'available',
            'state' => 'HIGH',
            'methodology_version' => 'opportunity-v1',
            'knowledge_time' => '2026-09-15T00:00:00+00:00',
            'changed' => false,
        ],
    ],
];

Bitmomo_Regime_State_Store::$records = [[
    'source_record_id' => $canonical_id,
    'provenance' => 'recorded_live',
    'regime' => 'expansion',
    'regime_confidence' => 78,
    'classifier_version' => 'classifier-v1',
]];
Bitmomo_AI_Opportunity_Store::$public = [
    'status' => 'available',
    'state' => 'LOW',
    'methodology_version' => 'opportunity-v1',
    'knowledge_time' => gmdate('c', time() - 300),
    'record_id' => 'private-fast-record-id',
    'changed' => true,
];
Bitmomo_AI_Intelligence::$projection = $base_projection;

$fresh = Bitmomo_Public_Intelligence_Adapter::snapshot();
trust_check('top-level Opportunity is owned by fresh fast store, not frozen Major Brief lineage',
    ($fresh['opportunity']['state'] ?? '') === 'LOW' &&
    ($fresh['session_intelligence']['opportunity']['state'] ?? '') === 'HIGH'
);
trust_check('fast Opportunity exposes state but no independent public timestamp or record id',
    !isset($fresh['opportunity']['knowledge_time'], $fresh['opportunity']['record_id'])
);
trust_check('fresh Major Brief still exposes validated slow-clock directional assessment',
    ($fresh['directional_bias'] ?? '') === 'bullish' &&
    ($fresh['confidence']['value'] ?? null) === 82 &&
    ($fresh['market_state'] ?? '') === 'expansion'
);

$delayed_projection = $base_projection;
$delayed_projection['status'] = 'delayed';
$delayed_projection['timestamp'] = time() - (7 * 3600);
$delayed_projection['timestamp_iso'] = gmdate('c', $delayed_projection['timestamp']);
$delayed_projection['freshness_label'] = 'delayed';
Bitmomo_AI_Intelligence::$projection = $delayed_projection;
$delayed = Bitmomo_Public_Intelligence_Adapter::snapshot();

trust_check('delayed Major Brief withholds slow-clock assessment',
    is_array($delayed) &&
    ($delayed['status'] ?? '') === 'delayed' &&
    ($delayed['directional_bias'] ?? null) === null &&
    ($delayed['direction_strength'] ?? null) === null &&
    ($delayed['market_state'] ?? null) === null &&
    ($delayed['confidence']['value'] ?? null) === null &&
    ($delayed['key_drivers'] ?? []) === [] &&
    ($delayed['session_intelligence'] ?? []) === []
);
trust_check('fresh fast Opportunity survives delayed Major Brief without refreshing brief provenance',
    ($delayed['opportunity']['status'] ?? '') === 'available' &&
    ($delayed['opportunity']['state'] ?? '') === 'LOW' &&
    !isset($delayed['opportunity']['knowledge_time']) &&
    ($delayed['provenance']['as_of'] ?? '') === $delayed_projection['timestamp_iso']
);

Bitmomo_AI_Opportunity_Store::$public = ['status' => 'unavailable', 'methodology_version' => 'opportunity-v1'];
$without_fast = Bitmomo_Public_Intelligence_Adapter::snapshot();
trust_check('fast Opportunity fails closed independently when its freshness gate is unavailable',
    ($without_fast['opportunity']['status'] ?? '') === 'unavailable'
);

$summary = Bitmomo_Public_Intelligence_Adapter::evaluation_summary();
$all = $summary['directional_evaluation']['engine-v1']['all'] ?? [];
$rolling = $summary['directional_evaluation']['engine-v1']['rolling_30'] ?? [];
trust_check('insufficient sample never exposes accuracy_pct through public adapter',
    ($all['sample_status'] ?? '') === 'INSUFFICIENT SAMPLE' && !array_key_exists('accuracy_pct', $all)
);
trust_check('early sample may retain accuracy only with explicit EARLY SAMPLE status',
    ($rolling['sample_status'] ?? '') === 'EARLY SAMPLE' && ($rolling['accuracy_pct'] ?? null) === 66.7
);

$plugin_source = file_get_contents(__DIR__ . '/../bitmomo-ai.php');
$guard = strpos($plugin_source, "defined('BITMOMO_AI_OPPORTUNITY_ENABLED') && BITMOMO_AI_OPPORTUNITY_ENABLED");
$register = strpos($plugin_source, 'Bitmomo_AI_Opportunity::register();');
trust_check('Opportunity polling is explicit opt-in rather than a release-side effect',
    false !== $guard && false !== $register && $guard < $register &&
    !preg_match('/define\s*\(\s*[\'\"]BITMOMO_AI_OPPORTUNITY_ENABLED[\'\"]/', $plugin_source)
);

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " public clock/trust boundary checks passed.\n";
