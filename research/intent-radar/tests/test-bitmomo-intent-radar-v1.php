<?php

require __DIR__ . '/../includes/class-bitmomo-intent-radar-v1.php';

$checks = [];
function radar_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

$now = strtotime('2026-09-14T13:00:00Z');

function eligible_research_item() {
    return [
        'research_id' => 'bmr-btc-test-001',
        'fingerprint' => 'sha256:research-001',
        'title' => 'BTC Market Intelligence and Derivatives Context',
        'summary' => 'Bitcoin directional bias, funding context, market drivers, and scenario framing.',
        'topics' => ['bitcoin', 'market-intelligence', 'derivatives'],
        'tags' => ['btc', 'funding', 'direction'],
        'distribution' => ['eligible' => true],
    ];
}

function ineligible_research_item() {
    $item = eligible_research_item();
    $item['research_id'] = 'bmr-btc-test-blocked';
    $item['fingerprint'] = 'sha256:research-blocked';
    $item['distribution']['eligible'] = false;
    return $item;
}

function x_fixture($text, $id = 'x-001', $created_at = '2026-09-14T12:55:00Z', $author = 'public-user-1') {
    return [
        'id' => $id,
        'url' => 'https://x.example/' . $id,
        'surface' => 'post',
        'text' => $text,
        'author_ref' => $author,
        'created_at' => $created_at,
    ];
}

$high_signal = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('Anyone have a BTC signal today?'),
    [eligible_research_item()],
    $now
);
radar_check('explicit BTC signal demand is valid', $high_signal['valid'] === true);
radar_check('explicit BTC signal maps to explicit_signal intent', $high_signal['opportunity']['intent']['primary'] === 'explicit_signal');
radar_check('explicit BTC signal ranks as high intent', $high_signal['opportunity']['score'] >= 85);
radar_check('high-intent unsolicited reply candidate requires human approval', $high_signal['opportunity']['suggested_action'] === 'review_for_reply' && $high_signal['opportunity']['approval_required'] === true);
radar_check('auto outbound is never permitted', $high_signal['opportunity']['auto_outbound_permitted'] === false);
radar_check('classification confidence is not presented as conversion probability', $high_signal['opportunity']['confidence']['semantics'] === 'classification_confidence_not_conversion_probability');
radar_check('eligible research enriches the opportunity', count($high_signal['opportunity']['research_links']) >= 1 && $high_signal['opportunity']['research_links'][0]['research_id'] === 'bmr-btc-test-001');

$generic = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC is green again.', 'x-generic'),
    [eligible_research_item()],
    $now
);
radar_check('generic BTC chatter remains valid observation', $generic['valid'] === true);
radar_check('generic BTC chatter has no high-intent classification', $generic['opportunity']['intent']['primary'] === null);
radar_check('generic BTC chatter is ignored rather than promoted', $generic['opportunity']['score'] < 35 && $generic['opportunity']['suggested_action'] === 'ignore');

$indonesian = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC long atau short sekarang?', 'x-id'),
    [],
    $now
);
radar_check('Indonesian long-short demand is detected', $indonesian['opportunity']['intent']['primary'] === 'long_short');
radar_check('Indonesian language is detected', $indonesian['opportunity']['detected_language'] === 'id');
radar_check('Indonesian high-intent query remains priority reply candidate', $indonesian['opportunity']['score'] >= 75 && $indonesian['opportunity']['approval_required'] === true);

$entry = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('Kapan masuk BTC? entry di mana sekarang', 'x-entry'),
    [],
    $now
);
radar_check('entry demand is classified independently', $entry['opportunity']['intent']['primary'] === 'entry_exit');

$analysis = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('Bitcoin analysis today — what is the BTC next move?', 'x-analysis'),
    [],
    $now
);
radar_check('analysis and prediction demand is detected', $analysis['opportunity']['intent']['primary'] === 'analysis_prediction');

$levels = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('Where is BTC support and resistance today?', 'x-levels'),
    [],
    $now
);
radar_check('support/resistance demand is detected', $levels['opportunity']['intent']['primary'] === 'breakout_support_resistance');

$why = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('Why is BTC dumping right now?', 'x-why'),
    [],
    $now
);
radar_check('why-move demand is detected', $why['opportunity']['intent']['primary'] === 'why_move');

$event = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC CPI reaction today?', 'x-event'),
    [],
    $now
);
radar_check('event-reaction demand is detected', $event['opportunity']['intent']['primary'] === 'event_reaction');

$ai = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('Anyone using an AI BTC model for analysis today?', 'x-ai'),
    [],
    $now
);
radar_check('AI research demand is detected', $ai['opportunity']['intent']['primary'] === 'ai_research');

$spam = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC signal today guaranteed profit join vip https://a.example https://b.example', 'x-spam'),
    [],
    $now
);
radar_check('promotional spam signals are detected', $spam['opportunity']['spam']['hard_block'] === true);
radar_check('hard-block spam is ignored even when signal keywords match', $spam['opportunity']['suggested_action'] === 'ignore' && $spam['opportunity']['approval_required'] === false);
radar_check('spam score is materially below clean signal demand', $spam['opportunity']['score'] + 30 < $high_signal['opportunity']['score']);

$stale = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC signal?', 'x-stale', '2026-09-12T12:00:00Z'),
    [],
    $now
);
radar_check('observations older than 48h fail closed', $stale['valid'] === false && in_array('stale_observation', $stale['errors'], true));

$aging = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC signal today?', 'x-aging', '2026-09-13T12:00:00Z'),
    [],
    $now
);
radar_check('24h-old observations remain traceable but lose freshness boost', $aging['valid'] === true && $aging['opportunity']['freshness']['band'] === 'old' && $aging['opportunity']['score_components']['freshness'] === 0);

$missing_ref = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC signal?', '', '2026-09-14T12:55:00Z'),
    [],
    $now
);
radar_check('missing source identity fails closed', $missing_ref['valid'] === false && in_array('source_ref', $missing_ref['errors'], true));

$future = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC signal?', 'x-future', '2026-09-14T13:06:00Z'),
    [],
    $now
);
radar_check('future-dated observation beyond tolerance fails closed', $future['valid'] === false && in_array('observed_at', $future['errors'], true));

$youtube = Bitmomo_Intent_Radar_V1::from_youtube_fixture([
    'id' => 'yt-001',
    'title' => 'Bitcoin Analysis Today: BTC Long or Short?',
    'description' => 'Three levels that matter for the next move.',
    'url' => 'https://youtube.example/watch?v=yt-001',
    'author_ref' => 'channel-1',
    'published_at' => '2026-09-14T12:50:00Z',
], [], $now);
radar_check('YouTube fixture adapter builds an opportunity without network access', $youtube['valid'] === true && $youtube['opportunity']['source'] === 'youtube');
radar_check('YouTube search-style title can express long-short intent', $youtube['opportunity']['intent']['primary'] === 'long_short');

$blocked_research = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('Anyone have a BTC signal today?', 'x-blocked-research'),
    [ineligible_research_item()],
    $now
);
radar_check('distribution-ineligible research is never attached', count($blocked_research['opportunity']['research_links']) === 0);

$no_research = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('Anyone have a BTC signal today?', 'x-no-research'),
    [],
    $now
);
radar_check('no-research fallback remains a valid intent opportunity', $no_research['valid'] === true && $no_research['opportunity']['research_links'] === []);
radar_check('research enrichment increases score only when eligible evidence matches', $high_signal['opportunity']['score_components']['research_fit'] === 5 && $no_research['opportunity']['score_components']['research_fit'] === 0);

$duplicate_a = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC long or short today?', 'x-dup', '2026-09-14T12:55:00Z', 'same-author'),
    [],
    $now
)['opportunity'];
$duplicate_b = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC long or short today?', 'x-dup', '2026-09-14T12:55:00Z', 'same-author'),
    [],
    $now
)['opportunity'];
$dedupe = Bitmomo_Intent_Radar_V1::deduplicate([$duplicate_a, $duplicate_b]);
radar_check('identical observations produce deterministic opportunity IDs', $duplicate_a['opportunity_id'] === $duplicate_b['opportunity_id']);
radar_check('identical observations produce deterministic fingerprints', $duplicate_a['fingerprint'] === $duplicate_b['fingerprint']);
radar_check('dedupe keeps one unique opportunity', count($dedupe['items']) === 1 && count($dedupe['duplicates']) === 1);

$edited_same_source = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC long or short right now?', 'x-dup', '2026-09-14T12:56:00Z', 'same-author'),
    [],
    $now
)['opportunity'];
$dedupe_edit = Bitmomo_Intent_Radar_V1::deduplicate([$duplicate_a, $edited_same_source]);
radar_check('same source object stays deduplicated even if public text is edited', count($dedupe_edit['items']) === 1 && count($dedupe_edit['duplicates']) === 1);

$cooldown_state = [
    $duplicate_a['cooldown_key'] => '2026-09-14T12:30:00Z',
];
radar_check('recent prior engagement activates deterministic cooldown', Bitmomo_Intent_Radar_V1::in_cooldown($duplicate_a, $cooldown_state, $now) === true);
$expired_cooldown = [
    $duplicate_a['cooldown_key'] => '2026-09-14T06:00:00Z',
];
radar_check('expired cooldown releases the opportunity', Bitmomo_Intent_Radar_V1::in_cooldown($duplicate_a, $expired_cooldown, $now) === false);

$monitor = Bitmomo_Intent_Radar_V1::from_x_fixture(
    x_fixture('BTC ETF update', 'x-monitor'),
    [],
    $now
);
radar_check('lower-confidence demand can remain monitor-only without outbound action', in_array($monitor['opportunity']['suggested_action'], ['monitor', 'ignore'], true) && $monitor['opportunity']['auto_outbound_permitted'] === false);

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " Intent Radar V1 checks passed.\n";
