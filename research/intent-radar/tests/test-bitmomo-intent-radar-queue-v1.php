<?php

require __DIR__ . '/../includes/class-bitmomo-intent-radar-v1.php';
require __DIR__ . '/../includes/class-bitmomo-intent-radar-queue-v1.php';

$checks = [];
function queue_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

$now = strtotime('2026-09-14T13:00:00Z');

$research = [[
    'research_id' => 'bmr-btc-queue-001',
    'fingerprint' => 'sha256:queue-research-001',
    'title' => 'BTC Market Intelligence',
    'summary' => 'Bitcoin directional bias and market drivers.',
    'topics' => ['bitcoin', 'market-intelligence'],
    'tags' => ['btc', 'direction'],
    'distribution' => ['eligible' => true],
]];

$signal_payload = [
    'id' => 'x-q-001',
    'text' => 'Anyone have a BTC signal today?',
    'author_ref' => 'queue-user-1',
    'created_at' => '2026-09-14T12:55:00Z',
];

$observations = [
    ['source' => 'x', 'payload' => $signal_payload],
    ['source' => 'x', 'payload' => $signal_payload], // duplicate
    ['source' => 'x', 'payload' => [
        'id' => 'x-q-002',
        'text' => 'BTC ETF update',
        'author_ref' => 'queue-user-2',
        'created_at' => '2026-09-14T12:56:00Z',
    ]],
    ['source' => 'x', 'payload' => [
        'id' => 'x-q-003',
        'text' => 'BTC is green again.',
        'author_ref' => 'queue-user-3',
        'created_at' => '2026-09-14T12:57:00Z',
    ]],
    ['source' => 'youtube', 'payload' => [
        'id' => 'yt-q-001',
        'title' => 'Bitcoin Analysis Today: BTC Long or Short?',
        'description' => 'Three levels that matter.',
        'author_ref' => 'channel-queue-1',
        'published_at' => '2026-09-14T12:50:00Z',
        'surface' => 'search_result',
    ]],
    ['source' => 'reddit', 'payload' => [
        'id' => 'unsupported-1',
        'text' => 'BTC signal?',
        'created_at' => '2026-09-14T12:55:00Z',
    ]],
    ['source' => 'x', 'payload' => [
        'id' => 'x-q-stale',
        'text' => 'BTC signal?',
        'created_at' => '2026-09-12T12:00:00Z',
    ]],
];

$queue = Bitmomo_Intent_Radar_Queue_V1::build($observations, $research, [], $now);
queue_check('queue contract is versioned', $queue['queue_version'] === 'intent-radar-queue-v1' && $queue['radar_version'] === 'intent-radar-v1');
queue_check('all supplied observations are counted', $queue['counts']['input'] === 7);
queue_check('duplicate observations are diagnosed', $queue['counts']['duplicates'] === 1 && count($queue['duplicates']) === 1);
queue_check('unsupported and stale observations are rejected', $queue['counts']['rejected'] === 2 && count($queue['rejected']) === 2);
queue_check('high-intent X request enters human review queue', $queue['counts']['review'] === 1 && $queue['review'][0]['source_ref'] === 'x-q-001');
queue_check('review queue preserves mandatory human approval', $queue['review'][0]['approval_required'] === true && $queue['review'][0]['auto_outbound_permitted'] === false);
queue_check('eligible upstream research survives into queued opportunity', count($queue['review'][0]['research_links']) >= 1 && $queue['review'][0]['research_links'][0]['research_id'] === 'bmr-btc-queue-001');
queue_check('high-scoring non-request X observation remains monitor-only', $queue['counts']['monitor'] >= 1 && in_array('x-q-002', array_column($queue['monitor'], 'source_ref'), true));
queue_check('YouTube search result is monitor-only rather than outbound reply candidate', in_array('yt-q-001', array_column($queue['monitor'], 'source_ref'), true));
queue_check('generic BTC chatter is routed to ignored bucket', $queue['counts']['ignored'] === 1 && $queue['ignored'][0]['source_ref'] === 'x-q-003');

$single = Bitmomo_Intent_Radar_V1::from_x_fixture($signal_payload, $research, $now)['opportunity'];
$cooldown_state = [$single['cooldown_key'] => '2026-09-14T12:30:00Z'];
$cooldown_queue = Bitmomo_Intent_Radar_Queue_V1::build([
    ['source' => 'x', 'payload' => $signal_payload],
], $research, $cooldown_state, $now);
queue_check('cooldown removes otherwise-reviewable opportunity from review queue', $cooldown_queue['counts']['review'] === 0 && $cooldown_queue['counts']['cooldown'] === 1);
queue_check('cooldown retains the opportunity for traceability', $cooldown_queue['cooldown'][0]['opportunity_id'] === $single['opportunity_id']);

$ranked = Bitmomo_Intent_Radar_Queue_V1::build([
    ['source' => 'x', 'payload' => [
        'id' => 'x-rank-low',
        'text' => 'BTC support today?',
        'author_ref' => 'rank-1',
        'created_at' => '2026-09-14T12:55:00Z',
    ]],
    ['source' => 'x', 'payload' => [
        'id' => 'x-rank-high',
        'text' => 'Anyone have a BTC signal today?',
        'author_ref' => 'rank-2',
        'created_at' => '2026-09-14T12:55:00Z',
    ]],
], $research, [], $now);
queue_check('review queue is deterministically sorted highest score first', count($ranked['review']) === 2 && $ranked['review'][0]['score'] >= $ranked['review'][1]['score'] && $ranked['review'][0]['source_ref'] === 'x-rank-high');

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " Intent Radar Queue V1 checks passed.\n";
