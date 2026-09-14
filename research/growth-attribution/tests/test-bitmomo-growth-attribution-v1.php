<?php
require_once __DIR__ . '/../includes/class-bitmomo-growth-attribution-v1.php';
require_once __DIR__ . '/../includes/class-bitmomo-growth-attribution-report-v1.php';

$failures = 0;
$checks = 0;
function ok($condition, $label) {
    global $failures, $checks;
    $checks++;
    if ($condition) echo "PASS: {$label}\n";
    else { echo "FAIL: {$label}\n"; $failures++; }
}

$now = strtotime('2026-09-14T13:00:00Z');
$lineage = [
    'research_id' => 'bmr-2026-017',
    'opportunity_id' => 'bio-abc123',
    'brief_id' => 'bcb-def456',
    'draft_id' => 'bed-ghi789',
    'intent' => 'long_short',
    'keyword_cluster' => 'btc-long-or-short',
    'channel' => 'x',
    'format' => 'x_post',
    'utm_source' => 'x',
    'utm_medium' => 'social',
    'utm_campaign' => 'bmr-campaign-017',
    'utm_content' => 'x-post-long-short',
];

$base = [
    'event_type' => 'site_click',
    'event_ref' => 'evt:site-click:001',
    'occurred_at' => '2026-09-14T12:10:00Z',
    'actor_ref' => 'anon_0000000000000001',
    'session_ref' => 'sess_0000000000000001',
    'lineage' => $lineage,
    'metadata' => ['surface' => 'x_profile'],
];

$r = Bitmomo_Growth_Attribution_V1::ingest($base, $now);
ok($r['valid'] === true, 'valid campaign event is accepted');
ok($r['event']['contract_version'] === 'growth-event-v1', 'growth event contract is versioned');
ok($r['event']['attribution_semantics'] === 'observational_not_causal', 'attribution semantics are explicitly non-causal');
ok(strpos($r['event']['event_id'], 'bge-') === 0, 'event id is deterministic namespace');

$r2 = Bitmomo_Growth_Attribution_V1::ingest($base, $now);
ok($r2['event']['event_id'] === $r['event']['event_id'], 'identical event ref/type produces stable event id');
ok($r2['event']['fingerprint'] === $r['event']['fingerprint'], 'identical event produces stable fingerprint');

$pii = $base; $pii['email'] = 'person@example.com';
ok(Bitmomo_Growth_Attribution_V1::ingest($pii, $now)['valid'] === false, 'explicit email field fails closed');
$bad_actor = $base; $bad_actor['actor_ref'] = 'john@example.com';
ok(Bitmomo_Growth_Attribution_V1::ingest($bad_actor, $now)['valid'] === false, 'email-shaped actor ref fails closed');
$bad_handle = $base; $bad_handle['actor_ref'] = '@someuser';
ok(Bitmomo_Growth_Attribution_V1::ingest($bad_handle, $now)['valid'] === false, 'platform-handle actor ref fails closed');
$bad_meta = $base; $bad_meta['metadata'] = ['surface' => '203.0.113.5'];
ok(Bitmomo_Growth_Attribution_V1::ingest($bad_meta, $now)['valid'] === false, 'IP-shaped metadata fails closed');
$bad_wallet = $base; $bad_wallet['metadata'] = ['experiment_ref' => '0x1111111111111111111111111111111111111111'];
ok(Bitmomo_Growth_Attribution_V1::ingest($bad_wallet, $now)['valid'] === false, 'wallet-shaped metadata fails closed');

$future = $base; $future['event_ref'] = 'evt:future:001'; $future['occurred_at'] = '2026-09-14T14:00:00Z';
ok(Bitmomo_Growth_Attribution_V1::ingest($future, $now)['valid'] === false, 'future event beyond tolerance fails closed');

$missing_campaign = $base; $missing_campaign['event_type'] = 'whitelist_complete'; $missing_campaign['event_ref'] = 'evt:wlc:missing'; unset($missing_campaign['lineage']['utm_campaign']);
ok(Bitmomo_Growth_Attribution_V1::ingest($missing_campaign, $now)['valid'] === false, 'qualified conversion without campaign lineage fails closed');

$intent = [
    'event_type' => 'intent_detected',
    'event_ref' => 'evt:intent:001',
    'occurred_at' => '2026-09-14T11:00:00Z',
    'lineage' => ['channel' => 'x', 'format' => 'unknown', 'intent' => 'long_short', 'keyword_cluster' => 'btc-long-or-short'],
];
ok(Bitmomo_Growth_Attribution_V1::ingest($intent, $now)['valid'] === true, 'intent detection does not require downstream campaign fields');
$bad_intent = $intent; $bad_intent['event_ref'] = 'evt:intent:002'; unset($bad_intent['lineage']['keyword_cluster']);
ok(Bitmomo_Growth_Attribution_V1::ingest($bad_intent, $now)['valid'] === false, 'intent detection requires keyword cluster');

$click = $base;
$visit = $base; $visit['event_type'] = 'profile_visit'; $visit['event_ref'] = 'evt:profile:001'; $visit['occurred_at'] = '2026-09-14T12:00:00Z';
$view = $base; $view['event_type'] = 'btc_page_view'; $view['event_ref'] = 'evt:btcpage:001'; $view['occurred_at'] = '2026-09-14T12:20:00Z'; $view['lineage']['channel'] = 'website'; $view['lineage']['format'] = 'landing_page';
$complete = $base; $complete['event_type'] = 'whitelist_complete'; $complete['event_ref'] = 'evt:wlc:001'; $complete['occurred_at'] = '2026-09-14T12:30:00Z'; $complete['lineage']['channel'] = 'website'; $complete['lineage']['format'] = 'landing_page';
$ledger = Bitmomo_Growth_Attribution_V1::build_ledger([$complete, $view, $click, $visit, $click, ['bad']], $now);
ok($ledger['counts']['inputs'] === 6, 'ledger counts all supplied inputs');
ok($ledger['counts']['duplicates'] === 1, 'ledger diagnoses duplicate event deterministically');
ok($ledger['counts']['rejected'] === 1, 'ledger rejects malformed event without blocking valid events');
ok($ledger['counts']['events'] === 4, 'ledger retains unique valid events');
ok($ledger['events'][0]['event_type'] === 'profile_visit', 'out-of-order events are sorted by occurred_at');
ok($ledger['events'][3]['event_type'] === 'whitelist_complete', 'qualified conversion sorts at its actual event time');

$report = Bitmomo_Growth_Attribution_Report_V1::build($ledger, 20, 3);
ok($report['qualified_conversion_event'] === 'whitelist_complete', 'whitelist complete is the qualified conversion outcome');
ok($report['funnel']['whitelist_complete'] === 1, 'report counts qualified whitelist conversion');
ok($report['touch_attribution']['first_touch']['channel']['x'] === 1, 'first touch is descriptively attributed to earliest preconversion channel');
ok($report['touch_attribution']['last_touch']['channel']['website'] === 1, 'last touch is descriptively attributed to latest preconversion channel');
ok($report['touch_attribution']['actor_refs_exposed'] === false, 'report never exposes actor refs');
ok($report['attribution_semantics'] === 'observational_not_causal', 'report preserves non-causal semantics');
ok($report['learning_policy']['winner_selection_permitted'] === false, 'report cannot declare a winner');

$events = [];
for ($i = 1; $i <= 25; $i++) {
    $hex = str_pad(dechex($i), 16, '0', STR_PAD_LEFT);
    $actor = 'anon_' . $hex;
    $campaign = $i <= 20 ? 'campaign-a' : 'campaign-b';
    $intent_name = $i <= 20 ? 'long_short' : 'analysis_prediction';
    $lin = $lineage;
    $lin['utm_campaign'] = $campaign;
    $lin['intent'] = $intent_name;
    $lin['keyword_cluster'] = $i <= 20 ? 'btc-long-or-short' : 'bitcoin-analysis-today';
    $events[] = [
        'event_type' => 'site_click', 'event_ref' => 'evt:bulk:click:' . $i,
        'occurred_at' => '2026-09-14T10:00:00Z', 'actor_ref' => $actor,
        'lineage' => $lin,
    ];
    if ($i <= 4 || $i === 21) {
        $events[] = [
            'event_type' => 'whitelist_complete', 'event_ref' => 'evt:bulk:wlc:' . $i,
            'occurred_at' => '2026-09-14T10:30:00Z', 'actor_ref' => $actor,
            'lineage' => $lin,
        ];
    }
}
$bulk = Bitmomo_Growth_Attribution_V1::build_ledger($events, $now);
ok($bulk['counts']['rejected'] === 0, 'bulk deterministic fixture is fully accepted');
$bulk_report = Bitmomo_Growth_Attribution_Report_V1::build($bulk, 20, 3);
$campaigns = [];
foreach ($bulk_report['segments']['campaign'] as $segment) $campaigns[$segment['value']] = $segment;
ok($campaigns['campaign-a']['unique_actors'] === 20, 'campaign segment counts unique anonymous actors');
ok($campaigns['campaign-a']['unique_converted_actors'] === 4, 'campaign segment counts unique converted actors');
ok(abs($campaigns['campaign-a']['descriptive_whitelist_conversion_rate'] - 0.2) < 0.000001, 'descriptive conversion rate is calculated without causal language');
ok($campaigns['campaign-a']['learning_signal']['status'] === 'eligible_observation', 'learning signal activates only after minimum actor/conversion sample');
ok($campaigns['campaign-a']['learning_signal']['winner_recommendation'] === null, 'eligible observation still does not recommend winner');
ok($campaigns['campaign-b']['learning_signal']['status'] === 'insufficient_sample', 'small sample remains insufficient even with a conversion');

$intents = [];
foreach ($bulk_report['segments']['intent'] as $segment) $intents[$segment['value']] = $segment;
ok(isset($intents['long_short']), 'report segments by intent');
$keywords = [];
foreach ($bulk_report['segments']['keyword_cluster'] as $segment) $keywords[$segment['value']] = $segment;
ok(isset($keywords['btc-long-or-short']), 'report segments by keyword cluster');
$research = [];
foreach ($bulk_report['segments']['research_id'] as $segment) $research[$segment['value']] = $segment;
ok(isset($research['bmr-2026-017']), 'report segments by research id');
$formats = [];
foreach ($bulk_report['segments']['format'] as $segment) $formats[$segment['value']] = $segment;
ok(isset($formats['x_post']), 'report segments by content format');
$channels = [];
foreach ($bulk_report['segments']['channel'] as $segment) $channels[$segment['value']] = $segment;
ok(isset($channels['x']), 'report segments by channel');

$reversed = array_reverse($events);
$report_reversed = Bitmomo_Growth_Attribution_Report_V1::build(Bitmomo_Growth_Attribution_V1::build_ledger($reversed, $now), 20, 3);
ok($report_reversed['segments']['campaign'] === $bulk_report['segments']['campaign'], 'report is deterministic even when events arrive out of order');
ok($report_reversed['touch_attribution'] === $bulk_report['touch_attribution'], 'touch attribution is deterministic across input ordering');

if ($failures) exit(1);
echo "All {$checks} Growth Attribution V1 checks passed.\n";
