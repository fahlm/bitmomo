<?php

/**
 * Deterministic batch queue builder over Intent Radar V1.
 *
 * This class accepts supplied observations only. It performs no network calls,
 * persistence, publishing, or outbound engagement.
 */
final class Bitmomo_Intent_Radar_Queue_V1 {
    const QUEUE_VERSION = 'intent-radar-queue-v1';

    public static function build(array $observations, array $research_items = [], array $cooldown_state = [], $now = null) {
        $now = $now === null ? time() : (is_numeric($now) ? (int) $now : time());
        $accepted = [];
        $rejected = [];

        foreach ($observations as $index => $row) {
            if (!is_array($row)) {
                $rejected[] = ['index' => $index, 'errors' => ['observation_shape']];
                continue;
            }

            $source = strtolower(trim((string) ($row['source'] ?? '')));
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : $row;

            if ($source === 'x') {
                $result = Bitmomo_Intent_Radar_V1::from_x_fixture($payload, $research_items, $now);
            } elseif ($source === 'youtube') {
                $result = Bitmomo_Intent_Radar_V1::from_youtube_fixture($payload, $research_items, $now);
            } else {
                $rejected[] = ['index' => $index, 'errors' => ['source']];
                continue;
            }

            if (empty($result['valid']) || !is_array($result['opportunity'] ?? null)) {
                $rejected[] = [
                    'index' => $index,
                    'source' => $source,
                    'source_ref' => self::source_ref($payload),
                    'errors' => array_values((array) ($result['errors'] ?? ['invalid_observation'])),
                ];
                continue;
            }

            $accepted[] = $result['opportunity'];
        }

        $deduped = Bitmomo_Intent_Radar_V1::deduplicate($accepted);
        $review = [];
        $monitor = [];
        $ignored = [];
        $cooldown = [];

        foreach ($deduped['items'] as $opportunity) {
            if (Bitmomo_Intent_Radar_V1::in_cooldown($opportunity, $cooldown_state, $now)) {
                $cooldown[] = $opportunity;
                continue;
            }

            $action = (string) ($opportunity['suggested_action'] ?? 'ignore');
            if ($action === 'review_for_reply') $review[] = $opportunity;
            elseif ($action === 'monitor') $monitor[] = $opportunity;
            else $ignored[] = $opportunity;
        }

        self::sort_queue($review);
        self::sort_queue($monitor);
        self::sort_queue($ignored);
        self::sort_queue($cooldown);

        return [
            'queue_version' => self::QUEUE_VERSION,
            'radar_version' => Bitmomo_Intent_Radar_V1::RADAR_VERSION,
            'generated_at' => gmdate('c', $now),
            'counts' => [
                'input' => count($observations),
                'accepted_before_dedupe' => count($accepted),
                'unique' => count($deduped['items']),
                'review' => count($review),
                'monitor' => count($monitor),
                'ignored' => count($ignored),
                'cooldown' => count($cooldown),
                'rejected' => count($rejected),
                'duplicates' => count($deduped['duplicates']),
            ],
            'review' => $review,
            'monitor' => $monitor,
            'ignored' => $ignored,
            'cooldown' => $cooldown,
            'rejected' => $rejected,
            'duplicates' => $deduped['duplicates'],
        ];
    }

    private static function source_ref(array $payload) {
        foreach (['id', 'source_ref'] as $key) {
            if (!empty($payload[$key]) && is_scalar($payload[$key])) return trim((string) $payload[$key]);
        }
        return null;
    }

    private static function sort_queue(array &$items) {
        usort($items, function ($a, $b) {
            $score_a = (int) ($a['score'] ?? 0);
            $score_b = (int) ($b['score'] ?? 0);
            if ($score_a !== $score_b) return $score_b <=> $score_a;
            return strcmp((string) ($a['opportunity_id'] ?? ''), (string) ($b['opportunity_id'] ?? ''));
        });
    }
}
