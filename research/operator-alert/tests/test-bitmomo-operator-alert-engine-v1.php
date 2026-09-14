<?php

require_once __DIR__ . '/../../intent-radar/includes/class-bitmomo-intent-radar-v1.php';
require_once __DIR__ . '/../../intent-radar/includes/class-bitmomo-intent-radar-queue-v1.php';
require_once __DIR__ . '/../../content-compiler/includes/class-bitmomo-content-compiler-v1.php';
require_once __DIR__ . '/../../engagement-copilot/includes/class-bitmomo-engagement-copilot-v1.php';
require_once __DIR__ . '/../../engagement-copilot/includes/class-bitmomo-engagement-copilot-queue-v1.php';
require_once __DIR__ . '/../../growth-attribution/includes/class-bitmomo-growth-attribution-v1.php';
require_once __DIR__ . '/../../growth-attribution/includes/class-bitmomo-growth-attribution-report-v1.php';
require_once __DIR__ . '/../../acquisition-orchestrator/includes/class-bitmomo-acquisition-orchestrator-v1.php';
require_once __DIR__ . '/../includes/class-bitmomo-indonesia-discovery-v1.php';
require_once __DIR__ . '/../includes/class-bitmomo-operator-alert-engine-v1.php';

$checks = [];
function oa_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }
$now = strtotime('2026-09-14T15:00:00Z');

function oa_research() {
    return [[
        'contract_version' => 'research-feed-v1',
        'research_id' => 'bmr-btc-live-id-001',
        'fingerprint' => 'sha256:btc-live-id-001',
        'source_type' => 'btc_intelligence',
        'title' => 'BTC Intelligence Indonesia Test',
        'summary' => 'Canonical BTC market intelligence for evidence-grounded distribution.',
        'findings' => [
            ['type' => 'directional_bias', 'value' => 'BTC bias remains neutral until structure confirms direction.'],
            ['type' => 'primary_driver', 'value' => 'Structure confirmation matters more than the first liquidity sweep.'],
        ],
        'metrics' => ['btc_reference_price' => 77500, 'directional_bias' => 'neutral'],
        'limitations' => ['Short-horizon market intelligence; not a guaranteed forecast or trading instruction.'],
        'topics' => ['bitcoin', 'btc', 'market-intelligence', 'direction', 'scenario'],
        'tags' => ['btc', 'bitcoin', 'direction'],
        'provenance' => [
            'source_record_id' => 'bitmomo-ai:test-id',
            'as_of' => '2026-09-14T14:55:00Z',
            'producer' => 'bitmomo-research',
            'methodology_version' => 'test-v1',
            'source_refs' => ['canonical test fixture'],
        ],
        'distribution' => ['eligible' => true, 'reasons' => []],
    ]];
}

function oa_fake_http_factory($now, &$requests) {
    return function ($request) use ($now, &$requests) {
        $requests[] = $request;
        $url = (string) ($request['url'] ?? '');
        if (strpos($url, 'api.x.com') !== false) {
            return [
                'status' => 200,
                'json' => ['data' => [
                    [
                        'id' => '2000000000000000003',
                        'text' => 'Butuh sinyal BTC sekarang. Long atau short? Analisis hari ini gimana?',
                        'created_at' => gmdate('c', $now - 300),
                        'author_id' => 'raw-author-fresh',
                        'lang' => 'id',
                        'public_metrics' => ['like_count' => 8, 'reply_count' => 2, 'retweet_count' => 1, 'quote_count' => 0],
                    ],
                    [
                        'id' => '2000000000000000002',
                        'text' => 'BTC naik atau turun sekarang? Lagi cari analisis BTC hari ini.',
                        'created_at' => gmdate('c', $now - 2700),
                        'author_id' => 'raw-author-45m',
                        'lang' => 'id',
                        'public_metrics' => ['like_count' => 2, 'reply_count' => 1, 'retweet_count' => 0, 'quote_count' => 0],
                    ],
                    [
                        'id' => '2000000000000000001',
                        'text' => 'BTC signal right now, long or short?',
                        'created_at' => gmdate('c', $now - 180),
                        'author_id' => 'raw-author-global',
                        'lang' => 'en',
                        'public_metrics' => ['like_count' => 100, 'reply_count' => 20, 'retweet_count' => 8, 'quote_count' => 2],
                    ],
                ]],
            ];
        }
        if (strpos($url, 'googleapis.com') !== false) {
            return [
                'status' => 200,
                'json' => ['items' => [[
                    'id' => ['videoId' => 'yt-id-001'],
                    'snippet' => [
                        'title' => 'Analisis Bitcoin Hari Ini: BTC Naik atau Turun?',
                        'description' => 'Pembahasan arah BTC hari ini untuk trader Indonesia.',
                        'publishedAt' => gmdate('c', $now - 900),
                        'channelId' => 'raw-channel-id',
                    ],
                ]]],
            ];
        }
        return ['status' => 404, 'json' => []];
    };
}

$requests = [];
$http = oa_fake_http_factory($now, $requests);
$result = Bitmomo_Operator_Alert_Engine_V1::run(
    ['x_bearer_token' => 'SECRET_X_TOKEN', 'youtube_api_key' => 'SECRET_YT_KEY'],
    $http,
    oa_research(),
    [], [], [], $now
);

oa_check('engine is operator-assisted manual posting mode', ($result['mode'] ?? '') === 'operator_assisted_manual_posting');
oa_check('auto-send remains off', empty($result['safety']['auto_send_permitted']));
oa_check('auto-publish remains off', empty($result['safety']['auto_publish_permitted']));
oa_check('audience percentage claims remain forbidden', empty($result['safety']['audience_percentage_claim_permitted']));
oa_check('Indonesia discovery uses one X request per poll', (int) ($result['discovery']['usage']['x_requests'] ?? 0) === 1);
oa_check('X query explicitly uses lang:id', strpos((string) ($requests[0]['query']['query'] ?? ''), 'lang:id') !== false);
oa_check('X request has a one-hour hard start window', !empty($requests[0]['query']['start_time']));
oa_check('X request asks for public metrics for ranking', strpos((string) ($requests[0]['query']['tweet.fields'] ?? ''), 'public_metrics') !== false);
oa_check('YouTube requests are Indonesia-region targeted', count(array_filter($requests, function ($r) { return (($r['query']['regionCode'] ?? '') === 'ID'); })) === 2);
oa_check('YouTube requests prefer Indonesian relevance language', count(array_filter($requests, function ($r) { return (($r['query']['relevanceLanguage'] ?? '') === 'id'); })) === 2);
oa_check('English/global X result is suppressed during Indonesia discovery', count(array_filter((array) ($result['discovery']['observations'] ?? []), function ($row) { return (($row['payload']['id'] ?? '') === '2000000000000000001'); })) === 0);
oa_check('fresh Indonesian high-intent tweet creates exactly one reply alert', (int) ($result['counts']['reply_alerts'] ?? 0) === 1);
oa_check('45-minute Indonesian opportunity is not a reply alert', count(array_filter((array) ($result['alerts'] ?? []), function ($a) { return (($a['source_ref'] ?? '') === '2000000000000000002'); })) === 0);
oa_check('45-minute Indonesian opportunity remains monitor-only', count(array_filter((array) ($result['monitor_only'] ?? []), function ($a) { return (($a['source_url'] ?? '') === 'https://x.com/i/web/status/2000000000000000002'); })) >= 1);

$alert = $result['alerts'][0] ?? [];
oa_check('operator alert carries exact tweet URL', ($alert['source_url'] ?? '') === 'https://x.com/i/web/status/2000000000000000003');
oa_check('operator alert carries source text', strpos((string) ($alert['source_text'] ?? ''), 'Butuh sinyal BTC') !== false);
oa_check('operator alert reports age in minutes', abs(((float) ($alert['age_minutes'] ?? 0)) - 5.0) < 0.1);
oa_check('operator alert has Indonesia targeting confidence rather than percentage', ($alert['indonesia_target']['confidence_band'] ?? '') === 'high' && ($alert['indonesia_target']['audience_percentage'] ?? 'not-null') === null);
oa_check('operator alert contains ready-to-post reply', trim((string) ($alert['ready_to_post'] ?? '')) !== '');
oa_check('operator alert contains research evidence lineage', ($alert['research_evidence']['research_id'] ?? '') === 'bmr-btc-live-id-001');
oa_check('operator alert contains material caveat', trim((string) ($alert['research_evidence']['limitation'] ?? '')) !== '');
oa_check('operator alert expires at 30 minutes from source timestamp', strtotime((string) ($alert['expires_at'] ?? '')) === ($now - 300 + 1800));
oa_check('manual click remains required', !empty($alert['manual_post_required']));
oa_check('social auto-send remains false on alert', empty($alert['auto_send_permitted']));
oa_check('notification includes tweet URL and ready-to-post section', strpos(Bitmomo_Operator_Alert_Engine_V1::format_notification($alert), 'https://x.com/i/web/status/2000000000000000003') !== false && strpos(Bitmomo_Operator_Alert_Engine_V1::format_notification($alert), 'READY TO POST') !== false);

$sent_messages = [];
$dispatch = Bitmomo_Operator_Alert_Engine_V1::dispatch([$alert], function ($message, $meta) use (&$sent_messages) { $sent_messages[] = [$message, $meta]; return true; });
oa_check('dispatcher sends operator notification via injected notifier', count($dispatch['sent'] ?? []) === 1 && count($sent_messages) === 1);
oa_check('notification contains no discovery credentials', strpos($sent_messages[0][0] ?? '', 'SECRET_X_TOKEN') === false && strpos($sent_messages[0][0] ?? '', 'SECRET_YT_KEY') === false);
oa_check('notification contains no raw X author id', strpos($sent_messages[0][0] ?? '', 'raw-author-fresh') === false);

$repeat = Bitmomo_Operator_Alert_Engine_V1::run(
    ['x_bearer_token' => 'SECRET_X_TOKEN', 'youtube_api_key' => 'SECRET_YT_KEY'],
    oa_fake_http_factory($now, $requests2 = []),
    oa_research(),
    ['alerted_ids' => [$alert['alert_id'] ?? '']], [], [], $now
);
oa_check('already-alerted opportunity does not alert twice', (int) ($repeat['counts']['reply_alerts'] ?? -1) === 0 && (int) ($repeat['counts']['duplicates'] ?? 0) >= 1);

// Explicit regression: a 12-hour-old opportunity must never be elevated even
// if upstream P1 had hypothetically classified it as review.
$old_opp = $result['acquisition_run']['intent_queue']['review'][0] ?? [];
$old_opp['observed_at'] = gmdate('c', $now - 43200);
$old_opp['created_at'] = gmdate('c', $now);
$old_draft = $result['acquisition_run']['engagement_queue']['review_drafts'][0] ?? [];
$synthetic_acq = $result['acquisition_run'];
$synthetic_acq['intent_queue']['review'] = [$old_opp];
$synthetic_acq['intent_queue']['monitor'] = [];
$synthetic_acq['engagement_queue']['review_drafts'] = [$old_draft];
$old_gate = Bitmomo_Operator_Alert_Engine_V1::build_alerts($synthetic_acq, $result['discovery'], [], $now);
oa_check('12-hour-old tweet can never become operator reply alert', count($old_gate['alerts']) === 0 && count(array_filter($old_gate['suppressed'], function ($d) { return (($d['reason'] ?? '') === 'expired_over_60m'); })) === 1);

$failed = 0;
foreach ($checks as [$label, $ok]) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    if (!$ok) $failed++;
}
if ($failed) exit(1);
echo 'All ' . count($checks) . " Operator Alert Engine V1 checks passed.\n";
