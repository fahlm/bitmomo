<?php

require_once __DIR__ . '/../../intent-radar/includes/class-bitmomo-intent-radar-v1.php';
require_once __DIR__ . '/../../intent-radar/includes/class-bitmomo-intent-radar-queue-v1.php';
require_once __DIR__ . '/../../content-compiler/includes/class-bitmomo-content-compiler-v1.php';
require_once __DIR__ . '/../../engagement-copilot/includes/class-bitmomo-engagement-copilot-v1.php';
require_once __DIR__ . '/../../engagement-copilot/includes/class-bitmomo-engagement-copilot-queue-v1.php';
require_once __DIR__ . '/../../growth-attribution/includes/class-bitmomo-growth-attribution-v1.php';
require_once __DIR__ . '/../includes/class-bitmomo-acquisition-orchestrator-v1.php';

$checks = 0;
$failures = 0;
function orchestrator_ok($condition, $label) {
    global $checks, $failures;
    $checks++;
    if ($condition) echo "PASS: {$label}\n";
    else { echo "FAIL: {$label}\n"; $failures++; }
}

$now = strtotime('2026-09-14T14:00:00Z');
$research = [[
    'contract_version' => 'research-feed-v1',
    'research_id' => 'bmr-btc-live-001',
    'fingerprint' => 'sha256:btc-live-001',
    'source_type' => 'btc_intelligence',
    'title' => 'BTC Intelligence — Current Market Structure',
    'summary' => 'Canonical BTC intelligence snapshot for downstream research distribution tooling.',
    'findings' => [
        ['type' => 'directional_bias', 'value' => 'bullish'],
        ['type' => 'primary_driver', 'value' => 'Spot structure remains constructive.'],
    ],
    'metrics' => ['btc_reference_price' => 72123.45, 'directional_bias' => 'bullish'],
    'limitations' => [
        'Short-horizon market intelligence; not a guaranteed forecast or trading instruction.',
        'Directional bias can change when market structure changes.',
    ],
    'topics' => ['bitcoin', 'market-intelligence', 'direction'],
    'tags' => ['btc', 'scenario', 'bias'],
    'provenance' => [
        'source_record_id' => 'bitmomo-ai:1789390800',
        'as_of' => '2026-09-14T13:00:00Z',
        'producer' => 'Binance public market data',
        'methodology_version' => '1.4.2',
        'source_refs' => ['Binance public market data'],
    ],
    'distribution' => ['eligible' => true, 'reasons' => []],
], [
    'contract_version' => 'research-feed-v1',
    'research_id' => 'bmr-blocked-001',
    'fingerprint' => 'sha256:blocked-001',
    'source_type' => 'manual_research',
    'title' => 'Blocked Research',
    'summary' => 'This item is intentionally distribution-ineligible.',
    'findings' => [['type' => 'result', 'value' => 'Do not distribute this item.']],
    'metrics' => [],
    'limitations' => ['Distribution is blocked.'],
    'topics' => ['bitcoin'],
    'tags' => ['btc'],
    'provenance' => [
        'source_record_id' => 'manual:blocked:001',
        'as_of' => '2026-09-14T13:00:00Z',
        'producer' => 'bitmomo-research',
        'methodology_version' => 'blocked-v1',
        'source_refs' => ['fixture'],
    ],
    'distribution' => ['eligible' => false, 'reasons' => ['fixture_block']],
]];

$observations = [
    [
        'source' => 'x',
        'payload' => [
            'id' => 'x-demand-001',
            'url' => 'https://x.example/x-demand-001',
            'surface' => 'post',
            'text' => 'BTC long or short today? Which side looks stronger right now?',
            'author_ref' => 'third-party-a',
            'created_at' => '2026-09-14T13:50:00Z',
        ],
    ],
    [
        'source' => 'youtube',
        'payload' => [
            'id' => 'yt-demand-001',
            'url' => 'https://youtube.example/watch?v=yt-demand-001',
            'surface' => 'search_result',
            'title' => 'BTC Long or Short Today? Bitcoin Analysis',
            'description' => 'A market analysis query fixture.',
            'author_ref' => 'channel-a',
            'published_at' => '2026-09-14T13:40:00Z',
        ],
    ],
    [
        'source' => 'x',
        'payload' => [
            'id' => 'x-chatter-001',
            'surface' => 'post',
            'text' => 'Bitcoin is amazing today.',
            'author_ref' => 'third-party-b',
            'created_at' => '2026-09-14T13:45:00Z',
        ],
    ],
    [
        'source' => 'reddit',
        'payload' => ['id' => 'unsupported-001', 'text' => 'BTC long or short?'],
    ],
];

$run = Bitmomo_Acquisition_Orchestrator_V1::run($research, $observations, [], [], [], $now);
orchestrator_ok($run['run_version'] === 'acquisition-run-v1', 'acquisition run contract is versioned');
orchestrator_ok($run['mode'] === 'dry_run_only', 'orchestrator is explicitly dry-run only');
orchestrator_ok($run['safety']['network_access_permitted'] === false, 'network access is frozen off');
orchestrator_ok($run['safety']['auto_publish_permitted'] === false, 'auto-publish is frozen off');
orchestrator_ok($run['safety']['auto_send_permitted'] === false, 'auto-send is frozen off');
orchestrator_ok($run['safety']['production_mutation_permitted'] === false, 'production mutation is frozen off');

orchestrator_ok($run['counts']['intent_review'] === 1, 'high-intent X request reaches review queue');
orchestrator_ok($run['counts']['intent_monitor'] >= 1, 'YouTube search demand remains monitorable owned-content intent');
orchestrator_ok($run['counts']['intent_ignored'] >= 1, 'generic BTC chatter is ignored rather than promoted');
orchestrator_ok($run['counts']['intent_rejected'] === 1, 'unsupported observation source is diagnosed without blocking valid work');
orchestrator_ok(count($run['worklist']['engagement_review']) === 1, 'operator receives one evidence-grounded engagement review draft');
orchestrator_ok(count($run['worklist']['owned_content']) === 5, 'eligible research compiles into five native owned-content briefs');

$draft = $run['worklist']['engagement_review'][0];
orchestrator_ok($draft['status'] === 'human_review_only', 'engagement draft remains human-review-only end to end');
orchestrator_ok($draft['approval_required'] === true, 'engagement draft requires approval end to end');
orchestrator_ok($draft['auto_send_permitted'] === false, 'engagement draft cannot auto-send end to end');
orchestrator_ok($draft['evidence_trace']['research_id'] === 'bmr-btc-live-001', 'engagement draft preserves canonical research lineage');

$briefs = $run['worklist']['owned_content'];
$all_draft_only = true;
$all_auto_off = true;
$blocked_present = false;
$youtube_found = false;
foreach ($briefs as $brief) {
    if (($brief['status'] ?? null) !== 'draft_only') $all_draft_only = false;
    if (!empty($brief['auto_publish_permitted'])) $all_auto_off = false;
    if (($brief['source_research']['research_id'] ?? '') === 'bmr-blocked-001') $blocked_present = true;
    if (($brief['format'] ?? '') === 'youtube_search' && ($brief['intent_context']['primary'] ?? '') === 'long_short') $youtube_found = true;
}
orchestrator_ok($all_draft_only, 'all owned-content outputs remain draft-only');
orchestrator_ok($all_auto_off, 'all owned-content outputs keep auto-publish off');
orchestrator_ok(!$blocked_present, 'distribution-ineligible research cannot drive owned-content output');
orchestrator_ok($youtube_found, 'search intent can steer a native YouTube search brief');

$attribution = $run['attribution_ledger'];
orchestrator_ok($attribution['counts']['rejected'] === 0, 'orchestrator-generated attribution events satisfy P4 contract');
orchestrator_ok($attribution['counts']['events'] === $run['counts']['attribution_events'], 'attribution event count is internally consistent');
$event_types = array_map(function ($event) { return $event['event_type']; }, $attribution['events']);
orchestrator_ok(in_array('intent_detected', $event_types, true), 'orchestrator creates attribution-ready intent events');
orchestrator_ok(in_array('draft_created', $event_types, true), 'orchestrator creates attribution-ready draft events');
$no_actor_pii = true;
foreach ($attribution['events'] as $event) {
    if (($event['actor_ref'] ?? null) !== null || ($event['session_ref'] ?? null) !== null) $no_actor_pii = false;
}
orchestrator_ok($no_actor_pii, 'dry-run attribution events contain no actor/session identifiers');

$run_again = Bitmomo_Acquisition_Orchestrator_V1::run($research, $observations, [], [], [], $now);
orchestrator_ok($run_again['run_id'] === $run['run_id'], 'identical input produces stable run id');
orchestrator_ok($run_again['fingerprint'] === $run['fingerprint'], 'identical input produces stable end-to-end fingerprint');
orchestrator_ok(
    array_column($run_again['worklist']['owned_content'], 'brief_id') === array_column($run['worklist']['owned_content'], 'brief_id'),
    'identical input produces equivalent owned-content worklist'
);
orchestrator_ok(
    array_column($run_again['worklist']['engagement_review'], 'draft_id') === array_column($run['worklist']['engagement_review'], 'draft_id'),
    'identical input produces equivalent engagement worklist'
);

$reversed = array_reverse($observations);
$run_reversed = Bitmomo_Acquisition_Orchestrator_V1::run($research, $reversed, [], [], [], $now);
orchestrator_ok($run_reversed['run_id'] === $run['run_id'], 'run identity is insensitive to observation input order');
orchestrator_ok($run_reversed['fingerprint'] === $run['fingerprint'], 'worklist fingerprint is deterministic across observation ordering');

$cooldown_key = $run['intent_queue']['review'][0]['cooldown_key'];
$cooldown_state = [$cooldown_key => '2026-09-14T13:59:00Z'];
$cooled = Bitmomo_Acquisition_Orchestrator_V1::run($research, $observations, $cooldown_state, [], [], $now);
orchestrator_ok($cooled['counts']['intent_review'] === 0, 'active cooldown removes X opportunity from engagement review');
orchestrator_ok(count($cooled['worklist']['engagement_review']) === 0, 'cooldown prevents draft work from reappearing in operator review');
orchestrator_ok(($cooled['intent_queue']['counts']['cooldown'] ?? 0) === 1, 'cooldown opportunity remains traceable in diagnostics');

if ($failures) exit(1);
echo "All {$checks} Acquisition Orchestrator V1 checks passed.\n";
