<?php

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);

function sanitize_key($value) {
    return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value));
}
function sanitize_text_field($value) {
    return trim(strip_tags((string) $value));
}

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
    public static $projection = array();
    public static function free_projection() { return self::$projection; }
}

class Bitmomo_AI_Scorecard_Repository {
    public static $scorecard = array();
    public static function build() { return self::$scorecard; }
}

require __DIR__ . '/../includes/class-bitmomo-public-intelligence-adapter.php';

$checks = array();
function trust_check($label, $condition) {
    global $checks;
    $checks[] = array($label, (bool) $condition);
}

$base_projection = array(
    'status' => 'fresh',
    'edition_id' => 'bitmomo-ai:test-record',
    'session_type' => 'us_post_close',
    'session_label' => 'US POST-CLOSE',
    'session_anchor' => gmdate('c', time() - 60),
    'us_market_status' => 'regular_session_day',
    'directional_bias' => 'bullish',
    'direction_strength' => 'strong_bullish',
    'source' => 'Binance public market data',
    'timestamp_iso' => gmdate('c', time() - 60),
    'timestamp' => time() - 60,
    'price' => 65000.0,
    'confidence' => 82,
    'freshness_label' => 'fresh',
    'latest_attempt' => array(),
    'key_drivers' => array('Momentum menguat.'),
    'session_intelligence' => array('current_setup' => array()),
);

Bitmomo_AI_Intelligence::$projection = $base_projection;
trust_check('valid canonical projection reaches public snapshot', is_array(Bitmomo_Public_Intelligence_Adapter::snapshot()));

$invalid = $base_projection;
$invalid['session_type'] = 'made_up_session';
Bitmomo_AI_Intelligence::$projection = $invalid;
trust_check('unsupported session fails closed at public adapter', null === Bitmomo_Public_Intelligence_Adapter::snapshot());

$invalid = $base_projection;
$invalid['price'] = 'not-a-number';
Bitmomo_AI_Intelligence::$projection = $invalid;
trust_check('non-numeric BTC reference price fails closed', null === Bitmomo_Public_Intelligence_Adapter::snapshot());

$invalid = $base_projection;
$invalid['price'] = 0;
Bitmomo_AI_Intelligence::$projection = $invalid;
trust_check('non-positive BTC reference price fails closed', null === Bitmomo_Public_Intelligence_Adapter::snapshot());

$invalid = $base_projection;
$invalid['confidence'] = 'high';
Bitmomo_AI_Intelligence::$projection = $invalid;
trust_check('non-numeric confidence fails closed', null === Bitmomo_Public_Intelligence_Adapter::snapshot());

$invalid = $base_projection;
$invalid['timestamp_iso'] = gmdate('c', time() + 10 * MINUTE_IN_SECONDS);
Bitmomo_AI_Intelligence::$projection = $invalid;
trust_check('future canonical timestamp fails closed', null === Bitmomo_Public_Intelligence_Adapter::snapshot());

Bitmomo_AI_Scorecard_Repository::$scorecard = array(
    'versions' => array(
        'engine-v1' => array(
            'latest_generated_at' => gmdate('c'),
            'outcome_methodology' => 'observed-close-24h-v2',
            'all' => array(
                'n' => 8,
                'conclusive_n' => 6,
                'accuracy_pct' => 83.3,
                'sample_status' => 'INSUFFICIENT SAMPLE',
            ),
            'rolling_30' => array(
                'n' => 8,
                'conclusive_n' => 6,
                'accuracy_pct' => 83.3,
                'sample_status' => 'EARLY SAMPLE',
            ),
        ),
    ),
);
$summary = Bitmomo_Public_Intelligence_Adapter::evaluation_summary();
$all_metric = $summary['directional_evaluation']['engine-v1']['all'] ?? array();
$early_metric = $summary['directional_evaluation']['engine-v1']['rolling_30'] ?? array();
trust_check('insufficient sample withholds public accuracy percentage', !array_key_exists('accuracy_pct', $all_metric));
trust_check('early sample may retain explicitly provisional accuracy statistic', isset($early_metric['accuracy_pct']) && (float) $early_metric['accuracy_pct'] === 83.3);

$main = file_get_contents(__DIR__ . '/../bitmomo-ai.php');
trust_check('canonical source rejects invalid bias rather than coercing neutral',
    false !== strpos($main, "if (!in_array(\$bias, ['bullish', 'neutral', 'bearish'], true)) return null;")
    && false === strpos($main, "\$bias = 'neutral';")
);
trust_check('canonical source requires numeric confidence and score',
    false !== strpos($main, "!is_numeric(\$evaluation['confidence'])")
    && false !== strpos($main, "!is_numeric(\$evaluation['score'])")
);
$validate_pos = strpos($main, 'is_supported_session_type($raw_session_type)');
$normalize_pos = strpos($main, 'normalize_session_type($raw_session_type)');
trust_check('raw session is validated before normalization', false !== $validate_pos && false !== $normalize_pos && $validate_pos < $normalize_pos);
trust_check('Whitelist V1 does not activate heavy Opportunity polling implicitly',
    false === strpos($main, "class-bitmomo-ai-opportunity.php")
    && false === strpos($main, 'Bitmomo_AI_Opportunity::register()')
);

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " public source trust checks passed.\n";
