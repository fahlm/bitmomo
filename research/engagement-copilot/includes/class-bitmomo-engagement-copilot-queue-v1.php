<?php

/** Deterministic operator approval queue over Engagement Copilot V1. */
final class Bitmomo_Engagement_Copilot_Queue_V1 {
    const QUEUE_VERSION = 'engagement-approval-queue-v1';

    public static function build(array $opportunities, array $content_briefs, array $interaction_context = [], $now = null) {
        $now = $now === null ? time() : (is_numeric($now) ? (int) $now : time());
        $brief_index = self::index_briefs($content_briefs);
        $drafts = [];
        $rejected = [];

        foreach ($opportunities as $index => $opportunity) {
            if (!is_array($opportunity)) {
                $rejected[] = ['index' => $index, 'errors' => ['opportunity_shape']];
                continue;
            }

            $brief = self::select_brief($opportunity, $brief_index);
            if (!$brief) {
                $rejected[] = [
                    'index' => $index,
                    'opportunity_id' => self::text($opportunity['opportunity_id'] ?? '') ?: null,
                    'errors' => ['content_brief_not_found'],
                ];
                continue;
            }

            $context = self::interaction_context_for($opportunity, $interaction_context);
            $result = Bitmomo_Engagement_Copilot_V1::draft($opportunity, $brief, $context, $now);
            if (empty($result['valid']) || !is_array($result['draft'] ?? null)) {
                $rejected[] = [
                    'index' => $index,
                    'opportunity_id' => self::text($opportunity['opportunity_id'] ?? '') ?: null,
                    'errors' => array_values((array) ($result['errors'] ?? ['invalid_draft'])),
                ];
                continue;
            }

            $drafts[] = $result['draft'];
        }

        $deduped = Bitmomo_Engagement_Copilot_V1::deduplicate($drafts);
        self::sort_drafts($deduped['drafts']);

        return [
            'queue_version' => self::QUEUE_VERSION,
            'copilot_version' => Bitmomo_Engagement_Copilot_V1::COPILOT_VERSION,
            'generated_at' => gmdate('c', $now),
            'counts' => [
                'input_opportunities' => count($opportunities),
                'drafts_before_dedupe' => count($drafts),
                'review_drafts' => count($deduped['drafts']),
                'duplicates' => count($deduped['duplicates']),
                'rejected' => count($rejected),
            ],
            'review_drafts' => $deduped['drafts'],
            'duplicates' => $deduped['duplicates'],
            'rejected' => $rejected,
        ];
    }

    private static function index_briefs(array $briefs) {
        $index = [];
        foreach ($briefs as $brief) {
            if (!is_array($brief)) continue;
            $research_id = self::text($brief['source_research']['research_id'] ?? '');
            if ($research_id === '') continue;
            if (!isset($index[$research_id])) $index[$research_id] = [];
            $index[$research_id][] = $brief;
        }
        return $index;
    }

    private static function select_brief(array $opportunity, array $index) {
        $intent = self::text($opportunity['intent']['primary'] ?? '');
        $source = self::text($opportunity['source'] ?? '');
        $candidate_ids = [];
        foreach ((array) ($opportunity['research_links'] ?? []) as $link) {
            if (!is_array($link)) continue;
            $id = self::text($link['research_id'] ?? '');
            if ($id !== '') $candidate_ids[] = $id;
        }

        $candidates = [];
        foreach ($candidate_ids as $research_id) {
            foreach ((array) ($index[$research_id] ?? []) as $brief) {
                $brief_intent = self::text($brief['intent_context']['primary'] ?? '');
                $format = self::text($brief['format'] ?? '');
                $rank = 0;
                if ($brief_intent === $intent) $rank += 100;
                if ($source === 'x' && $format === 'x_post') $rank += 20;
                if ($source === 'youtube' && $format === 'youtube_search') $rank += 20;
                if ($format === 'research_article') $rank += 5;
                $candidates[] = ['rank' => $rank, 'brief' => $brief];
            }
        }

        if (!$candidates) return null;
        usort($candidates, function ($a, $b) {
            if ($a['rank'] !== $b['rank']) return $b['rank'] <=> $a['rank'];
            return strcmp(
                (string) ($a['brief']['brief_id'] ?? ''),
                (string) ($b['brief']['brief_id'] ?? '')
            );
        });
        return $candidates[0]['brief'];
    }

    private static function interaction_context_for(array $opportunity, array $contexts) {
        $opportunity_id = self::text($opportunity['opportunity_id'] ?? '');
        $source_ref = self::text($opportunity['source_ref'] ?? '');
        if ($opportunity_id !== '' && is_array($contexts[$opportunity_id] ?? null)) return $contexts[$opportunity_id];
        if ($source_ref !== '' && is_array($contexts[$source_ref] ?? null)) return $contexts[$source_ref];
        return [];
    }

    private static function sort_drafts(array &$drafts) {
        usort($drafts, function ($a, $b) {
            $sa = (int) ($a['source_context']['score'] ?? 0);
            $sb = (int) ($b['source_context']['score'] ?? 0);
            if ($sa !== $sb) return $sb <=> $sa;
            return strcmp((string) ($a['draft_id'] ?? ''), (string) ($b['draft_id'] ?? ''));
        });
    }

    private static function text($value) {
        if (!is_scalar($value)) return '';
        return trim((string) $value);
    }
}
