<?php

/**
 * Converts Indonesia-first live discovery + canonical P5 output into complete
 * operator alerts. The operator's only engagement action is the final manual
 * Post/Reply click on the social platform.
 */
final class Bitmomo_Operator_Alert_Engine_V1 {
    const ALERT_VERSION = 'operator-alert-v1';
    const ENGINE_VERSION = 'operator-alert-engine-v1';
    const IDEAL_AGE_SECONDS = 600;
    const MAX_REPLY_AGE_SECONDS = 1800;
    const MONITOR_ONLY_AGE_SECONDS = 3600;

    public static function run(
        array $credentials,
        callable $http_get,
        array $research_items,
        array $state = [],
        array $cooldown_state = [],
        array $interaction_context = [],
        $now = null
    ) {
        $now = self::now($now);
        $discovery = Bitmomo_Indonesia_Discovery_V1::discover($credentials, $http_get, $state, $now);
        $acquisition = Bitmomo_Acquisition_Orchestrator_V1::run(
            $research_items,
            $discovery['observations'] ?? [],
            $cooldown_state,
            $interaction_context,
            ['x_post', 'x_thread', 'youtube_search', 'youtube_short', 'research_article'],
            $now
        );
        $alerts = self::build_alerts($acquisition, $discovery, $state, $now);

        return [
            'engine_version' => self::ENGINE_VERSION,
            'mode' => 'operator_assisted_manual_posting',
            'generated_at' => gmdate('c', $now),
            'safety' => [
                'manual_post_required' => true,
                'auto_send_permitted' => false,
                'auto_publish_permitted' => false,
                'audience_percentage_claim_permitted' => false,
            ],
            'counts' => [
                'discovered' => count((array) ($discovery['observations'] ?? [])),
                'reply_alerts' => count($alerts['alerts']),
                'monitor_only' => count($alerts['monitor_only']),
                'suppressed' => count($alerts['suppressed']),
                'duplicates' => count($alerts['duplicates']),
            ],
            'alerts' => $alerts['alerts'],
            'monitor_only' => $alerts['monitor_only'],
            'suppressed' => $alerts['suppressed'],
            'duplicates' => $alerts['duplicates'],
            'next_state' => [
                'x_since_id' => $discovery['state_hints']['x_since_id'] ?? ($state['x_since_id'] ?? null),
                'youtube_published_after' => $discovery['state_hints']['youtube_published_after'] ?? ($state['youtube_published_after'] ?? null),
                'alerted_ids' => self::merge_alerted_ids((array) ($state['alerted_ids'] ?? []), $alerts['alerts']),
            ],
            'discovery' => $discovery,
            'acquisition_run' => $acquisition,
        ];
    }

    public static function build_alerts(array $acquisition, array $discovery, array $state = [], $now = null) {
        $now = self::now($now);
        $review = (array) ($acquisition['intent_queue']['review'] ?? []);
        $monitor = (array) ($acquisition['intent_queue']['monitor'] ?? []);
        $drafts = (array) ($acquisition['engagement_queue']['review_drafts'] ?? []);
        $draft_index = [];
        foreach ($drafts as $draft) {
            if (!is_array($draft)) continue;
            $opportunity_id = self::text($draft['source_context']['opportunity_id'] ?? '');
            if ($opportunity_id !== '') $draft_index[$opportunity_id] = $draft;
        }

        $target_index = self::target_index((array) ($discovery['observations'] ?? []));
        $seen = array_fill_keys(array_map('strval', (array) ($state['alerted_ids'] ?? [])), true);
        $alerts = [];
        $monitor_only = [];
        $suppressed = [];
        $duplicates = [];

        foreach ($review as $opportunity) {
            if (!is_array($opportunity)) continue;
            $result = self::alert_from_opportunity($opportunity, $draft_index, $target_index, $now);
            if (!$result['eligible']) {
                if ($result['class'] === 'monitor_only') $monitor_only[] = $result['diagnostic'];
                else $suppressed[] = $result['diagnostic'];
                continue;
            }
            $alert = $result['alert'];
            if (isset($seen[$alert['alert_id']])) {
                $duplicates[] = ['alert_id' => $alert['alert_id'], 'source_url' => $alert['source_url']];
                continue;
            }
            $seen[$alert['alert_id']] = true;
            $alerts[] = $alert;
        }

        // P1 may classify 30-60m demand as review; P7 demotes it. Existing monitor
        // items are retained for research/content learning but never operator reply alerts.
        foreach ($monitor as $opportunity) {
            if (!is_array($opportunity)) continue;
            $age = self::age_seconds($opportunity['observed_at'] ?? null, $now);
            if ($age === null || $age > self::MONITOR_ONLY_AGE_SECONDS) continue;
            $monitor_only[] = [
                'opportunity_id' => self::text($opportunity['opportunity_id'] ?? ''),
                'source_url' => self::nullable_text($opportunity['source_url'] ?? null),
                'age_minutes' => round($age / 60, 1),
                'reason' => 'monitor_not_reply_alert',
            ];
        }

        usort($alerts, function ($a, $b) {
            if ($a['priority_score'] !== $b['priority_score']) return $b['priority_score'] <=> $a['priority_score'];
            return strcmp($a['alert_id'], $b['alert_id']);
        });
        return ['alerts' => $alerts, 'monitor_only' => $monitor_only, 'suppressed' => $suppressed, 'duplicates' => $duplicates];
    }

    public static function format_notification(array $alert) {
        $lines = [];
        $lines[] = '🚨 BITMOMO OPPORTUNITY';
        $lines[] = 'Age: ' . self::text($alert['age_minutes'] ?? '') . ' min | Intent: ' . self::text($alert['intent'] ?? '') . ' | Score: ' . self::text($alert['intent_score'] ?? '');
        $lines[] = 'Target: Indonesia (' . self::text($alert['indonesia_target']['confidence_band'] ?? 'unknown') . ')';
        $lines[] = 'Tweet: ' . self::text($alert['source_url'] ?? '');
        $lines[] = '';
        $lines[] = 'SOURCE';
        $lines[] = self::text($alert['source_text'] ?? '');
        $lines[] = '';
        $lines[] = 'WHY NOW';
        $lines[] = self::text($alert['why_now'] ?? '');
        $lines[] = '';
        $lines[] = 'READY TO POST';
        $lines[] = self::text($alert['ready_to_post'] ?? '');
        $lines[] = '';
        $lines[] = 'Expires: ' . self::text($alert['expires_at'] ?? '');
        $lines[] = 'Manual post required. Auto-send OFF.';
        return implode("\n", $lines);
    }

    public static function dispatch(array $alerts, callable $notify) {
        $sent = [];
        $failed = [];
        foreach ($alerts as $alert) {
            if (!is_array($alert)) continue;
            $alert_id = self::text($alert['alert_id'] ?? '');
            try {
                $result = call_user_func($notify, self::format_notification($alert), $alert);
                if ($result === false) throw new RuntimeException('notify_false');
                $sent[] = $alert_id;
            } catch (Throwable $e) {
                $failed[] = ['alert_id' => $alert_id, 'code' => 'notify_failed'];
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    private static function alert_from_opportunity(array $opportunity, array $draft_index, array $target_index, $now) {
        $id = self::text($opportunity['opportunity_id'] ?? '');
        $source = self::text($opportunity['source'] ?? '');
        $url = self::text($opportunity['source_url'] ?? '');
        $age = self::age_seconds($opportunity['observed_at'] ?? null, $now);
        $diagnostic = ['opportunity_id' => $id, 'source_url' => $url ?: null];

        if ($source !== 'x') return ['eligible' => false, 'class' => 'suppressed', 'diagnostic' => $diagnostic + ['reason' => 'reply_alert_x_only_v1']];
        if ($url === '' || strpos($url, 'https://x.com/') !== 0) return ['eligible' => false, 'class' => 'suppressed', 'diagnostic' => $diagnostic + ['reason' => 'source_url_required']];
        if ($age === null) return ['eligible' => false, 'class' => 'suppressed', 'diagnostic' => $diagnostic + ['reason' => 'invalid_age']];
        if ($age > self::MONITOR_ONLY_AGE_SECONDS) return ['eligible' => false, 'class' => 'suppressed', 'diagnostic' => $diagnostic + ['reason' => 'expired_over_60m', 'age_minutes' => round($age / 60, 1)]];
        if ($age > self::MAX_REPLY_AGE_SECONDS) return ['eligible' => false, 'class' => 'monitor_only', 'diagnostic' => $diagnostic + ['reason' => '30_60m_monitor_only', 'age_minutes' => round($age / 60, 1)]];
        if (self::text($opportunity['detected_language'] ?? '') !== 'id') return ['eligible' => false, 'class' => 'suppressed', 'diagnostic' => $diagnostic + ['reason' => 'indonesia_language_required']];
        if (empty($opportunity['research_links'])) return ['eligible' => false, 'class' => 'suppressed', 'diagnostic' => $diagnostic + ['reason' => 'research_evidence_required']];
        if (!isset($draft_index[$id])) return ['eligible' => false, 'class' => 'suppressed', 'diagnostic' => $diagnostic + ['reason' => 'ready_reply_required']];

        $draft = $draft_index[$id];
        if (!empty($draft['auto_send_permitted']) || empty($draft['approval_required'])) return ['eligible' => false, 'class' => 'suppressed', 'diagnostic' => $diagnostic + ['reason' => 'delivery_policy_invalid']];
        $source_ref = self::text($opportunity['source_ref'] ?? '');
        $target = $target_index['x|' . $source_ref] ?? [];
        $score = (int) ($opportunity['score'] ?? 0);
        $freshness_bonus = $age <= self::IDEAL_AGE_SECONDS ? 15 : max(0, 15 - (int) floor(($age - self::IDEAL_AGE_SECONDS) / 120));
        $engagement_bonus = min(10, (int) floor(log(1 + self::engagement_total($target), 2)));
        $priority_score = min(125, $score + $freshness_bonus + $engagement_bonus);
        $research = (array) ($opportunity['research_links'][0] ?? []);
        $alert_id = 'boa-' . substr(hash('sha256', $id . '|' . self::text($draft['draft_id'] ?? '')), 0, 22);
        $expires = strtotime((string) ($opportunity['observed_at'] ?? '')) + self::MAX_REPLY_AGE_SECONDS;

        $alert = [
            'alert_version' => self::ALERT_VERSION,
            'alert_id' => $alert_id,
            'status' => 'ready_for_manual_post',
            'platform' => 'x',
            'source_url' => $url,
            'source_ref' => $source_ref,
            'source_timestamp' => self::text($opportunity['observed_at'] ?? ''),
            'age_minutes' => round($age / 60, 1),
            'expires_at' => gmdate('c', $expires),
            'source_text' => self::text($opportunity['text'] ?? ''),
            'intent' => self::text($opportunity['intent']['primary'] ?? ''),
            'intent_score' => $score,
            'priority_score' => $priority_score,
            'score_components' => $opportunity['score_components'] ?? [],
            'indonesia_target' => [
                'confidence_band' => 'high',
                'reasons' => ['x_lang_id_query', 'detected_language_id', 'indonesia_first_query_profile'],
                'audience_percentage' => null,
                'note' => 'This is targeting confidence, not a claim about follower geography.',
            ],
            'why_now' => ($age <= self::IDEAL_AGE_SECONDS ? 'Fresh high-intent Indonesian BTC demand detected within the ideal <=10 minute window.' : 'High-intent Indonesian BTC demand detected before the 30-minute reply expiry.'),
            'research_evidence' => [
                'research_id' => self::text($research['research_id'] ?? ''),
                'title' => self::text($research['title'] ?? ''),
                'match_score' => (int) ($research['match_score'] ?? 0),
                'finding' => self::text($draft['evidence_trace']['finding'] ?? ''),
                'limitation' => self::text($draft['evidence_trace']['limitation'] ?? ''),
            ],
            'ready_to_post' => self::text($draft['draft_text'] ?? ''),
            'manual_post_required' => true,
            'auto_send_permitted' => false,
            'cooldown_key' => self::text($opportunity['cooldown_key'] ?? ''),
            'operator_action' => 'Open source_url, paste ready_to_post, click Reply/Post manually.',
        ];
        return ['eligible' => true, 'class' => 'alert', 'alert' => $alert, 'diagnostic' => null];
    }

    private static function target_index(array $observations) {
        $out = [];
        foreach ($observations as $row) {
            if (!is_array($row)) continue;
            $source = self::text($row['source'] ?? '');
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            $id = self::text($payload['id'] ?? '');
            if ($source === '' || $id === '') continue;
            $out[$source . '|' . $id] = is_array($row['targeting'] ?? null) ? $row['targeting'] : [];
        }
        return $out;
    }

    private static function engagement_total(array $target) {
        $m = is_array($target['public_metrics'] ?? null) ? $target['public_metrics'] : [];
        return max(0, (int) ($m['like_count'] ?? 0)) + max(0, (int) ($m['reply_count'] ?? 0)) + max(0, (int) ($m['retweet_count'] ?? 0)) + max(0, (int) ($m['quote_count'] ?? 0));
    }

    private static function merge_alerted_ids(array $existing, array $alerts) {
        $ids = array_values(array_unique(array_map('strval', $existing)));
        foreach ($alerts as $alert) if (is_array($alert) && !empty($alert['alert_id'])) $ids[] = (string) $alert['alert_id'];
        $ids = array_values(array_unique($ids));
        return array_slice($ids, -500);
    }

    private static function age_seconds($value, $now) {
        $ts = is_string($value) ? strtotime($value) : false;
        if (!$ts || $ts > $now + 300) return null;
        return max(0, $now - $ts);
    }
    private static function nullable_text($value) { $value = self::text($value); return $value === '' ? null : $value; }
    private static function text($value) { return is_scalar($value) ? trim((string) $value) : ''; }
    private static function now($now) { return $now === null ? time() : (is_numeric($now) ? (int) $now : time()); }
}
