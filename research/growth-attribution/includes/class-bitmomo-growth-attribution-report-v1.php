<?php

/** Deterministic descriptive reporting over Growth Attribution Ledger V1. */
final class Bitmomo_Growth_Attribution_Report_V1 {
    const REPORT_VERSION = 'growth-attribution-report-v1';
    const SEMANTICS = 'observational_not_causal';
    const DEFAULT_MIN_ACTORS = 20;
    const DEFAULT_MIN_CONVERSIONS = 3;

    private static $funnel_order = [
        'intent_detected', 'draft_created', 'operator_approved', 'content_published',
        'profile_visit', 'site_click', 'btc_page_view', 'whitelist_start',
        'whitelist_complete', 'paid_activation',
    ];

    private static $touch_events = ['profile_visit', 'site_click', 'btc_page_view'];

    private static $dimensions = [
        'channel', 'intent', 'research_id', 'keyword_cluster', 'campaign', 'format',
    ];

    public static function build(array $ledger_or_events, $min_actors = null, $min_conversions = null) {
        $min_actors = $min_actors === null ? self::DEFAULT_MIN_ACTORS : max(1, (int) $min_actors);
        $min_conversions = $min_conversions === null ? self::DEFAULT_MIN_CONVERSIONS : max(1, (int) $min_conversions);
        $events = isset($ledger_or_events['events']) && is_array($ledger_or_events['events'])
            ? $ledger_or_events['events']
            : $ledger_or_events;
        $events = array_values(array_filter($events, 'is_array'));
        self::sort_events($events);

        $funnel = array_fill_keys(self::$funnel_order, 0);
        foreach ($events as $event) {
            $type = self::text($event['event_type'] ?? '');
            if (isset($funnel[$type])) $funnel[$type]++;
        }

        $segments = [];
        foreach (self::$dimensions as $dimension) {
            $segments[$dimension] = self::segment($events, $dimension, $min_actors, $min_conversions);
        }

        $touch = self::touch_attribution($events);

        return [
            'report_version' => self::REPORT_VERSION,
            'attribution_semantics' => self::SEMANTICS,
            'qualified_conversion_event' => 'whitelist_complete',
            'funnel' => $funnel,
            'segments' => $segments,
            'touch_attribution' => $touch,
            'learning_policy' => [
                'min_unique_actors' => $min_actors,
                'min_qualified_conversions' => $min_conversions,
                'winner_selection_permitted' => false,
                'interpretation' => 'descriptive_signal_only',
            ],
        ];
    }

    private static function segment(array $events, $dimension, $min_actors, $min_conversions) {
        $groups = [];

        foreach ($events as $event) {
            $value = self::dimension_value($event, $dimension);
            if ($value === null || $value === '') continue;
            if (!isset($groups[$value])) {
                $groups[$value] = [
                    'value' => $value,
                    'events' => 0,
                    'funnel' => array_fill_keys(self::$funnel_order, 0),
                    '_actors' => [],
                    '_converted_actors' => [],
                ];
            }
            $groups[$value]['events']++;
            $type = self::text($event['event_type'] ?? '');
            if (isset($groups[$value]['funnel'][$type])) $groups[$value]['funnel'][$type]++;

            $actor = self::text($event['actor_ref'] ?? '');
            if ($actor !== '') {
                $groups[$value]['_actors'][$actor] = true;
                if ($type === 'whitelist_complete') $groups[$value]['_converted_actors'][$actor] = true;
            }
        }

        $out = [];
        foreach ($groups as $value => $group) {
            $actor_count = count($group['_actors']);
            $converted_count = count($group['_converted_actors']);
            $rate = $actor_count > 0 ? $converted_count / $actor_count : null;
            $eligible = $actor_count >= $min_actors && $converted_count >= $min_conversions;

            unset($group['_actors'], $group['_converted_actors']);
            $group['unique_actors'] = $actor_count;
            $group['qualified_conversions'] = $group['funnel']['whitelist_complete'];
            $group['unique_converted_actors'] = $converted_count;
            $group['descriptive_whitelist_conversion_rate'] = $rate;
            $group['learning_signal'] = [
                'status' => $eligible ? 'eligible_observation' : 'insufficient_sample',
                'winner_recommendation' => null,
                'causal_claim_permitted' => false,
            ];
            $out[] = $group;
        }

        usort($out, function ($a, $b) {
            if ($a['qualified_conversions'] !== $b['qualified_conversions']) {
                return $b['qualified_conversions'] <=> $a['qualified_conversions'];
            }
            if ($a['unique_actors'] !== $b['unique_actors']) return $b['unique_actors'] <=> $a['unique_actors'];
            return strcmp((string) $a['value'], (string) $b['value']);
        });
        return $out;
    }

    private static function touch_attribution(array $events) {
        $by_actor = [];
        foreach ($events as $event) {
            $actor = self::text($event['actor_ref'] ?? '');
            if ($actor === '') continue;
            $by_actor[$actor][] = $event;
        }

        $first = ['channel' => [], 'campaign' => [], 'intent' => []];
        $last = ['channel' => [], 'campaign' => [], 'intent' => []];
        $converted_actors = 0;
        $attributable_actors = 0;

        foreach ($by_actor as $actor_events) {
            self::sort_events($actor_events);
            $conversion_ts = null;
            foreach ($actor_events as $event) {
                if (($event['event_type'] ?? '') === 'whitelist_complete') {
                    $conversion_ts = strtotime((string) ($event['occurred_at'] ?? '')) ?: null;
                    if ($conversion_ts !== null) break;
                }
            }
            if ($conversion_ts === null) continue;
            $converted_actors++;

            $touches = [];
            foreach ($actor_events as $event) {
                $type = self::text($event['event_type'] ?? '');
                if (!in_array($type, self::$touch_events, true)) continue;
                $ts = strtotime((string) ($event['occurred_at'] ?? '')) ?: 0;
                if ($ts <= $conversion_ts) $touches[] = $event;
            }
            if (!$touches) continue;
            $attributable_actors++;
            $first_touch = $touches[0];
            $last_touch = $touches[count($touches) - 1];
            self::count_touch($first, $first_touch);
            self::count_touch($last, $last_touch);
        }

        foreach ([$first, $last] as &$collection) {
            foreach ($collection as &$counts) {
                ksort($counts, SORT_STRING);
            }
        }
        unset($collection, $counts);

        return [
            'semantics' => self::SEMANTICS,
            'converted_actors_with_opaque_ref' => $converted_actors,
            'converted_actors_with_preconversion_touch' => $attributable_actors,
            'first_touch' => $first,
            'last_touch' => $last,
            'actor_refs_exposed' => false,
        ];
    }

    private static function count_touch(array &$bucket, array $event) {
        $lineage = is_array($event['lineage'] ?? null) ? $event['lineage'] : [];
        $values = [
            'channel' => self::text($lineage['channel'] ?? '') ?: 'unknown',
            'campaign' => self::text($lineage['utm_campaign'] ?? '') ?: 'unknown',
            'intent' => self::text($lineage['intent'] ?? '') ?: 'unknown',
        ];
        foreach ($values as $dimension => $value) {
            if (!isset($bucket[$dimension][$value])) $bucket[$dimension][$value] = 0;
            $bucket[$dimension][$value]++;
        }
    }

    private static function dimension_value(array $event, $dimension) {
        $lineage = is_array($event['lineage'] ?? null) ? $event['lineage'] : [];
        $map = [
            'channel' => $lineage['channel'] ?? null,
            'intent' => $lineage['intent'] ?? null,
            'research_id' => $lineage['research_id'] ?? null,
            'keyword_cluster' => $lineage['keyword_cluster'] ?? null,
            'campaign' => $lineage['utm_campaign'] ?? null,
            'format' => $lineage['format'] ?? null,
        ];
        $value = $map[$dimension] ?? null;
        return $value === null ? null : self::text($value);
    }

    private static function sort_events(array &$events) {
        usort($events, function ($a, $b) {
            $ta = strtotime((string) ($a['occurred_at'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['occurred_at'] ?? '')) ?: 0;
            if ($ta !== $tb) return $ta <=> $tb;
            return strcmp((string) ($a['event_id'] ?? ''), (string) ($b['event_id'] ?? ''));
        });
    }

    private static function text($value) {
        if (!is_scalar($value)) return '';
        return trim((string) $value);
    }
}
