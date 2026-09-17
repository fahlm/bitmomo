<?php
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);

function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function __($value, $domain = null) { return $value; }
function human_time_diff($from, $to) { return max(1, (int) floor(abs($to - $from) / 60)) . ' menit'; }

class Bitmomo_AI_Session_Intelligence {
    const MARKET_TIMEZONE = 'America/New_York';
    public static function is_supported_session_type($value) {
        return in_array(sanitize_key((string) $value), ['us_pre_open', 'us_post_close', 'morning', 'us_session'], true);
    }
    public static function normalize_session_type($value) {
        $value = sanitize_key((string) $value);
        return in_array($value, ['us_pre_open', 'morning'], true) ? 'us_pre_open' : 'us_post_close';
    }
    public static function opposite($value) {
        return self::normalize_session_type($value) === 'us_pre_open' ? 'us_post_close' : 'us_pre_open';
    }
    public static function next_anchor($session_type, DateTimeImmutable $now = null) {
        $timezone = new DateTimeZone(self::MARKET_TIMEZONE);
        $now = $now ? $now->setTimezone($timezone) : new DateTimeImmutable('now', $timezone);
        $is_pre = self::normalize_session_type($session_type) === 'us_pre_open';
        $candidate = $now->setTime($is_pre ? 8 : 20, 10, 0);
        if ($candidate <= $now) $candidate = $candidate->modify('+1 day');
        return $candidate;
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

$ny = new DateTimeZone('America/New_York');
$now_ny = new DateTimeImmutable('now', $ny);
$today_pre = $now_ny->setTime(8, 10, 0);
$today_post = $now_ny->setTime(20, 10, 0);
if ($today_post <= $now_ny) {
    $current_type = 'us_post_close';
    $current_anchor = $today_post;
} elseif ($today_pre <= $now_ny) {
    $current_type = 'us_pre_open';
    $current_anchor = $today_pre;
} else {
    $current_type = 'us_post_close';
    $current_anchor = $today_post->modify('-1 day');
}

$canonical_id = 'bitmomo-ai:current-session:test';
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
    'session_type' => $current_type,
    'session_label' => $current_type === 'us_pre_open' ? 'US PRE-OPEN' : 'US POST-CLOSE',
    'session_anchor' => $current_anchor->format(DateTimeInterface::ATOM),
    'us_market_status' => 'regular_session_day',
    'key_drivers' => ['Momentum menguat.'],
    'session_intelligence' => [
        'current_setup' => [],
        'opportunity' => [
            'status' => 'available',
            'state' => 'HIGH',
            'methodology_version' => 'opportunity-v1',
            'knowledge_time' => gmdate('c', time() - 300),
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
trust_check('fast Opportunity is fresh through the first 15 minutes',
    ($fresh['opportunity']['freshness_state'] ?? '') === 'fresh' &&
    ($fresh['opportunity']['age_seconds'] ?? 9999) <= 15 * MINUTE_IN_SECONDS
);
trust_check('current Major Brief exposes validated slow-clock directional assessment',
    ($fresh['status'] ?? '') === 'fresh' &&
    ($fresh['directional_bias'] ?? '') === 'bullish' &&
    ($fresh['confidence']['value'] ?? null) === 82 &&
    ($fresh['market_state'] ?? '') === 'expansion'
);

// A Major Brief can be older than the legacy six-hour threshold and still be
// current when the next session anchor has not arrived yet.
$current_but_old = $base_projection;
$current_but_old['status'] = 'delayed';
$current_but_old['timestamp'] = time() - (7 * 3600);
$current_but_old['timestamp_iso'] = gmdate('c', $current_but_old['timestamp']);
$current_but_old['freshness_label'] = 'delayed';
Bitmomo_AI_Intelligence::$projection = $current_but_old;
$current = Bitmomo_Public_Intelligence_Adapter::snapshot();
trust_check('Major Brief freshness follows the next session anchor, not the legacy six-hour age',
    is_array($current) &&
    ($current['status'] ?? '') === 'fresh' &&
    ($current['directional_bias'] ?? '') === 'bullish'
);

// Force a genuinely overdue Major Brief by moving its anchor two days back.
$overdue_projection = $base_projection;
$overdue_projection['status'] = 'delayed';
$overdue_projection['timestamp'] = time() - (20 * 3600);
$overdue_projection['timestamp_iso'] = gmdate('c', $overdue_projection['timestamp']);
$overdue_projection['session_anchor'] = $current_anchor->modify('-2 days')->format(DateTimeInterface::ATOM);
Bitmomo_AI_Intelligence::$projection = $overdue_projection;
$overdue = Bitmomo_Public_Intelligence_Adapter::snapshot();
trust_check('overdue Major Brief withholds slow-clock assessment',
    is_array($overdue) &&
    ($overdue['status'] ?? '') === 'delayed' &&
    ($overdue['directional_bias'] ?? null) === null &&
    ($overdue['direction_strength'] ?? null) === null &&
    ($overdue['market_state'] ?? null) === null &&
    ($overdue['confidence']['value'] ?? null) === null &&
    ($overdue['key_drivers'] ?? []) === [] &&
    ($overdue['session_intelligence'] ?? []) === []
);
trust_check('fresh fast Opportunity survives an overdue Major Brief without refreshing brief provenance',
    ($overdue['opportunity']['status'] ?? '') === 'available' &&
    ($overdue['opportunity']['state'] ?? '') === 'LOW' &&
    !isset($overdue['opportunity']['knowledge_time']) &&
    ($overdue['provenance']['as_of'] ?? '') === $overdue_projection['timestamp_iso']
);

Bitmomo_AI_Intelligence::$projection = $base_projection;
Bitmomo_AI_Opportunity_Store::$public = [
    'status' => 'available',
    'state' => 'NORMAL',
    'methodology_version' => 'opportunity-v1',
    'knowledge_time' => gmdate('c', time() - (20 * MINUTE_IN_SECONDS)),
    'changed' => false,
];
$pulse_delayed = Bitmomo_Public_Intelligence_Adapter::snapshot();
trust_check('Market Pulse is explicitly delayed between 15 and 30 minutes',
    ($pulse_delayed['opportunity']['status'] ?? '') === 'available' &&
    ($pulse_delayed['opportunity']['freshness_state'] ?? '') === 'delayed'
);

Bitmomo_AI_Opportunity_Store::$public['knowledge_time'] = gmdate('c', time() - (31 * MINUTE_IN_SECONDS));
$pulse_expired = Bitmomo_Public_Intelligence_Adapter::snapshot();
trust_check('Market Pulse fails closed after 30 minutes',
    ($pulse_expired['opportunity']['status'] ?? '') === 'unavailable'
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
trust_check('Opportunity polling remains explicit opt-in in the production plugin',
    false !== $guard && false !== $register && $guard < $register &&
    !preg_match('/define\s*\(\s*[\'\"]BITMOMO_AI_OPPORTUNITY_ENABLED[\'\"]/', $plugin_source)
);

$staging_guard = file_get_contents(__DIR__ . '/../../../../../config/staging/bitmomo-staging-safety.php');
trust_check('canonical staging explicitly enables launch-critical Market Pulse polling',
    false !== strpos($staging_guard, "define( 'BITMOMO_AI_OPPORTUNITY_ENABLED', true )")
);

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " public clock/trust boundary checks passed.\n";
