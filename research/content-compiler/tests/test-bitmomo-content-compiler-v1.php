<?php

require __DIR__ . '/../includes/class-bitmomo-content-compiler-v1.php';

$checks = [];
function compiler_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

$now = strtotime('2026-09-14T13:30:00Z');

function eligible_manual_research() {
    return [
        'contract_version' => 'research-feed-v1',
        'research_id' => 'bmr-funding-extremes-v1',
        'fingerprint' => 'sha256:funding-research-v1',
        'source_type' => 'manual_research',
        'title' => 'Do Funding Extremes Predict BTC Reversals?',
        'summary' => 'A point-in-time study of BTC funding extremes and subsequent price behavior.',
        'findings' => [
            ['type' => 'result', 'value' => 'Extreme funding alone was not sufficient to classify direction.'],
            ['type' => 'result', 'value' => 'Context materially changed the observed outcome distribution.'],
        ],
        'metrics' => [
            'sample_n' => 842,
            'observation_window_days' => 180,
        ],
        'limitations' => [
            'Historical relationships may change across market regimes.',
            'Funding alone should not be treated as a directional signal.',
        ],
        'topics' => ['bitcoin', 'derivatives', 'research'],
        'tags' => ['funding', 'research'],
        'provenance' => [
            'source_record_id' => 'manual-research:funding-extremes:v1:1789322400',
            'as_of' => '2026-09-13T18:00:00Z',
            'producer' => 'bitmomo-research',
            'methodology_version' => 'funding-reversal-study-v1',
            'source_refs' => ['Binance USD-M historical funding', 'Binance BTCUSDT candles'],
        ],
        'distribution' => ['eligible' => true, 'reasons' => []],
    ];
}

function eligible_btc_research() {
    return [
        'contract_version' => 'research-feed-v1',
        'research_id' => 'bmr-btc-live-001',
        'fingerprint' => 'sha256:btc-live-001',
        'source_type' => 'btc_intelligence',
        'title' => 'BTC Intelligence — 2026-09-14T13:00Z',
        'summary' => 'Canonical BTC intelligence snapshot for downstream research distribution tooling.',
        'findings' => [
            ['type' => 'directional_bias', 'value' => 'bullish'],
            ['type' => 'primary_driver', 'value' => 'Spot structure remains constructive.'],
        ],
        'metrics' => [
            'btc_reference_price' => 72123.45,
            'directional_bias' => 'bullish',
            'opportunity_state' => 'high',
        ],
        'limitations' => [
            'Short-horizon market intelligence; not a guaranteed forecast or trading instruction.',
            'Opportunity describes relative activity and is not a directional signal.',
        ],
        'topics' => ['bitcoin', 'market-intelligence'],
        'tags' => ['btc', 'intraday'],
        'provenance' => [
            'source_record_id' => 'bitmomo-ai:1789390800',
            'as_of' => '2026-09-14T13:00:00Z',
            'producer' => 'Binance public market data',
            'methodology_version' => '1.4.2',
            'source_refs' => ['Binance public market data'],
        ],
        'distribution' => ['eligible' => true, 'reasons' => []],
    ];
}

function long_short_intent($research_id = 'bmr-btc-live-001', $score = 92) {
    return [
        'opportunity_id' => 'bio-user-source-001',
        'source' => 'x',
        'source_ref' => 'x-private-ref-123',
        'source_url' => 'https://x.example/x-private-ref-123',
        'text' => 'I need a BTC long or short signal right now — please tell me.',
        'author' => ['ref' => 'third-party-handle-secret'],
        'intent' => [
            'primary' => 'long_short',
            'matches' => ['long_short' => ['long or short']],
        ],
        'matched_terms' => ['long or short', 'btc'],
        'score' => $score,
        'detected_language' => 'en',
        'research_links' => [[
            'research_id' => $research_id,
            'fingerprint' => 'sha256:linked-research',
            'title' => 'BTC Intelligence',
            'match_score' => 90,
            'matched_terms' => ['btc'],
        ]],
    ];
}

$manual = eligible_manual_research();
$btc = eligible_btc_research();
$intent = long_short_intent();

$x = Bitmomo_Content_Compiler_V1::compile($btc, $intent, 'x_post', $now);
compiler_check('eligible research compiles into a content brief', $x['valid'] === true && is_array($x['brief']));
compiler_check('compiled brief is draft-only and never auto-publishable', $x['brief']['status'] === 'draft_only' && $x['brief']['human_review_required'] === true && $x['brief']['auto_publish_permitted'] === false);
compiler_check('long-short intent changes framing to native intent capture title', $x['brief']['framing']['title_candidates'][0] === 'BTC Long or Short? What the Data Says Now');
compiler_check('X post uses native X structure', $x['brief']['channel'] === 'x' && $x['brief']['framing']['structure'] === ['hook', 'one_evidence_point', 'implication', 'limitation', 'cta']);
compiler_check('owned whitelist CTA is deterministic', $x['brief']['cta']['type'] === 'founding_member_whitelist' && $x['brief']['cta']['destination_key'] === 'bitmomo_founding_whitelist');
compiler_check('UTM attribution is deterministic and channel-specific', $x['brief']['attribution']['utm_source'] === 'x' && $x['brief']['attribution']['utm_medium'] === 'social' && $x['brief']['attribution']['utm_content'] === 'x_post-long_short');

$encoded_x = json_encode($x['brief']);
compiler_check('third-party author identifier is excluded from compiled brief', strpos($encoded_x, 'third-party-handle-secret') === false);
compiler_check('third-party source reference is excluded from compiled brief', strpos($encoded_x, 'x-private-ref-123') === false);
compiler_check('verbatim third-party observation text is excluded from compiled brief', strpos($encoded_x, 'I need a BTC long or short signal right now') === false);
compiler_check('safe aggregate intent survives privacy sanitization', $x['brief']['intent_context']['primary'] === 'long_short' && $x['brief']['intent_context']['search_keyword'] === 'btc long or short');

$original_findings = array_column($btc['findings'], 'value');
$compiled_findings = array_column($x['brief']['evidence']['findings'], 'value');
compiler_check('research findings are preserved without rewriting', $compiled_findings === $original_findings);
compiler_check('research limitations are preserved in evidence lock', $x['brief']['evidence']['limitations'] === $btc['limitations']);
compiler_check('research provenance is preserved in evidence lock', $x['brief']['evidence']['provenance']['source_record_id'] === $btc['provenance']['source_record_id'] && $x['brief']['evidence']['provenance']['methodology_version'] === '1.4.2');
compiler_check('Opportunity remains forbidden as a directional claim', in_array('opportunity_as_direction', $x['brief']['claim_policy']['forbidden'], true));
compiler_check('compiler forbids invented statistics and unsupported win rates', in_array('invented_statistics', $x['brief']['claim_policy']['forbidden'], true) && in_array('unsupported_win_rate', $x['brief']['claim_policy']['forbidden'], true));

$youtube = Bitmomo_Content_Compiler_V1::compile($btc, $intent, 'youtube_search', $now);
compiler_check('YouTube Search brief is native to YouTube', $youtube['valid'] === true && $youtube['brief']['channel'] === 'youtube');
compiler_check('YouTube Search uses highest-value normalized intent keyword', $youtube['brief']['framing']['search_keyword'] === 'btc long or short');
compiler_check('YouTube Search structure includes query-match hook and limitations', $youtube['brief']['framing']['structure'][0] === 'query_match_hook' && in_array('limitations', $youtube['brief']['framing']['structure'], true));

$short = Bitmomo_Content_Compiler_V1::compile($btc, $intent, 'youtube_short', $now);
compiler_check('Shorts brief uses single-finding structure', $short['brief']['framing']['structure'] === ['first_2s_hook', 'single_finding', 'single_caveat', 'cta']);
compiler_check('Shorts title candidates remain compact', strlen($short['brief']['framing']['title_candidates'][0]) <= 70);

$thread = Bitmomo_Content_Compiler_V1::compile($btc, $intent, 'x_thread', $now);
compiler_check('X thread has evidence-sequenced native structure', $thread['brief']['framing']['structure'] === ['hook', 'research_question', 'evidence_1', 'evidence_2', 'implication', 'limitations', 'cta']);

$article = Bitmomo_Content_Compiler_V1::compile($manual, [], 'research_article', $now);
compiler_check('manual original research defaults to authority objective', $article['valid'] === true && $article['brief']['objective'] === 'authority');
compiler_check('authority research article exposes Bitmomo Tested It signature framing', $article['brief']['framing']['title_candidates'][0] === 'Bitmomo Tested It: Do Funding Extremes Predict BTC Reversals?');
compiler_check('research article ends in methodology/source structure rather than social CTA-first structure', end($article['brief']['framing']['structure']) === 'methodology_and_sources');

$ai_intent = long_short_intent($manual['research_id'], 88);
$ai_intent['intent']['primary'] = 'ai_research';
$ai_intent['matched_terms'] = ['ai bitcoin', 'ai research bitcoin'];
$ai_intent['detected_language'] = 'en';
$ai = Bitmomo_Content_Compiler_V1::compile($manual, $ai_intent, 'youtube_search', $now);
compiler_check('AI-research demand selects authority objective', $ai['brief']['objective'] === 'authority');
compiler_check('AI-research framing is evidence-oriented rather than performance claim', $ai['brief']['framing']['title_candidates'][0] === 'AI Bitcoin Research: What the Evidence Actually Shows');
compiler_check('AI-research keyword is normalized without unsupported performance language', $ai['brief']['intent_context']['search_keyword'] === 'ai bitcoin research' && strpos(json_encode($ai['brief']['framing']), 'win rate') === false);

$event_intent = long_short_intent($btc['research_id'], 82);
$event_intent['intent']['primary'] = 'event_reaction';
$event_intent['matched_terms'] = ['btc cpi'];
$event = Bitmomo_Content_Compiler_V1::compile($btc, $event_intent, 'x_post', $now);
compiler_check('event-reaction intent selects event-intelligence objective', $event['brief']['objective'] === 'event_intelligence');

$accountability = eligible_manual_research();
$accountability['research_id'] = 'bmr-forecast-ledger-sep';
$accountability['fingerprint'] = 'sha256:forecast-ledger-sep';
$accountability['tags'][] = 'forecast-ledger';
$accountability_result = Bitmomo_Content_Compiler_V1::compile($accountability, [], 'research_article', $now);
compiler_check('forecast-ledger research selects accountability objective', $accountability_result['brief']['objective'] === 'accountability');

$blocked = eligible_manual_research();
$blocked['distribution']['eligible'] = false;
$blocked_result = Bitmomo_Content_Compiler_V1::compile($blocked, [], 'x_post', $now);
compiler_check('distribution-ineligible research fails closed', $blocked_result['valid'] === false && in_array('distribution.eligible', $blocked_result['errors'], true));

$missing_limits = eligible_manual_research();
$missing_limits['limitations'] = [];
$missing_limits_result = Bitmomo_Content_Compiler_V1::compile($missing_limits, [], 'x_post', $now);
compiler_check('research without limitations fails closed', $missing_limits_result['valid'] === false && in_array('limitations', $missing_limits_result['errors'], true));

$missing_provenance = eligible_manual_research();
unset($missing_provenance['provenance']['methodology_version']);
$missing_provenance_result = Bitmomo_Content_Compiler_V1::compile($missing_provenance, [], 'x_post', $now);
compiler_check('research without methodology provenance fails closed', $missing_provenance_result['valid'] === false && in_array('provenance.methodology_version', $missing_provenance_result['errors'], true));

$missing_findings = eligible_manual_research();
$missing_findings['findings'] = [];
$missing_findings_result = Bitmomo_Content_Compiler_V1::compile($missing_findings, [], 'x_post', $now);
compiler_check('research without findings fails closed', $missing_findings_result['valid'] === false && in_array('findings', $missing_findings_result['errors'], true));

$bad_format = Bitmomo_Content_Compiler_V1::compile($manual, [], 'instagram_reel', $now);
compiler_check('unsupported format fails closed', $bad_format['valid'] === false && in_array('format', $bad_format['errors'], true));

$x_again = Bitmomo_Content_Compiler_V1::compile($btc, $intent, 'x_post', $now);
compiler_check('identical compile produces stable brief id', $x_again['brief']['brief_id'] === $x['brief']['brief_id']);
compiler_check('identical compile produces stable fingerprint', $x_again['brief']['fingerprint'] === $x['brief']['fingerprint']);
compiler_check('identical compile produces stable campaign attribution', $x_again['brief']['attribution'] === $x['brief']['attribution']);
compiler_check('different channel format produces a distinct brief id', $youtube['brief']['brief_id'] !== $x['brief']['brief_id']);

$dedupe = Bitmomo_Content_Compiler_V1::deduplicate([$x['brief'], $x_again['brief'], $youtube['brief']]);
compiler_check('brief dedupe retains only unique research-intent-format combinations', count($dedupe['briefs']) === 2);
compiler_check('brief dedupe emits duplicate diagnostics', count($dedupe['duplicates']) === 1 && $dedupe['duplicates'][0]['brief_id'] === $x['brief']['brief_id']);

$queue = [
    'review' => [
        [
            'score' => 91,
            'text' => 'third-party text must not propagate',
            'author' => ['ref' => 'private-author-ref'],
            'source_ref' => 'private-source-ref',
            'intent' => ['primary' => 'long_short'],
            'matched_terms' => ['long or short'],
            'detected_language' => 'en',
            'research_links' => [[
                'research_id' => $btc['research_id'],
                'fingerprint' => $btc['fingerprint'],
            ]],
        ],
    ],
    'monitor' => [
        [
            'score' => 70,
            'intent' => ['primary' => 'analysis_prediction'],
            'matched_terms' => ['btc analysis'],
            'detected_language' => 'en',
            'research_links' => [[
                'research_id' => $btc['research_id'],
                'fingerprint' => $btc['fingerprint'],
            ]],
        ],
    ],
    'ignored' => [
        [
            'score' => 99,
            'intent' => ['primary' => 'explicit_signal'],
            'matched_terms' => ['btc signal'],
            'research_links' => [[
                'research_id' => $manual['research_id'],
                'fingerprint' => $manual['fingerprint'],
            ]],
        ],
    ],
];

$campaign = Bitmomo_Content_Compiler_V1::compile_campaign([$btc, $manual], $queue, ['x_post', 'youtube_search', 'research_article'], $now);
compiler_check('campaign compiles all requested formats for eligible research', $campaign['counts']['briefs'] === 6 && $campaign['counts']['rejected'] === 0);
compiler_check('campaign uses highest-scoring review/monitor intent linked to research', array_values(array_filter($campaign['briefs'], function ($brief) use ($btc) { return $brief['source_research']['research_id'] === $btc['research_id'] && $brief['format'] === 'youtube_search'; }))[0]['intent_context']['primary'] === 'long_short');
compiler_check('ignored opportunities never influence campaign intent selection', array_values(array_filter($campaign['briefs'], function ($brief) use ($manual) { return $brief['source_research']['research_id'] === $manual['research_id']; }))[0]['intent_context']['primary'] === null);
compiler_check('campaign briefs remain free of third-party queue identifiers', strpos(json_encode($campaign['briefs']), 'private-author-ref') === false && strpos(json_encode($campaign['briefs']), 'private-source-ref') === false);
compiler_check('campaign briefs remain free of third-party queue text', strpos(json_encode($campaign['briefs']), 'third-party text must not propagate') === false);

$duplicated_campaign = Bitmomo_Content_Compiler_V1::compile_campaign([$btc, $btc], $queue, ['x_post', 'youtube_search'], $now);
compiler_check('campaign deduplicates repeated research inputs', $duplicated_campaign['counts']['briefs_before_dedupe'] === 4 && $duplicated_campaign['counts']['briefs'] === 2 && $duplicated_campaign['counts']['duplicates'] === 2);

$mixed_campaign = Bitmomo_Content_Compiler_V1::compile_campaign([$btc, $blocked, 'bad-shape'], [], ['x_post'], $now);
compiler_check('campaign rejects ineligible and malformed research without blocking valid research', $mixed_campaign['counts']['briefs'] === 1 && $mixed_campaign['counts']['rejected'] === 2);

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " Content Compiler V1 checks passed.\n";
