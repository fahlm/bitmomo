<?php

/**
 * Privacy-minimal deterministic growth attribution ledger.
 *
 * P4 is research/tooling only. It performs no tracking, cookies, network calls,
 * database writes, browser instrumentation, or production analytics.
 */
final class Bitmomo_Growth_Attribution_V1 {
    const CONTRACT_VERSION = 'growth-event-v1';
    const LEDGER_VERSION = 'growth-attribution-ledger-v1';
    const ATTRIBUTION_SEMANTICS = 'observational_not_causal';
    const MAX_FUTURE_SECONDS = 300;

    private static $event_types = [
        'intent_detected',
        'draft_created',
        'operator_approved',
        'content_published',
        'profile_visit',
        'site_click',
        'btc_page_view',
        'whitelist_start',
        'whitelist_complete',
        'paid_activation',
    ];

    private static $channels = [
        'x', 'youtube', 'bitmomo_research', 'website', 'email', 'telegram',
        'search', 'direct', 'unknown',
    ];

    private static $formats = [
        'x_post', 'x_thread', 'youtube_search', 'youtube_short',
        'research_article', 'reply_draft', 'landing_page', 'email',
        'telegram_alert', 'unknown',
    ];

    private static $campaign_required_events = [
        'content_published', 'profile_visit', 'site_click', 'btc_page_view',
        'whitelist_start', 'whitelist_complete', 'paid_activation',
    ];

    public static function ingest(array $event, $now = null) {
        $now = self::now($now);
        $errors = [];

        $event_type = self::key($event['event_type'] ?? '');
        if (!in_array($event_type, self::$event_types, true)) $errors[] = 'event_type';

        $event_ref = self::text($event['event_ref'] ?? '');
        if ($event_ref === '') $errors[] = 'event_ref';
        if ($event_ref !== '' && !self::opaque_ref($event_ref, false)) $errors[] = 'event_ref_unsafe';

        $occurred_at = self::parse_time($event['occurred_at'] ?? null);
        if (!$occurred_at) {
            $errors[] = 'occurred_at';
        } elseif ($occurred_at > $now + self::MAX_FUTURE_SECONDS) {
            $errors[] = 'occurred_at_future';
        }

        $actor_ref = self::nullable_text($event['actor_ref'] ?? null);
        if ($actor_ref !== null && !self::opaque_ref($actor_ref, true)) $errors[] = 'actor_ref_unsafe';

        $session_ref = self::nullable_text($event['session_ref'] ?? null);
        if ($session_ref !== null && !self::opaque_ref($session_ref, true)) $errors[] = 'session_ref_unsafe';

        foreach (['email', 'username', 'handle', 'ip', 'ip_address', 'wallet', 'wallet_address', 'phone', 'name', 'source_user'] as $forbidden) {
            if (array_key_exists($forbidden, $event)) $errors[] = 'pii_field:' . $forbidden;
        }

        $lineage = self::normalize_lineage($event['lineage'] ?? [], $event_type, $errors);
        $metadata = self::normalize_metadata($event['metadata'] ?? [], $errors);

        $errors = array_values(array_unique($errors));
        sort($errors, SORT_STRING);
        if ($errors) return self::failure($errors);

        $occurred_iso = gmdate('c', $occurred_at);
        $normalized = [
            'contract_version' => self::CONTRACT_VERSION,
            'ledger_version' => self::LEDGER_VERSION,
            'event_id' => '',
            'fingerprint' => '',
            'event_type' => $event_type,
            'event_ref' => $event_ref,
            'occurred_at' => $occurred_iso,
            'actor_ref' => $actor_ref,
            'session_ref' => $session_ref,
            'lineage' => $lineage,
            'metadata' => $metadata,
            'attribution_semantics' => self::ATTRIBUTION_SEMANTICS,
        ];

        $normalized['event_id'] = self::event_id($normalized);
        $normalized['fingerprint'] = self::fingerprint($normalized);
        return ['valid' => true, 'errors' => [], 'event' => $normalized];
    }

    public static function build_ledger(array $raw_events, $now = null) {
        $now = self::now($now);
        $accepted = [];
        $rejected = [];

        foreach ($raw_events as $index => $raw) {
            if (!is_array($raw)) {
                $rejected[] = ['index' => $index, 'errors' => ['event_shape']];
                continue;
            }
            $result = self::ingest($raw, $now);
            if (empty($result['valid'])) {
                $rejected[] = [
                    'index' => $index,
                    'event_ref' => self::text($raw['event_ref'] ?? '') ?: null,
                    'errors' => $result['errors'],
                ];
                continue;
            }
            $accepted[] = $result['event'];
        }

        $deduped = self::deduplicate($accepted);
        self::sort_events($deduped['events']);

        return [
            'ledger_version' => self::LEDGER_VERSION,
            'attribution_semantics' => self::ATTRIBUTION_SEMANTICS,
            'generated_at' => gmdate('c', $now),
            'counts' => [
                'inputs' => count($raw_events),
                'accepted_before_dedupe' => count($accepted),
                'events' => count($deduped['events']),
                'duplicates' => count($deduped['duplicates']),
                'rejected' => count($rejected),
            ],
            'events' => $deduped['events'],
            'duplicates' => $deduped['duplicates'],
            'rejected' => $rejected,
        ];
    }

    public static function deduplicate(array $events) {
        $unique = [];
        $duplicates = [];
        $seen_ids = [];
        $seen_fingerprints = [];

        foreach ($events as $index => $event) {
            if (!is_array($event)) continue;
            $id = self::text($event['event_id'] ?? '');
            $fingerprint = self::text($event['fingerprint'] ?? '');
            if ($id === '' || $fingerprint === '') continue;

            if (isset($seen_ids[$id]) || isset($seen_fingerprints[$fingerprint])) {
                $duplicates[] = [
                    'event_id' => $id,
                    'duplicate_index' => $index,
                    'kept_index' => $seen_ids[$id] ?? $seen_fingerprints[$fingerprint],
                ];
                continue;
            }
            $seen_ids[$id] = $index;
            $seen_fingerprints[$fingerprint] = $index;
            $unique[] = $event;
        }

        return ['events' => $unique, 'duplicates' => $duplicates];
    }

    public static function event_id(array $event) {
        $stable = [
            'event_type' => self::key($event['event_type'] ?? ''),
            'event_ref' => self::text($event['event_ref'] ?? ''),
        ];
        return 'bge-' . substr(hash('sha256', json_encode($stable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 24);
    }

    public static function fingerprint(array $event) {
        $stable = [
            'contract_version' => self::text($event['contract_version'] ?? ''),
            'event_id' => self::text($event['event_id'] ?? ''),
            'event_type' => self::key($event['event_type'] ?? ''),
            'occurred_at' => self::text($event['occurred_at'] ?? ''),
            'actor_ref' => self::nullable_text($event['actor_ref'] ?? null),
            'session_ref' => self::nullable_text($event['session_ref'] ?? null),
            'lineage' => self::canonicalize($event['lineage'] ?? []),
            'metadata' => self::canonicalize($event['metadata'] ?? []),
        ];
        return 'sha256:' . hash('sha256', json_encode($stable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function supported_event_types() {
        return self::$event_types;
    }

    private static function normalize_lineage($raw, $event_type, array &$errors) {
        if (!is_array($raw)) {
            $errors[] = 'lineage';
            return [];
        }

        $channel = self::key($raw['channel'] ?? '');
        if (!in_array($channel, self::$channels, true)) $errors[] = 'lineage.channel';

        $format = self::key($raw['format'] ?? 'unknown');
        if (!in_array($format, self::$formats, true)) $errors[] = 'lineage.format';

        $intent = self::key($raw['intent'] ?? '');
        $keyword_cluster = self::slug($raw['keyword_cluster'] ?? '');
        $research_id = self::nullable_safe_id($raw['research_id'] ?? null, 'research_id', $errors);
        $opportunity_id = self::nullable_safe_id($raw['opportunity_id'] ?? null, 'opportunity_id', $errors);
        $brief_id = self::nullable_safe_id($raw['brief_id'] ?? null, 'brief_id', $errors);
        $draft_id = self::nullable_safe_id($raw['draft_id'] ?? null, 'draft_id', $errors);

        $utm_campaign = self::slug($raw['utm_campaign'] ?? '');
        $utm_content = self::slug($raw['utm_content'] ?? '');
        $utm_source = self::slug($raw['utm_source'] ?? '');
        $utm_medium = self::slug($raw['utm_medium'] ?? '');

        if ($event_type === 'intent_detected') {
            if ($intent === '') $errors[] = 'lineage.intent';
            if ($keyword_cluster === '') $errors[] = 'lineage.keyword_cluster';
        }

        if (in_array($event_type, self::$campaign_required_events, true)) {
            if ($utm_campaign === '') $errors[] = 'lineage.utm_campaign';
            if ($utm_content === '') $errors[] = 'lineage.utm_content';
            if ($utm_source === '') $errors[] = 'lineage.utm_source';
            if ($utm_medium === '') $errors[] = 'lineage.utm_medium';
        }

        return [
            'research_id' => $research_id,
            'opportunity_id' => $opportunity_id,
            'brief_id' => $brief_id,
            'draft_id' => $draft_id,
            'intent' => $intent ?: null,
            'keyword_cluster' => $keyword_cluster ?: null,
            'channel' => $channel,
            'format' => $format,
            'utm_source' => $utm_source ?: null,
            'utm_medium' => $utm_medium ?: null,
            'utm_campaign' => $utm_campaign ?: null,
            'utm_content' => $utm_content ?: null,
        ];
    }

    private static function normalize_metadata($raw, array &$errors) {
        if ($raw === null) return [];
        if (!is_array($raw)) {
            $errors[] = 'metadata';
            return [];
        }

        $allowed = ['surface', 'page_key', 'approval_state', 'publication_ref', 'experiment_ref'];
        $out = [];
        foreach ($raw as $key => $value) {
            $key = self::key($key);
            if (!in_array($key, $allowed, true)) {
                $errors[] = 'metadata_field:' . $key;
                continue;
            }
            if (!is_scalar($value) && $value !== null) {
                $errors[] = 'metadata_value:' . $key;
                continue;
            }
            $text = $value === null ? null : self::text($value);
            if ($text !== null && self::looks_like_pii($text)) {
                $errors[] = 'metadata_pii:' . $key;
                continue;
            }
            $out[$key] = $text;
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    private static function nullable_safe_id($value, $field, array &$errors) {
        $value = self::nullable_text($value);
        if ($value === null) return null;
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\-]{2,127}$/', $value) || self::looks_like_pii($value)) {
            $errors[] = 'lineage.' . $field;
            return null;
        }
        return $value;
    }

    private static function opaque_ref($value, $anonymous_only) {
        $value = self::text($value);
        if ($value === '' || strlen($value) > 128 || self::looks_like_pii($value)) return false;
        if ($anonymous_only) {
            return (bool) preg_match('/^(anon|sess)_[a-f0-9]{16,64}$/', $value);
        }
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\-]{2,127}$/', $value);
    }

    private static function looks_like_pii($value) {
        $value = trim((string) $value);
        if ($value === '') return false;
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) return true;
        if (filter_var($value, FILTER_VALIDATE_IP)) return true;
        if (preg_match('/https?:\/\//i', $value)) return true;
        if (preg_match('/(^|\s)@[A-Za-z0-9_]{2,}/', $value)) return true;
        if (preg_match('/\b0x[a-fA-F0-9]{40}\b/', $value)) return true;
        if (preg_match('/\b[13][a-km-zA-HJ-NP-Z1-9]{25,34}\b/', $value)) return true;
        if (preg_match('/\+?\d[\d\s().-]{8,}\d/', $value)) return true;
        return false;
    }

    private static function sort_events(array &$events) {
        usort($events, function ($a, $b) {
            $ta = strtotime((string) ($a['occurred_at'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['occurred_at'] ?? '')) ?: 0;
            if ($ta !== $tb) return $ta <=> $tb;
            return strcmp((string) ($a['event_id'] ?? ''), (string) ($b['event_id'] ?? ''));
        });
    }

    private static function failure(array $errors) {
        $errors = array_values(array_unique($errors));
        sort($errors, SORT_STRING);
        return ['valid' => false, 'errors' => $errors, 'event' => null];
    }

    private static function canonicalize($value) {
        if (!is_array($value)) return $value;
        if (array_keys($value) === range(0, count($value) - 1)) {
            return array_map([__CLASS__, 'canonicalize'], $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $entry) $value[$key] = self::canonicalize($entry);
        return $value;
    }

    private static function parse_time($value) {
        if (is_numeric($value)) return (int) $value;
        if (!is_string($value) || trim($value) === '') return 0;
        $ts = strtotime($value);
        return $ts ?: 0;
    }

    private static function slug($value) {
        $value = strtolower(self::text($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value);
        return trim($value, '-');
    }

    private static function key($value) {
        $value = strtolower(self::text($value));
        return preg_replace('/[^a-z0-9_\-]/', '', $value);
    }

    private static function nullable_text($value) {
        if ($value === null || $value === '') return null;
        return self::text($value);
    }

    private static function text($value) {
        if (!is_scalar($value)) return '';
        $value = strip_tags((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private static function now($now) {
        if ($now === null) return time();
        return is_numeric($now) ? (int) $now : time();
    }
}
