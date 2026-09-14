<?php

require_once __DIR__ . '/../includes/class-bitmomo-live-ingestion-v1.php';
require_once __DIR__ . '/../../intent-radar/includes/class-bitmomo-intent-radar-v1.php';
require_once __DIR__ . '/../../intent-radar/includes/class-bitmomo-intent-radar-queue-v1.php';
require_once __DIR__ . '/../../content-compiler/includes/class-bitmomo-content-compiler-v1.php';
require_once __DIR__ . '/../../engagement-copilot/includes/class-bitmomo-engagement-copilot-v1.php';
require_once __DIR__ . '/../../engagement-copilot/includes/class-bitmomo-engagement-copilot-queue-v1.php';
require_once __DIR__ . '/../../growth-attribution/includes/class-bitmomo-growth-attribution-v1.php';
require_once __DIR__ . '/../../acquisition-orchestrator/includes/class-bitmomo-acquisition-orchestrator-v1.php';

$checks = 0;
$failures = 0;
function ingestion_ok($condition, $label) {
    global $checks, $failures;
    $checks++;
    if ($condition) echo "PASS: {$label}\n";
    else { echo "FAIL: {$label}\n"; $failures++; }
}

$now = strtotime('2026-09-14T14:00:00Z');
$x_secret = 'X_SUPER_SECRET_BEARER_123456789';
$yt_secret = 'YT_SUPER_SECRET_KEY_987654321';
$requests = [];

$http = function (array $request) use (&$requests, $x_secret, $yt_secret) {
    $requests[] = $request;
    $url = $request['url'] ?? '';
    $query = $request['query'] ?? [];

    if ($url === Bitmomo_Live_Ingestion_V1::X_ENDPOINT) {
        $auth = $request['headers']['Authorization'] ?? '';
        if ($auth !== 'Bearer ' . $x_secret) return ['status' => 401, 'json' => ['error' => 'bad token']];
        $q = (string) ($query['query'] ?? '');

        if (strpos($q, 'analysis today') !== false) {
            return ['status' => 503, 'json' => ['error' => 'fixture outage']];
        }

        if (strpos($q, 'support OR resistance') !== false) {
            return ['status' => 200, 'json' => ['data' => [
                [
                    'id' => '2000000000000000002',
                    'text' => 'Why BTC dumping today? What changed in the market?',
                    'created_at' => '2026-09-14T13:52:00Z',
                    'author_id' => '998877665544332211',
                    'lang' => 'en',
                ],
                [
                    'id' => '2000000000000000001',
                    'text' => 'BTC long or short today? Which side looks stronger right now?',
                    'created_at' => '2026-09-14T13:50:00Z',
                    'author_id' => '112233445566778899',
                    'lang' => 'en',
                ],
            ]]];
        }

        return ['status' => 200, 'json' => ['data' => [
            [
                'id' => '2000000000000000001',
                'text' => 'BTC long or short today? Which side looks stronger right now?',
                'created_at' => '2026-09-14T13:50:00Z',
                'author_id' => '112233445566778899',
                'lang' => 'en',
            ],
            [
                'id' => '2000000000000000000',
                'text' => 'Bitcoin is amazing today.',
                'created_at' => '2026-09-14T13:49:00Z',
                'author_id' => '223344556677889900',
                'lang' => 'en',
            ],
        ]]];
    }

    if ($url === Bitmomo_Live_Ingestion_V1::YOUTUBE_ENDPOINT) {
        if (($query['key'] ?? '') !== $yt_secret) return ['status' => 403, 'json' => ['error' => 'bad key']];
        $q = (string) ($query['q'] ?? '');
        if ($q === 'bitcoin analysis today') {
            return ['status' => 200, 'json' => ['items' => [[
                'id' => ['videoId' => 'yt-live-001'],
                'snippet' => [
                    'title' => 'BTC Long or Short Today? Bitcoin Analysis',
                    'description' => 'What traders are searching for today.',
                    'publishedAt' => '2026-09-14T13:40:00Z',
                    'channelId' => 'UC-secret-channel-001',
                ],
            ]]]];
        }
        if ($q === 'ai bitcoin research') {
            return ['status' => 200, 'json' => ['items' => [[
                'id' => ['videoId' => 'yt-live-002'],
                'snippet' => [
                    'title' => 'AI Bitcoin Research: Can Models Read BTC?',
                    'description' => 'A testable research question about AI and Bitcoin.',
                    'publishedAt' => '2026-09-14T13:42:00Z',
                    'channelId' => 'UC-secret-channel-002',
                ],
            ]]]];
        }
        // Duplicate result across the remaining queries to test source/id dedupe.
        return ['status' => 200, 'json' => ['items' => [[
            'id' => ['videoId' => 'yt-live-001'],
            'snippet' => [
                'title' => 'BTC Long or Short Today? Bitcoin Analysis',
                'description' => 'What traders are searching for today.',
                'publishedAt' => '2026-09-14T13:40:00Z',
                'channelId' => 'UC-secret-channel-001',
            ],
        ]]]];
    }

    return ['status' => 404, 'json' => ['error' => 'unexpected endpoint']];
};

$credentials = ['x_bearer_token' => $x_secret, 'youtube_api_key' => $yt_secret];
$batch = Bitmomo_Live_Ingestion_V1::discover($credentials, $http, [], [], $now);

ingestion_ok($batch['batch_version'] === 'live-ingestion-batch-v1', 'live ingestion batch is versioned');
ingestion_ok($batch['mode'] === 'read_only', 'batch is explicitly read-only');
ingestion_ok($batch['safety']['write_endpoints_present'] === false, 'module exposes no write endpoints');
ingestion_ok($batch['safety']['auto_send_permitted'] === false, 'auto-send remains forbidden');
ingestion_ok($batch['safety']['auto_publish_permitted'] === false, 'auto-publish remains forbidden');
ingestion_ok($batch['budget']['x_max_clusters_per_run'] === 3, 'X defaults to maximum three query clusters');
ingestion_ok($batch['budget']['x_max_results_per_cluster'] === 10, 'X defaults to ten results per cluster');
ingestion_ok($batch['budget']['x_max_posts_requested_per_run'] === 30, 'X theoretical request volume is capped at 30 posts per run');
ingestion_ok(abs($batch['budget']['x_theoretical_max_read_cost_usd'] - 0.15) < 0.000001, 'X theoretical read cost ceiling is explicit at current official unit price');
ingestion_ok($batch['budget']['youtube_max_search_calls_per_run'] === 4, 'YouTube defaults to four search calls per run');
ingestion_ok($batch['budget']['youtube_max_results_per_call'] === 10, 'YouTube results are capped at ten per search call');
ingestion_ok($batch['usage']['x_requests'] === 3, 'exactly three X recent-search requests are attempted');
ingestion_ok($batch['usage']['youtube_search_calls'] === 4, 'exactly four YouTube search calls are attempted');
ingestion_ok($batch['counts']['source_errors'] === 1, 'single X cluster failure is isolated as source error');
ingestion_ok($batch['counts']['duplicates'] >= 2, 'duplicate public resources are diagnosed before P5 handoff');
ingestion_ok($batch['counts']['observations'] >= 5, 'valid observations from both platforms survive partial failure and dedupe');

$serialized = json_encode($batch, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
ingestion_ok(strpos($serialized, $x_secret) === false, 'X bearer token never appears in batch output');
ingestion_ok(strpos($serialized, $yt_secret) === false, 'YouTube API key never appears in batch output');
ingestion_ok(strpos($serialized, '112233445566778899') === false, 'raw X author id is hashed before output');
ingestion_ok(strpos($serialized, 'UC-secret-channel-001') === false, 'raw YouTube channel id is hashed before output');

$x_requests = array_values(array_filter($requests, function ($r) { return ($r['url'] ?? '') === Bitmomo_Live_Ingestion_V1::X_ENDPOINT; }));
$yt_requests = array_values(array_filter($requests, function ($r) { return ($r['url'] ?? '') === Bitmomo_Live_Ingestion_V1::YOUTUBE_ENDPOINT; }));
ingestion_ok(count($x_requests) === 3, 'all X calls target only official recent-search endpoint');
ingestion_ok(count($yt_requests) === 4, 'all YouTube calls target only official search endpoint');
$all_x_capped = true;
foreach ($x_requests as $request) if (($request['query']['max_results'] ?? 999) > 10) $all_x_capped = false;
ingestion_ok($all_x_capped, 'every X request enforces result cap');
$all_yt_capped = true;
foreach ($yt_requests as $request) if (($request['query']['maxResults'] ?? 999) > 10) $all_yt_capped = false;
ingestion_ok($all_yt_capped, 'every YouTube request enforces result cap');

$state_x = $batch['state_hints']['x_since_id'];
ingestion_ok($state_x === '2000000000000000002', 'X state hint advances to highest observed post id');
ingestion_ok($batch['state_hints']['youtube_published_after'] === '2026-09-14T13:42:00Z', 'YouTube state hint advances to latest publication timestamp');

$requests2 = [];
$http2 = function (array $request) use (&$requests2, $http) {
    $requests2[] = $request;
    return $http($request);
};
$state = ['x_since_id' => '1999999999999999999', 'youtube_published_after' => '2026-09-14T13:00:00Z'];
Bitmomo_Live_Ingestion_V1::discover($credentials, $http2, $state, ['youtube_region' => 'ID', 'youtube_language' => 'id'], $now);
$since_sent = true;
$yt_state_sent = true;
foreach ($requests2 as $request) {
    if (($request['url'] ?? '') === Bitmomo_Live_Ingestion_V1::X_ENDPOINT && ($request['query']['since_id'] ?? '') !== $state['x_since_id']) $since_sent = false;
    if (($request['url'] ?? '') === Bitmomo_Live_Ingestion_V1::YOUTUBE_ENDPOINT) {
        if (($request['query']['publishedAfter'] ?? '') !== $state['youtube_published_after']) $yt_state_sent = false;
        if (($request['query']['regionCode'] ?? '') !== 'ID') $yt_state_sent = false;
        if (($request['query']['relevanceLanguage'] ?? '') !== 'id') $yt_state_sent = false;
    }
}
ingestion_ok($since_sent, 'caller-supplied X since_id is propagated to all recent-search clusters');
ingestion_ok($yt_state_sent, 'YouTube checkpoint/region/language controls are propagated safely');

$only_youtube = Bitmomo_Live_Ingestion_V1::discover(['youtube_api_key' => $yt_secret], $http, [], ['youtube_max_calls' => 1], $now);
ingestion_ok($only_youtube['counts']['source_errors'] === 1, 'missing X credentials are isolated without blocking YouTube');
ingestion_ok($only_youtube['usage']['youtube_search_calls'] === 1, 'YouTube continues when X credentials are absent');

$only_x = Bitmomo_Live_Ingestion_V1::discover(['x_bearer_token' => $x_secret], $http, [], ['x_max_clusters' => 1], $now);
ingestion_ok($only_x['counts']['source_errors'] === 1, 'missing YouTube credentials are isolated without blocking X');
ingestion_ok($only_x['usage']['x_requests'] === 1, 'X continues when YouTube credentials are absent');

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
    'tags' => ['btc', 'scenario', 'bias', 'ai', 'research'],
    'provenance' => [
        'source_record_id' => 'bitmomo-ai:1789390800',
        'as_of' => '2026-09-14T13:00:00Z',
        'producer' => 'Binance public market data',
        'methodology_version' => '1.4.2',
        'source_refs' => ['Binance public market data'],
    ],
    'distribution' => ['eligible' => true, 'reasons' => []],
]];

$handoff = Bitmomo_Live_Ingestion_V1::run_acquisition($credentials, $http, $research, [], [], [], [], [], $now);
$run = $handoff['acquisition_run'];
ingestion_ok($run['run_version'] === 'acquisition-run-v1', 'live-ingestion batch hands directly into canonical P5 contract');
ingestion_ok($run['safety']['auto_send_permitted'] === false, 'P5 auto-send safety remains frozen after live-ingestion handoff');
ingestion_ok($run['safety']['auto_publish_permitted'] === false, 'P5 auto-publish safety remains frozen after live-ingestion handoff');
ingestion_ok($run['counts']['intent_review'] >= 1, 'live-shaped high-intent X observation reaches human review through P5');
ingestion_ok($run['counts']['intent_monitor'] >= 1, 'live-shaped YouTube search demand reaches monitor/owned-content path');
ingestion_ok($run['counts']['engagement_drafts'] >= 1, 'evidence-grounded operator draft is produced from live-shaped input');
ingestion_ok($run['counts']['content_briefs'] === 5, 'live-shaped demand compiles five native owned-content briefs from eligible research');

$handoff_serialized = json_encode($handoff, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
ingestion_ok(strpos($handoff_serialized, $x_secret) === false && strpos($handoff_serialized, $yt_secret) === false, 'secrets remain absent after full P6 to P5 handoff');

if ($failures) exit(1);
echo "All {$checks} Live Ingestion V1 checks passed.\n";
