<?php
define('ABSPATH', __DIR__);
define('BITMOMO_AI_VERSION', '1.4.2-test');

require __DIR__ . '/../includes/class-bitmomo-research-feed-v1.php';

final class Bitmomo_AI_Intelligence {
    public static $projection = [];
    public static function free_projection() { return self::$projection; }
}

final class Bitmomo_AI_Runtime_State {
    public static $snapshot = [];
    public static function latest_valid_snapshot() { return self::$snapshot; }
}

require __DIR__ . '/../includes/class-bitmomo-research-feed-provider-v1.php';

$checks = [];
function provider_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

$now = strtotime('2026-09-14T12:00:00Z');

function provider_valid_projection() {
    return [
        'status' => 'fresh',
        'price' => 72123.45,
        'source' => 'Binance public market data',
        'bias' => 'bullish',
        'direction_strength' => 'bullish',
        'confidence' => 64,
        'key_drivers' => [
            'Spot structure remains constructive.',
            'Funding is elevated but not extreme.',
        ],
        'timestamp' => strtotime('2026-09-14T11:55:00Z'),
        'timestamp_iso' => '2026-09-14T11:55:00Z',
    ];
}

function provider_valid_snapshot() {
    return [
        'generated_at' => '2026-09-14T11:55:00Z',
        'source_record_id' => 'bitmomo-ai:official:20260914T115500Z',
        'data' => [
            'close' => 72123.45,
            'timestamp' => '2026-09-14T11:55:00Z',
        ],
        'evaluation' => [
            'bias' => 'bullish',
            'confidence' => 64,
            'quality' => ['status' => 'complete'],
        ],
        'opportunity' => [
            'status' => 'available',
            'state' => 'HIGH',
            'methodology_version' => 'opportunity-v1',
            'knowledge_time' => '2026-09-14T11:45:00Z',
        ],
    ];
}

function provider_set_valid_runtime() {
    Bitmomo_AI_Intelligence::$projection = provider_valid_projection();
    Bitmomo_AI_Runtime_State::$snapshot = provider_valid_snapshot();
}

function provider_valid_manual() {
    return [
        'research_slug' => 'funding-extremes-btc-reversals',
        'version' => 'v1',
        'title' => 'Do Funding Extremes Predict BTC Reversals?',
        'summary' => 'A point-in-time study of BTC funding extremes and subsequent price behavior.',
        'as_of' => '2026-09-13T18:00:00Z',
        'quality_status' => 'complete',
        'methodology_version' => 'funding-reversal-study-v1',
        'source_refs' => ['Binance USD-M historical funding', 'Binance BTCUSDT candles'],
        'limitations' => ['Historical relationships may change across market regimes.'],
        'findings' => [
            ['type' => 'result', 'value' => 'Extreme funding alone was not sufficient to classify direction.'],
        ],
        'metrics' => ['sample_n' => 842],
        'confidence' => 72,
        'topics' => ['bitcoin', 'derivatives'],
        'tags' => ['funding', 'research'],
    ];
}

provider_set_valid_runtime();
$current = Bitmomo_Research_Feed_Provider_V1::current_btc($now);
provider_check('matching canonical runtime reads produce a valid BTC research item', $current['valid'] === true && is_array($current['item']));
provider_check('provider preserves canonical source record id', $current['item']['provenance']['source_record_id'] === 'bitmomo-ai:official:20260914T115500Z');
provider_check('provider preserves canonical quality status', $current['item']['data_quality']['status'] === 'complete');
provider_check('provider preserves engine version lineage', $current['item']['provenance']['methodology_version'] === '1.4.2-test');
provider_check('Opportunity remains a separate activity metric', $current['item']['metrics']['opportunity_state'] === 'high' && $current['item']['metrics']['directional_bias'] === 'bullish');
provider_check('fresh complete canonical runtime is distribution eligible', $current['item']['distribution']['eligible'] === true);

provider_set_valid_runtime();
Bitmomo_AI_Runtime_State::$snapshot['generated_at'] = '2026-09-14T11:54:00Z';
$mismatch_time = Bitmomo_Research_Feed_Provider_V1::current_btc($now);
provider_check('timestamp lineage mismatch fails closed', $mismatch_time['valid'] === false && in_array('canonical_lineage_timestamp_mismatch', $mismatch_time['errors'], true));

provider_set_valid_runtime();
Bitmomo_AI_Runtime_State::$snapshot['data']['close'] = 72000.00;
$mismatch_price = Bitmomo_Research_Feed_Provider_V1::current_btc($now);
provider_check('price lineage mismatch fails closed', $mismatch_price['valid'] === false && in_array('canonical_lineage_price_mismatch', $mismatch_price['errors'], true));

provider_set_valid_runtime();
Bitmomo_AI_Runtime_State::$snapshot['evaluation']['bias'] = 'bearish';
$mismatch_bias = Bitmomo_Research_Feed_Provider_V1::current_btc($now);
provider_check('directional-bias lineage mismatch fails closed', $mismatch_bias['valid'] === false && in_array('canonical_lineage_bias_mismatch', $mismatch_bias['errors'], true));

provider_set_valid_runtime();
Bitmomo_AI_Runtime_State::$snapshot['evaluation']['quality']['status'] = 'degraded';
$degraded = Bitmomo_Research_Feed_Provider_V1::current_btc($now);
provider_check('degraded canonical runtime remains traceable', $degraded['valid'] === true && $degraded['item']['data_quality']['status'] === 'degraded');
provider_check('degraded canonical runtime is held from distribution', $degraded['item']['distribution']['eligible'] === false);

provider_set_valid_runtime();
Bitmomo_AI_Intelligence::$projection['status'] = 'delayed';
$delayed = Bitmomo_Research_Feed_Provider_V1::current_btc($now);
provider_check('delayed canonical runtime remains valid evidence', $delayed['valid'] === true);
provider_check('delayed canonical runtime is held from distribution', $delayed['item']['distribution']['eligible'] === false);

provider_set_valid_runtime();
Bitmomo_AI_Intelligence::$projection['status'] = 'unavailable';
$unavailable = Bitmomo_Research_Feed_Provider_V1::current_btc($now);
provider_check('unavailable public-safe intelligence fails closed', $unavailable['valid'] === false && in_array('btc_intelligence_unavailable', $unavailable['errors'], true));

provider_set_valid_runtime();
Bitmomo_AI_Intelligence::$projection['source'] = '';
$missing_source = Bitmomo_Research_Feed_Provider_V1::current_btc($now);
provider_check('missing canonical provenance source fails closed', $missing_source['valid'] === false && in_array('canonical_provenance_source_missing', $missing_source['errors'], true));

provider_set_valid_runtime();
unset(Bitmomo_AI_Runtime_State::$snapshot['source_record_id']);
$fallback_id = Bitmomo_Research_Feed_Provider_V1::current_btc($now);
provider_check('missing explicit record id falls back deterministically to canonical snapshot timestamp', $fallback_id['valid'] === true && $fallback_id['item']['provenance']['source_record_id'] === 'bitmomo-ai:' . strtotime('2026-09-14T11:55:00Z'));

provider_set_valid_runtime();
$manual = provider_valid_manual();
$invalid_manual = provider_valid_manual();
$invalid_manual['source_refs'] = [];
$feed = Bitmomo_Research_Feed_Provider_V1::build([$manual, $manual, $invalid_manual], $now);
provider_check('aggregator combines current BTC and valid manual research', $feed['counts']['items'] === 2);
provider_check('aggregator deduplicates repeated manual research deterministically', $feed['counts']['duplicates'] === 1);
provider_check('aggregator reports rejected invalid manual research', $feed['counts']['rejected'] === 1 && $feed['rejected'][0]['source_type'] === 'manual_research');
provider_check('aggregator counts only distribution-eligible unique items', $feed['counts']['distribution_eligible'] === 2);
provider_check('aggregator exposes versioned provider contract', $feed['contract_version'] === 'research-feed-v1' && $feed['provider_version'] === 'research-feed-provider-v1');

provider_set_valid_runtime();
Bitmomo_AI_Intelligence::$projection['status'] = 'unavailable';
$feed_without_btc = Bitmomo_Research_Feed_Provider_V1::build([provider_valid_manual()], $now);
provider_check('aggregator keeps valid manual research when current BTC is unavailable', $feed_without_btc['counts']['items'] === 1 && $feed_without_btc['items'][0]['source_type'] === 'manual_research');
provider_check('aggregator records unavailable BTC as rejected instead of fabricating it', $feed_without_btc['counts']['rejected'] === 1 && $feed_without_btc['rejected'][0]['source_type'] === 'btc_intelligence');

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " Research Feed Provider V1 checks passed.\n";
