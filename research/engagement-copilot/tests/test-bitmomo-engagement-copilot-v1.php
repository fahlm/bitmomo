<?php

require __DIR__ . '/../includes/class-bitmomo-engagement-copilot-v1.php';
require __DIR__ . '/../includes/class-bitmomo-engagement-copilot-queue-v1.php';

$checks = [];
function engagement_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

$now = strtotime('2026-09-14T13:40:00Z');

function review_opportunity($id = 'bio-review-001', $source = 'x', $score = 92, $language = 'en', $intent = 'long_short') {
    return [
        'contract_version' => 'intent-opportunity-v1',
        'opportunity_id' => $id,
        'source' => $source,
        'source_ref' => $source . '-source-001',
        'source_url' => 'https://platform.example/' . $source . '-source-001',
        'text' => 'Third-party verbatim text should not become the response body.',
        'author' => ['ref' => 'third-party-author-secret'],
        'intent' => ['primary' => $intent],
        'score' => $score,
        'detected_language' => $language,
        'suggested_action' => 'review_for_reply',
        'approval_required' => true,
        'auto_outbound_permitted' => false,
        'research_links' => [[
            'research_id' => 'bmr-btc-live-001',
            'fingerprint' => 'sha256:btc-live-001',
            'match_score' => 90,
        ]],
    ];
}

function evidence_brief($brief_id = 'bcb-btc-long-short', $intent = 'long_short', $format = 'x_post', $research_id = 'bmr-btc-live-001') {
    return [
        'contract_version' => 'content-brief-v1',
        'compiler_version' => 'content-compiler-v1',
        'brief_id' => $brief_id,
        'status' => 'draft_only',
        'format' => $format,
        'source_research' => [
            'research_id' => $research_id,
            'fingerprint' => 'sha256:btc-live-001',
            'source_type' => 'btc_intelligence',
        ],
        'intent_context' => [
            'primary' => $intent,
            'search_keyword' => 'btc long or short',
        ],
        'evidence' => [
            'findings' => [
                ['type' => 'directional_bias', 'value' => 'Directional bias is bullish, while evidence remains conditional.'],
                ['type' => 'primary_driver', 'value' => 'Spot structure remains constructive.'],
            ],
            'metrics' => ['btc_reference_price' => 72123.45],
            'limitations' => [
                'Short-horizon market intelligence is not a guaranteed forecast or trading instruction.',
                'Opportunity describes relative activity and is not a directional signal.',
            ],
            'provenance' => [
                'source_record_id' => 'bitmomo-ai:1789390800',
                'as_of' => '2026-09-14T13:00:00Z',
                'producer' => 'Binance public market data',
                'methodology_version' => '1.4.2',
                'source_refs' => ['Binance public market data'],
            ],
        ],
        'human_review_required' => true,
        'auto_publish_permitted' => false,
    ];
}

$opportunity = review_opportunity();
$brief = evidence_brief();
$result = Bitmomo_Engagement_Copilot_V1::draft($opportunity, $brief, ['discovery_mode' => 'keyword_search'], $now);
engagement_check('valid review opportunity produces operator draft', $result['valid'] === true && is_array($result['draft']));
engagement_check('every engagement draft remains human-review-only', $result['draft']['status'] === 'human_review_only' && $result['draft']['approval_required'] === true);
engagement_check('auto-send is frozen off', $result['draft']['auto_send_permitted'] === false && $result['draft']['delivery_policy']['auto_send_permitted'] === false);
engagement_check('keyword-discovered X opportunity records unsolicited auto-reply prohibition', in_array('unsolicited_auto_reply_prohibited', $result['draft']['delivery_policy']['reasons'], true));
engagement_check('X AI reply approval requirement is explicit', in_array('x_ai_auto_reply_requires_platform_approval', $result['draft']['delivery_policy']['reasons'], true));
engagement_check('direct promotional link is disabled by default', $result['draft']['cta']['direct_link_permitted'] === false && $result['draft']['cta']['mode'] === 'none_by_default');
engagement_check('draft text includes upstream finding exactly', strpos($result['draft']['draft_text'], 'Directional bias is bullish, while evidence remains conditional.') !== false);
engagement_check('draft text includes material upstream caveat exactly', strpos($result['draft']['draft_text'], 'Short-horizon market intelligence is not a guaranteed forecast or trading instruction.') !== false);
engagement_check('evidence trace preserves research lineage', $result['draft']['evidence_trace']['research_id'] === 'bmr-btc-live-001' && $result['draft']['evidence_trace']['methodology_version'] === '1.4.2');
engagement_check('third-party author is not inserted into draft text', strpos($result['draft']['draft_text'], 'third-party-author-secret') === false);
engagement_check('source URL is not inserted into draft text', strpos($result['draft']['draft_text'], 'platform.example') === false);
engagement_check('verbatim discovery text is not inserted into draft text', strpos($result['draft']['draft_text'], 'Third-party verbatim text') === false);
engagement_check('draft contains no direct URL', strpos($result['draft']['draft_text'], 'http://') === false && strpos($result['draft']['draft_text'], 'https://') === false);
engagement_check('English long-short strategy is intelligence-first', strpos($result['draft']['draft_text'], 'Long or short is less useful') === 0);

$id_opportunity = review_opportunity('bio-review-id', 'x', 90, 'id', 'explicit_signal');
$id_result = Bitmomo_Engagement_Copilot_V1::draft($id_opportunity, $brief, ['discovery_mode' => 'keyword_search'], $now);
engagement_check('Indonesian draft uses Indonesian evidence-first opening', $id_result['valid'] === true && strpos($id_result['draft']['draft_text'], 'Kalau mencari sinyal BTC') === 0);
engagement_check('Indonesian draft still preserves exact evidence rather than translating claims', strpos($id_result['draft']['draft_text'], 'Directional bias is bullish, while evidence remains conditional.') !== false);

$ai_opportunity = review_opportunity('bio-review-ai', 'x', 88, 'en', 'ai_research');
$ai_result = Bitmomo_Engagement_Copilot_V1::draft($ai_opportunity, $brief, ['discovery_mode' => 'keyword_search'], $now);
engagement_check('AI-research response positions AI as testable research', strpos($ai_result['draft']['draft_text'], 'AI is more useful when treated as a testable model') === 0);
engagement_check('AI response does not invent win-rate language', stripos($ai_result['draft']['draft_text'], 'win rate') === false && stripos($ai_result['draft']['draft_text'], 'guaranteed') === false || strpos($ai_result['draft']['draft_text'], 'not a guaranteed forecast') !== false);

$opted_in = Bitmomo_Engagement_Copilot_V1::draft($opportunity, $brief, [
    'discovery_mode' => 'mention',
    'opted_in' => true,
], $now);
engagement_check('opted-in X interaction removes unsolicited-discovery reason', !in_array('unsolicited_auto_reply_prohibited', $opted_in['draft']['delivery_policy']['reasons'], true));
engagement_check('opted-in X interaction still cannot auto-send in V1', $opted_in['draft']['auto_send_permitted'] === false && in_array('x_ai_auto_reply_requires_platform_approval', $opted_in['draft']['delivery_policy']['reasons'], true));

$youtube_opportunity = review_opportunity('bio-youtube-review', 'youtube', 86, 'en', 'analysis_prediction');
$youtube_opportunity['source_ref'] = 'youtube-comment-001';
$youtube_result = Bitmomo_Engagement_Copilot_V1::draft($youtube_opportunity, $brief, ['discovery_mode' => 'comment'], $now);
engagement_check('YouTube draft remains manual review only', $youtube_result['valid'] === true && $youtube_result['draft']['delivery_policy']['delivery_class'] === 'human_review_only');
engagement_check('YouTube anti-spam policy reason is explicit', in_array('youtube_comment_spam_manual_review_required', $youtube_result['draft']['delivery_policy']['reasons'], true));

$monitor = review_opportunity('bio-monitor');
$monitor['suggested_action'] = 'monitor';
$monitor['approval_required'] = false;
$monitor_result = Bitmomo_Engagement_Copilot_V1::draft($monitor, $brief, [], $now);
engagement_check('monitor opportunity cannot become engagement draft', $monitor_result['valid'] === false && in_array('opportunity.suggested_action', $monitor_result['errors'], true));

$unsafe_auto = review_opportunity('bio-unsafe-auto');
$unsafe_auto['auto_outbound_permitted'] = true;
$unsafe_auto_result = Bitmomo_Engagement_Copilot_V1::draft($unsafe_auto, $brief, [], $now);
engagement_check('upstream opportunity claiming auto-outbound permission fails closed', $unsafe_auto_result['valid'] === false && in_array('opportunity.auto_outbound_permitted', $unsafe_auto_result['errors'], true));

$mismatch_brief = evidence_brief('bcb-mismatch', 'long_short', 'x_post', 'bmr-other-research');
$mismatch_result = Bitmomo_Engagement_Copilot_V1::draft($opportunity, $mismatch_brief, [], $now);
engagement_check('mismatched research lineage fails closed', $mismatch_result['valid'] === false && in_array('research_lineage', $mismatch_result['errors'], true));

$no_limits = evidence_brief('bcb-no-limit');
$no_limits['evidence']['limitations'] = [];
$no_limits_result = Bitmomo_Engagement_Copilot_V1::draft($opportunity, $no_limits, [], $now);
engagement_check('content brief without material caveat fails closed', $no_limits_result['valid'] === false && in_array('content_brief.limitations', $no_limits_result['errors'], true));

$bad_brief = evidence_brief('bcb-auto-publish');
$bad_brief['auto_publish_permitted'] = true;
$bad_brief_result = Bitmomo_Engagement_Copilot_V1::draft($opportunity, $bad_brief, [], $now);
engagement_check('auto-publishable content brief is rejected for engagement drafting', $bad_brief_result['valid'] === false && in_array('content_brief.auto_publish_permitted', $bad_brief_result['errors'], true));

$result_again = Bitmomo_Engagement_Copilot_V1::draft($opportunity, $brief, ['discovery_mode' => 'keyword_search'], $now);
engagement_check('identical inputs produce stable draft id', $result_again['draft']['draft_id'] === $result['draft']['draft_id']);
engagement_check('identical inputs produce stable fingerprint', $result_again['draft']['fingerprint'] === $result['draft']['fingerprint']);
$dedupe = Bitmomo_Engagement_Copilot_V1::deduplicate([$result['draft'], $result_again['draft'], $youtube_result['draft']]);
engagement_check('draft dedupe retains unique drafts only', count($dedupe['drafts']) === 2 && count($dedupe['duplicates']) === 1);

$brief_long = evidence_brief('bcb-long', 'long_short', 'x_post');
$brief_generic = evidence_brief('bcb-generic', null, 'research_article');
$brief_generic['intent_context']['primary'] = null;
$brief_youtube = evidence_brief('bcb-youtube', 'analysis_prediction', 'youtube_search');
$content_briefs = [$brief_generic, $brief_long, $brief_youtube];

$op_high = review_opportunity('bio-queue-high', 'x', 95, 'en', 'long_short');
$op_high['source_ref'] = 'x-queue-high';
$op_low = review_opportunity('bio-queue-low', 'youtube', 80, 'en', 'analysis_prediction');
$op_low['source_ref'] = 'youtube-queue-low';
$op_duplicate = $op_high;
$op_monitor = $monitor;

$queue = Bitmomo_Engagement_Copilot_Queue_V1::build(
    [$op_low, $op_high, $op_duplicate, $op_monitor],
    $content_briefs,
    [
        'bio-queue-high' => ['discovery_mode' => 'keyword_search'],
        'bio-queue-low' => ['discovery_mode' => 'comment'],
    ],
    $now
);
engagement_check('approval queue is versioned', $queue['queue_version'] === 'engagement-approval-queue-v1' && $queue['copilot_version'] === 'engagement-copilot-v1');
engagement_check('queue rejects non-review opportunity rather than drafting it', $queue['counts']['rejected'] === 1);
engagement_check('queue diagnoses duplicate drafts deterministically', $queue['counts']['duplicates'] === 1);
engagement_check('queue keeps two unique review drafts', $queue['counts']['review_drafts'] === 2);
engagement_check('queue ranks highest intent score first', $queue['review_drafts'][0]['source_context']['score'] === 95 && $queue['review_drafts'][0]['source_context']['source_ref'] === 'x-queue-high');
engagement_check('queue selects intent-matched X content brief', $queue['review_drafts'][0]['evidence_trace']['research_id'] === 'bmr-btc-live-001' && $queue['review_drafts'][0]['strategy']['angle'] === 'direction_with_invalidation');
engagement_check('all queued drafts still require human approval', count(array_filter($queue['review_drafts'], function ($draft) { return empty($draft['approval_required']) || !empty($draft['auto_send_permitted']); })) === 0);

$no_brief_op = review_opportunity('bio-no-brief');
$no_brief_op['research_links'][0]['research_id'] = 'bmr-not-indexed';
$missing_queue = Bitmomo_Engagement_Copilot_Queue_V1::build([$no_brief_op], $content_briefs, [], $now);
engagement_check('queue rejects opportunity without linked evidence brief', $missing_queue['counts']['review_drafts'] === 0 && $missing_queue['counts']['rejected'] === 1 && in_array('content_brief_not_found', $missing_queue['rejected'][0]['errors'], true));

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " Engagement Copilot V1 checks passed.\n";
