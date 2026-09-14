<?php
define('ABSPATH', __DIR__);

require __DIR__ . '/../includes/class-bitmomo-research-feed-v1.php';

$checks = [];
function research_feed_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

$now = strtotime('2026-09-14T12:00:00Z');

function valid_btc_source() {
    return [
        'status' => 'fresh',
        'quality_status' => 'complete',
        'btc_reference_price' => 72123.45,
        'directional_bias' => 'bullish',
        'direction_strength' => 'bullish',
        'market_state' => 'expansion',
        'confidence' => ['value' => 64, 'label' => 'medium'],
        'key_drivers' => [
            'Spot structure remains constructive.',
            'Funding is elevated but not extreme.',
        ],
        'opportunity' => [
            'state' => 'HIGH',
            'methodology_version' => 'opportunity-v1',
        ],
        'provenance' => [
            'source' => 'Binance public market data',
            'as_of' => '2026-09-14T11:55:00Z',
        ],
        'versions' => [
            'engine' => '1.4.2',
            'opportunity' => 'opportunity-v1',
        ],
        'topics' => ['btc'],
        'tags' => ['intraday', 'bitcoin'],
    ];
}

$btc = Bitmomo_Research_Feed_V1::from_btc_intelligence(valid_btc_source(), $now);
research_feed_check('valid fresh BTC intelligence projects successfully', $btc['valid'] === true && is_array($btc['item']));
research_feed_check('fresh complete BTC intelligence is distribution eligible', $btc['item']['distribution']['eligible'] === true);
research_feed_check('BTC source lineage is deterministic from canonical as_of', $btc['item']['provenance']['source_record_id'] === 'bitmomo-ai:' . strtotime('2026-09-14T11:55:00Z'));
research_feed_check('confidence semantics explicitly reject probability interpretation', $btc['item']['confidence']['semantics'] === 'evidence_strength_not_probability');
research_feed_check('Opportunity remains a separate activity metric', $btc['item']['metrics']['opportunity_state'] === 'high' && $btc['item']['metrics']['directional_bias'] === 'bullish');

$btc_again = Bitmomo_Research_Feed_V1::from_btc_intelligence(valid_btc_source(), $now);
research_feed_check('identical BTC source produces stable research id', $btc_again['item']['research_id'] === $btc['item']['research_id']);
research_feed_check('identical BTC source produces stable fingerprint', $btc_again['item']['fingerprint'] === $btc['item']['fingerprint']);

$reordered = valid_btc_source();
$reordered['tags'] = ['bitcoin', 'intraday'];
$reordered['topics'] = ['btc'];
$reordered['key_drivers'] = array_reverse($reordered['key_drivers']);
$reordered_result = Bitmomo_Research_Feed_V1::from_btc_intelligence($reordered, $now);
research_feed_check('unordered list normalization prevents cosmetic duplicate fingerprints', $reordered_result['item']['fingerprint'] === $btc['item']['fingerprint']);

$delayed = valid_btc_source();
$delayed['status'] = 'delayed';
$delayed_result = Bitmomo_Research_Feed_V1::from_btc_intelligence($delayed, $now);
research_feed_check('delayed BTC intelligence remains valid evidence', $delayed_result['valid'] === true);
research_feed_check('delayed BTC intelligence is held from distribution', $delayed_result['item']['distribution']['eligible'] === false && in_array('requires_fresh_btc_intelligence', $delayed_result['item']['distribution']['reasons'], true));

$degraded = valid_btc_source();
$degraded['quality_status'] = 'degraded';
$degraded_result = Bitmomo_Research_Feed_V1::from_btc_intelligence($degraded, $now);
research_feed_check('degraded BTC intelligence remains traceable', $degraded_result['valid'] === true);
research_feed_check('degraded BTC intelligence is held from distribution', $degraded_result['item']['distribution']['eligible'] === false && in_array('requires_complete_quality', $degraded_result['item']['distribution']['reasons'], true));

$bad_bias = valid_btc_source();
$bad_bias['directional_bias'] = 'risk_on';
$bad_bias_result = Bitmomo_Research_Feed_V1::from_btc_intelligence($bad_bias, $now);
research_feed_check('unknown directional bias fails closed instead of being reinterpreted', $bad_bias_result['valid'] === false && in_array('directional_bias', $bad_bias_result['errors'], true));

$missing_provenance = valid_btc_source();
unset($missing_provenance['provenance']['source']);
$missing_provenance_result = Bitmomo_Research_Feed_V1::from_btc_intelligence($missing_provenance, $now);
research_feed_check('missing BTC provenance fails closed', $missing_provenance_result['valid'] === false && in_array('provenance.source', $missing_provenance_result['errors'], true));

$future = valid_btc_source();
$future['provenance']['as_of'] = '2026-09-14T12:06:00Z';
$future_result = Bitmomo_Research_Feed_V1::from_btc_intelligence($future, $now);
research_feed_check('future-dated BTC intelligence beyond tolerance fails closed', $future_result['valid'] === false && in_array('provenance.as_of', $future_result['errors'], true));

function valid_manual_research() {
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
            ['type' => 'result', 'value' => 'Context materially changed the observed outcome distribution.'],
        ],
        'metrics' => ['sample_n' => 842],
        'confidence' => 72,
        'topics' => ['bitcoin', 'derivatives'],
        'tags' => ['funding', 'research'],
    ];
}

$manual = Bitmomo_Research_Feed_V1::from_manual_research(valid_manual_research(), $now);
research_feed_check('valid manual research projects successfully', $manual['valid'] === true && is_array($manual['item']));
research_feed_check('complete manual research is distribution eligible', $manual['item']['distribution']['eligible'] === true);
research_feed_check('manual research preserves declared methodology', $manual['item']['provenance']['methodology_version'] === 'funding-reversal-study-v1');

$manual_reordered = valid_manual_research();
$manual_reordered['findings'] = array_reverse($manual_reordered['findings']);
$manual_reordered['source_refs'] = array_reverse($manual_reordered['source_refs']);
$manual_reordered_result = Bitmomo_Research_Feed_V1::from_manual_research($manual_reordered, $now);
research_feed_check('manual research fingerprint is stable across unordered evidence input', $manual_reordered_result['item']['fingerprint'] === $manual['item']['fingerprint']);

$manual_degraded = valid_manual_research();
$manual_degraded['quality_status'] = 'degraded';
$manual_degraded_result = Bitmomo_Research_Feed_V1::from_manual_research($manual_degraded, $now);
research_feed_check('degraded manual research is retained but not distributed', $manual_degraded_result['valid'] === true && $manual_degraded_result['item']['distribution']['eligible'] === false);

$manual_no_sources = valid_manual_research();
$manual_no_sources['source_refs'] = [];
$manual_no_sources_result = Bitmomo_Research_Feed_V1::from_manual_research($manual_no_sources, $now);
research_feed_check('manual research without source references fails closed', $manual_no_sources_result['valid'] === false && in_array('source_refs', $manual_no_sources_result['errors'], true));

$manual_no_limits = valid_manual_research();
$manual_no_limits['limitations'] = [];
$manual_no_limits_result = Bitmomo_Research_Feed_V1::from_manual_research($manual_no_limits, $now);
research_feed_check('manual research without limitations fails closed', $manual_no_limits_result['valid'] === false && in_array('limitations', $manual_no_limits_result['errors'], true));

$dedupe = Bitmomo_Research_Feed_V1::deduplicate([$btc['item'], $btc_again['item'], $manual['item']]);
research_feed_check('dedupe retains only unique research items', count($dedupe['items']) === 2);
research_feed_check('dedupe emits explicit duplicate diagnostics', count($dedupe['duplicates']) === 1 && $dedupe['duplicates'][0]['research_id'] === $btc['item']['research_id']);

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " Research Feed V1 checks passed.\n";
