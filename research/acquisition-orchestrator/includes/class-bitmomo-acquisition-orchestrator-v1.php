<?php

/**
 * End-to-end dry-run coordinator for Bitmomo's research distribution stack.
 *
 * P5 introduces no new scoring, evidence, claims, or delivery policy. It only
 * composes canonical P0-P4 modules into one deterministic operator worklist.
 */
final class Bitmomo_Acquisition_Orchestrator_V1 {
    const RUN_VERSION = 'acquisition-run-v1';
    const ORCHESTRATOR_VERSION = 'acquisition-orchestrator-v1';

    public static function run(
        array $research_items,
        array $observations,
        array $cooldown_state = [],
        array $interaction_context = [],
        array $formats = [],
        $now = null
    ) {
        $now = self::now($now);
        $formats = $formats ?: ['x_post', 'x_thread', 'youtube_search', 'youtube_short', 'research_article'];

        $intent_queue = Bitmomo_Intent_Radar_Queue_V1::build(
            $observations,
            $research_items,
            $cooldown_state,
            $now
        );

        $content_campaign = Bitmomo_Content_Compiler_V1::compile_campaign(
            $research_items,
            $intent_queue,
            $formats,
            $now
        );

        $engagement_queue = Bitmomo_Engagement_Copilot_Queue_V1::build(
            $intent_queue['review'] ?? [],
            $content_campaign['briefs'] ?? [],
            $interaction_context,
            $now
        );

        $attribution_raw = self::attribution_events($intent_queue, $content_campaign, $engagement_queue);
        $attribution_ledger = Bitmomo_Growth_Attribution_V1::build_ledger($attribution_raw, $now);

        $run_id = self::run_id($research_items, $observations, $formats);
        $worklist = [
            'engagement_review' => array_values($engagement_queue['review_drafts'] ?? []),
            'owned_content' => array_values($content_campaign['briefs'] ?? []),
            'monitor' => array_values($intent_queue['monitor'] ?? []),
        ];

        $run = [
            'run_version' => self::RUN_VERSION,
            'orchestrator_version' => self::ORCHESTRATOR_VERSION,
            'run_id' => $run_id,
            'fingerprint' => '',
            'mode' => 'dry_run_only',
            'generated_at' => gmdate('c', $now),
            'safety' => [
                'network_access_permitted' => false,
                'browser_automation_permitted' => false,
                'auto_publish_permitted' => false,
                'auto_send_permitted' => false,
                'production_mutation_permitted' => false,
                'human_approval_required_for_engagement' => true,
            ],
            'counts' => [
                'research_inputs' => count($research_items),
                'observation_inputs' => count($observations),
                'intent_review' => (int) ($intent_queue['counts']['review'] ?? 0),
                'intent_monitor' => (int) ($intent_queue['counts']['monitor'] ?? 0),
                'intent_ignored' => (int) ($intent_queue['counts']['ignored'] ?? 0),
                'intent_rejected' => (int) ($intent_queue['counts']['rejected'] ?? 0),
                'content_briefs' => (int) ($content_campaign['counts']['briefs'] ?? 0),
                'engagement_drafts' => (int) ($engagement_queue['counts']['review_drafts'] ?? 0),
                'attribution_events' => (int) ($attribution_ledger['counts']['events'] ?? 0),
                'attribution_rejected' => (int) ($attribution_ledger['counts']['rejected'] ?? 0),
            ],
            'worklist' => $worklist,
            'diagnostics' => [
                'intent' => [
                    'rejected' => $intent_queue['rejected'] ?? [],
                    'duplicates' => $intent_queue['duplicates'] ?? [],
                    'cooldown' => $intent_queue['cooldown'] ?? [],
                ],
                'content' => [
                    'rejected' => $content_campaign['rejected'] ?? [],
                    'duplicates' => $content_campaign['duplicates'] ?? [],
                ],
                'engagement' => [
                    'rejected' => $engagement_queue['rejected'] ?? [],
                    'duplicates' => $engagement_queue['duplicates'] ?? [],
                ],
                'attribution' => [
                    'rejected' => $attribution_ledger['rejected'] ?? [],
                    'duplicates' => $attribution_ledger['duplicates'] ?? [],
                ],
            ],
            'intent_queue' => $intent_queue,
            'content_campaign' => $content_campaign,
            'engagement_queue' => $engagement_queue,
            'attribution_ledger' => $attribution_ledger,
        ];

        $run['fingerprint'] = self::fingerprint($run);
        return $run;
    }

    private static function attribution_events(array $intent_queue, array $content_campaign, array $engagement_queue) {
        $events = [];
        $opportunities = array_merge(
            array_values($intent_queue['review'] ?? []),
            array_values($intent_queue['monitor'] ?? [])
        );

        foreach ($opportunities as $opportunity) {
            if (!is_array($opportunity)) continue;
            $opportunity_id = self::text($opportunity['opportunity_id'] ?? '');
            if ($opportunity_id === '') continue;
            $intent = self::key($opportunity['intent']['primary'] ?? '');
            $research_id = self::first_research_id($opportunity);
            $events[] = [
                'event_type' => 'intent_detected',
                'event_ref' => 'intent:' . $opportunity_id,
                'occurred_at' => self::text($opportunity['created_at'] ?? $opportunity['observed_at'] ?? ''),
                'lineage' => [
                    'research_id' => $research_id,
                    'opportunity_id' => $opportunity_id,
                    'intent' => $intent,
                    'keyword_cluster' => self::keyword_cluster($intent),
                    'channel' => self::channel($opportunity['source'] ?? ''),
                    'format' => 'unknown',
                ],
                'metadata' => ['surface' => 'intent_radar'],
            ];
        }

        foreach ((array) ($content_campaign['briefs'] ?? []) as $brief) {
            if (!is_array($brief)) continue;
            $brief_id = self::text($brief['brief_id'] ?? '');
            if ($brief_id === '') continue;
            $intent = self::key($brief['intent_context']['primary'] ?? '');
            $attr = is_array($brief['attribution'] ?? null) ? $brief['attribution'] : [];
            $events[] = [
                'event_type' => 'draft_created',
                'event_ref' => 'content:' . $brief_id,
                'occurred_at' => self::text($brief['created_at'] ?? ''),
                'lineage' => [
                    'research_id' => self::text($brief['source_research']['research_id'] ?? '') ?: null,
                    'brief_id' => $brief_id,
                    'intent' => $intent ?: null,
                    'keyword_cluster' => self::keyword_cluster($intent),
                    'channel' => self::channel($brief['channel'] ?? ''),
                    'format' => self::key($brief['format'] ?? 'unknown') ?: 'unknown',
                    'utm_source' => $attr['utm_source'] ?? null,
                    'utm_medium' => $attr['utm_medium'] ?? null,
                    'utm_campaign' => $attr['utm_campaign'] ?? null,
                    'utm_content' => $attr['utm_content'] ?? null,
                ],
                'metadata' => ['surface' => 'owned_content_brief'],
            ];
        }

        foreach ((array) ($engagement_queue['review_drafts'] ?? []) as $draft) {
            if (!is_array($draft)) continue;
            $draft_id = self::text($draft['draft_id'] ?? '');
            if ($draft_id === '') continue;
            $intent = self::key($draft['intent'] ?? '');
            $events[] = [
                'event_type' => 'draft_created',
                'event_ref' => 'engagement:' . $draft_id,
                'occurred_at' => self::text($draft['created_at'] ?? ''),
                'lineage' => [
                    'research_id' => self::text($draft['evidence_trace']['research_id'] ?? '') ?: null,
                    'opportunity_id' => self::text($draft['source_context']['opportunity_id'] ?? '') ?: null,
                    'draft_id' => $draft_id,
                    'intent' => $intent ?: null,
                    'keyword_cluster' => self::keyword_cluster($intent),
                    'channel' => self::channel($draft['source_context']['source'] ?? ''),
                    'format' => 'reply_draft',
                ],
                'metadata' => ['surface' => 'engagement_draft'],
            ];
        }

        return $events;
    }

    private static function first_research_id(array $opportunity) {
        foreach ((array) ($opportunity['research_links'] ?? []) as $link) {
            if (!is_array($link)) continue;
            $id = self::text($link['research_id'] ?? '');
            if ($id !== '') return $id;
        }
        return null;
    }

    private static function keyword_cluster($intent) {
        $map = [
            'explicit_signal' => 'btc-signal',
            'long_short' => 'btc-long-or-short',
            'entry_exit' => 'btc-entry-exit',
            'analysis_prediction' => 'bitcoin-analysis-prediction',
            'breakout_support_resistance' => 'btc-levels-breakout',
            'why_move' => 'why-btc-moving',
            'event_reaction' => 'btc-event-reaction',
            'ai_research' => 'ai-bitcoin-research',
        ];
        return $map[$intent] ?? 'btc-market-intelligence';
    }

    private static function channel($value) {
        $value = self::key($value);
        $allowed = ['x', 'youtube', 'bitmomo_research', 'website', 'email', 'telegram', 'search', 'direct'];
        return in_array($value, $allowed, true) ? $value : 'unknown';
    }

    private static function run_id(array $research_items, array $observations, array $formats) {
        $research = [];
        foreach ($research_items as $item) {
            if (!is_array($item)) continue;
            $research[] = [
                self::text($item['research_id'] ?? ''),
                self::text($item['fingerprint'] ?? ''),
            ];
        }
        usort($research, function ($a, $b) { return strcmp(implode('|', $a), implode('|', $b)); });

        $observation_ids = [];
        foreach ($observations as $row) {
            if (!is_array($row)) continue;
            $source = self::key($row['source'] ?? '');
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : $row;
            $id = self::text($payload['id'] ?? $payload['source_ref'] ?? '');
            $text = self::text($payload['text'] ?? $payload['title'] ?? '');
            $observation_ids[] = [$source, $id, hash('sha256', $text)];
        }
        usort($observation_ids, function ($a, $b) { return strcmp(implode('|', $a), implode('|', $b)); });

        $formats = array_values(array_unique(array_map([__CLASS__, 'key'], $formats)));
        sort($formats, SORT_STRING);
        $stable = ['research' => $research, 'observations' => $observation_ids, 'formats' => $formats];
        return 'bar-' . substr(hash('sha256', json_encode($stable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 24);
    }

    private static function fingerprint(array $run) {
        $stable = [
            'run_version' => $run['run_version'] ?? null,
            'run_id' => $run['run_id'] ?? null,
            'safety' => $run['safety'] ?? [],
            'counts' => $run['counts'] ?? [],
            'worklist' => self::stable_worklist($run['worklist'] ?? []),
            'attribution_event_ids' => array_map(function ($event) {
                return is_array($event) ? ($event['event_id'] ?? null) : null;
            }, (array) ($run['attribution_ledger']['events'] ?? [])),
        ];
        return 'sha256:' . hash('sha256', json_encode(self::canonicalize($stable), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function stable_worklist(array $worklist) {
        return [
            'engagement_review' => array_map(function ($row) { return is_array($row) ? ($row['draft_id'] ?? null) : null; }, (array) ($worklist['engagement_review'] ?? [])),
            'owned_content' => array_map(function ($row) { return is_array($row) ? ($row['brief_id'] ?? null) : null; }, (array) ($worklist['owned_content'] ?? [])),
            'monitor' => array_map(function ($row) { return is_array($row) ? ($row['opportunity_id'] ?? null) : null; }, (array) ($worklist['monitor'] ?? [])),
        ];
    }

    private static function canonicalize($value) {
        if (!is_array($value)) return $value;
        if (array_keys($value) === range(0, count($value) - 1)) return array_map([__CLASS__, 'canonicalize'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $entry) $value[$key] = self::canonicalize($entry);
        return $value;
    }

    private static function key($value) {
        $value = strtolower(self::text($value));
        return preg_replace('/[^a-z0-9_\-]/', '', $value);
    }

    private static function text($value) {
        if (!is_scalar($value)) return '';
        return trim((string) $value);
    }

    private static function now($now) {
        if ($now === null) return time();
        return is_numeric($now) ? (int) $now : time();
    }
}
