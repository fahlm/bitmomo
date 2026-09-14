<?php

/**
 * Deterministic, evidence-locked content brief compiler.
 *
 * P2 produces owned-channel planning objects only. It does not call an LLM,
 * publish, browse, contact users, or modify research conclusions.
 */
final class Bitmomo_Content_Compiler_V1 {
    const CONTRACT_VERSION = 'content-brief-v1';
    const COMPILER_VERSION = 'content-compiler-v1';

    private static $formats = [
        'x_post',
        'x_thread',
        'youtube_search',
        'youtube_short',
        'research_article',
    ];

    public static function compile(array $research, array $intent_context = [], $format = 'x_post', $now = null) {
        $now = self::now($now);
        $format = self::key($format);
        $errors = self::validate_research($research);
        if (!in_array($format, self::$formats, true)) $errors[] = 'format';
        if ($errors) return self::failure($errors);

        $intent = self::sanitize_intent_context($intent_context);
        $objective = self::objective($research, $intent);
        $framing = self::framing($research, $intent, $format, $objective);
        $evidence = self::evidence_lock($research);
        $research_id = self::text($research['research_id']);
        $intent_key = self::text($intent['primary'] ?? 'none') ?: 'none';

        $brief = [
            'contract_version' => self::CONTRACT_VERSION,
            'compiler_version' => self::COMPILER_VERSION,
            'brief_id' => self::brief_id($research_id, $intent_key, $format),
            'fingerprint' => '',
            'status' => 'draft_only',
            'channel' => self::channel_for_format($format),
            'format' => $format,
            'objective' => $objective,
            'source_research' => [
                'research_id' => $research_id,
                'fingerprint' => self::text($research['fingerprint']),
                'source_type' => self::text($research['source_type'] ?? ''),
            ],
            'intent_context' => $intent,
            'framing' => $framing,
            'evidence' => $evidence,
            'claim_policy' => self::claim_policy(),
            'cta' => self::cta_for_format($format),
            'attribution' => self::attribution($research_id, $intent_key, $format),
            'human_review_required' => true,
            'auto_publish_permitted' => false,
            'created_at' => gmdate('c', $now),
        ];

        $brief['fingerprint'] = self::fingerprint($brief);
        return ['valid' => true, 'errors' => [], 'brief' => $brief];
    }

    /**
     * Build a deterministic owned-content campaign from eligible research and
     * already-ranked Intent Radar queue output.
     */
    public static function compile_campaign(array $research_items, array $intent_queue = [], array $formats = [], $now = null) {
        $now = self::now($now);
        $formats = $formats ?: self::$formats;
        $formats = array_values(array_filter(array_unique(array_map([__CLASS__, 'key'], $formats)), function ($format) {
            return in_array($format, self::$formats, true);
        }));

        $intent_by_research = self::best_intent_by_research($intent_queue);
        $briefs = [];
        $rejected = [];

        foreach ($research_items as $index => $research) {
            if (!is_array($research)) {
                $rejected[] = ['index' => $index, 'errors' => ['research_shape']];
                continue;
            }

            $research_id = self::text($research['research_id'] ?? '');
            $intent = $research_id !== '' && isset($intent_by_research[$research_id])
                ? $intent_by_research[$research_id]
                : [];

            foreach ($formats as $format) {
                $compiled = self::compile($research, $intent, $format, $now);
                if (empty($compiled['valid'])) {
                    $rejected[] = [
                        'index' => $index,
                        'research_id' => $research_id ?: null,
                        'format' => $format,
                        'errors' => $compiled['errors'],
                    ];
                    continue;
                }
                $briefs[] = $compiled['brief'];
            }
        }

        $deduped = self::deduplicate($briefs);
        self::sort_briefs($deduped['briefs']);

        return [
            'campaign_version' => 'content-campaign-v1',
            'compiler_version' => self::COMPILER_VERSION,
            'generated_at' => gmdate('c', $now),
            'counts' => [
                'research_inputs' => count($research_items),
                'briefs_before_dedupe' => count($briefs),
                'briefs' => count($deduped['briefs']),
                'duplicates' => count($deduped['duplicates']),
                'rejected' => count($rejected),
            ],
            'briefs' => $deduped['briefs'],
            'duplicates' => $deduped['duplicates'],
            'rejected' => $rejected,
        ];
    }

    public static function deduplicate(array $briefs) {
        $unique = [];
        $duplicates = [];
        $seen_ids = [];
        $seen_fingerprints = [];

        foreach ($briefs as $index => $brief) {
            if (!is_array($brief)) continue;
            $id = self::text($brief['brief_id'] ?? '');
            $fingerprint = self::text($brief['fingerprint'] ?? '');
            if ($id === '' || $fingerprint === '') continue;

            if (isset($seen_ids[$id]) || isset($seen_fingerprints[$fingerprint])) {
                $duplicates[] = [
                    'brief_id' => $id,
                    'duplicate_index' => $index,
                    'kept_index' => $seen_ids[$id] ?? $seen_fingerprints[$fingerprint],
                ];
                continue;
            }

            $seen_ids[$id] = $index;
            $seen_fingerprints[$fingerprint] = $index;
            $unique[] = $brief;
        }

        return ['briefs' => $unique, 'duplicates' => $duplicates];
    }

    public static function fingerprint(array $brief) {
        $stable = [
            'contract_version' => self::text($brief['contract_version'] ?? ''),
            'brief_id' => self::text($brief['brief_id'] ?? ''),
            'source_research' => $brief['source_research'] ?? [],
            'intent_context' => $brief['intent_context'] ?? [],
            'format' => self::text($brief['format'] ?? ''),
            'framing' => $brief['framing'] ?? [],
            'evidence' => $brief['evidence'] ?? [],
        ];
        return 'sha256:' . hash('sha256', json_encode(self::canonicalize($stable), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function validate_research(array $research) {
        $errors = [];
        if (empty($research['distribution']['eligible'])) $errors[] = 'distribution.eligible';
        if (self::text($research['research_id'] ?? '') === '') $errors[] = 'research_id';
        if (self::text($research['fingerprint'] ?? '') === '') $errors[] = 'fingerprint';
        if (self::text($research['title'] ?? '') === '') $errors[] = 'title';
        if (!is_array($research['findings'] ?? null) || !$research['findings']) $errors[] = 'findings';
        if (!is_array($research['limitations'] ?? null) || !$research['limitations']) $errors[] = 'limitations';

        $provenance = is_array($research['provenance'] ?? null) ? $research['provenance'] : [];
        if (self::text($provenance['source_record_id'] ?? '') === '') $errors[] = 'provenance.source_record_id';
        if (self::text($provenance['as_of'] ?? '') === '') $errors[] = 'provenance.as_of';
        if (self::text($provenance['methodology_version'] ?? '') === '') $errors[] = 'provenance.methodology_version';

        $errors = array_values(array_unique($errors));
        sort($errors, SORT_STRING);
        return $errors;
    }

    /** Only safe intent aggregates survive. Third-party identity/text is dropped. */
    private static function sanitize_intent_context(array $intent) {
        $primary = self::key($intent['intent']['primary'] ?? ($intent['primary'] ?? ''));
        $allowed = [
            'explicit_signal', 'long_short', 'entry_exit', 'analysis_prediction',
            'breakout_support_resistance', 'why_move', 'event_reaction', 'ai_research',
        ];
        if (!in_array($primary, $allowed, true)) $primary = null;

        $terms = $intent['matched_terms'] ?? [];
        if (!is_array($terms)) $terms = [];
        $terms = self::string_list($terms);

        $score = isset($intent['score']) && is_numeric($intent['score'])
            ? max(0, min(100, (int) $intent['score']))
            : null;

        $language = self::key($intent['detected_language'] ?? '');
        if (!in_array($language, ['id', 'en'], true)) $language = null;

        return [
            'primary' => $primary,
            'matched_terms' => $terms,
            'score' => $score,
            'language' => $language,
            'search_keyword' => self::search_keyword($primary, $terms),
        ];
    }

    private static function objective(array $research, array $intent) {
        $primary = $intent['primary'] ?? null;
        $tags = array_map('strtolower', self::string_list($research['tags'] ?? []));
        $topics = array_map('strtolower', self::string_list($research['topics'] ?? []));

        if (in_array('accountability', $tags, true) || in_array('forecast-ledger', $tags, true)) return 'accountability';
        if ($primary === 'ai_research' || self::text($research['source_type'] ?? '') === 'manual_research') return 'authority';
        if ($primary === 'event_reaction' || in_array('event', $topics, true) || in_array('macro', $topics, true)) return 'event_intelligence';
        if ($primary !== null) return 'intent_capture';
        return 'authority';
    }

    private static function framing(array $research, array $intent, $format, $objective) {
        $primary = $intent['primary'] ?? null;
        $source_title = self::text($research['title']);
        $base_title = self::intent_title($primary, $source_title, $objective);

        $structures = [
            'x_post' => ['hook', 'one_evidence_point', 'implication', 'limitation', 'cta'],
            'x_thread' => ['hook', 'research_question', 'evidence_1', 'evidence_2', 'implication', 'limitations', 'cta'],
            'youtube_search' => ['query_match_hook', 'current_context', 'evidence', 'what_matters_next', 'limitations', 'cta'],
            'youtube_short' => ['first_2s_hook', 'single_finding', 'single_caveat', 'cta'],
            'research_article' => ['question', 'data_and_method', 'findings', 'interpretation', 'limitations', 'methodology_and_sources'],
        ];

        return [
            'source_title' => $source_title,
            'title_candidates' => self::title_candidates($base_title, $source_title, $format, $objective),
            'search_keyword' => in_array($format, ['youtube_search', 'youtube_short'], true) ? ($intent['search_keyword'] ?? null) : null,
            'structure' => $structures[$format],
            'voice' => 'concise_neutral_evidence_first',
            'positioning' => $objective === 'authority' ? 'bitmomo_research' : 'bitmomo_market_intelligence',
        ];
    }

    private static function title_candidates($base, $source_title, $format, $objective) {
        $titles = [];
        if ($format === 'research_article' && $objective === 'authority') {
            $titles[] = 'Bitmomo Tested It: ' . $source_title;
            $titles[] = $source_title;
        } elseif ($format === 'youtube_short') {
            $titles[] = self::shorten_title($base);
            $titles[] = self::shorten_title($source_title);
        } else {
            $titles[] = $base;
            $titles[] = $source_title;
        }
        return array_values(array_unique(array_filter(array_map([__CLASS__, 'text'], $titles))));
    }

    private static function intent_title($primary, $fallback, $objective) {
        $titles = [
            'explicit_signal' => 'Bitcoin Signal Today? What the Data Actually Says',
            'long_short' => 'BTC Long or Short? What the Data Says Now',
            'entry_exit' => 'BTC Entry Today? What to Check Before Acting',
            'analysis_prediction' => 'Bitcoin Analysis Today: What Matters Next',
            'breakout_support_resistance' => 'BTC Support and Resistance: What Matters Now',
            'why_move' => 'Why Is BTC Moving? What the Data Shows',
            'event_reaction' => 'BTC Event Reaction: What Changed and What Matters',
            'ai_research' => 'AI Bitcoin Research: What the Evidence Actually Shows',
        ];
        if (isset($titles[$primary])) return $titles[$primary];
        if ($objective === 'authority') return 'Bitmomo Research: ' . $fallback;
        return $fallback;
    }

    private static function evidence_lock(array $research) {
        return [
            'findings' => self::normalize_findings($research['findings']),
            'metrics' => self::scalar_map($research['metrics'] ?? []),
            'limitations' => self::string_list($research['limitations']),
            'provenance' => [
                'source_record_id' => self::text($research['provenance']['source_record_id'] ?? ''),
                'as_of' => self::text($research['provenance']['as_of'] ?? ''),
                'producer' => self::text($research['provenance']['producer'] ?? ''),
                'methodology_version' => self::text($research['provenance']['methodology_version'] ?? ''),
                'source_refs' => self::string_list($research['provenance']['source_refs'] ?? []),
            ],
        ];
    }

    private static function claim_policy() {
        return [
            'evidence_lock' => true,
            'allowed_claim_sources' => ['evidence.findings', 'evidence.metrics', 'evidence.provenance', 'evidence.limitations'],
            'forbidden' => [
                'invented_causality',
                'invented_statistics',
                'guaranteed_returns',
                'unsupported_win_rate',
                'confidence_as_probability',
                'opportunity_as_direction',
                'omitted_material_limitation',
            ],
        ];
    }

    private static function cta_for_format($format) {
        if ($format === 'research_article') {
            return [
                'enabled' => true,
                'type' => 'founding_member_whitelist',
                'destination_key' => 'bitmomo_founding_whitelist',
                'placement' => 'after_research',
            ];
        }
        return [
            'enabled' => true,
            'type' => 'founding_member_whitelist',
            'destination_key' => 'bitmomo_founding_whitelist',
            'placement' => 'end',
        ];
    }

    private static function attribution($research_id, $intent_key, $format) {
        $campaign = 'bmr-' . substr(hash('sha256', $research_id), 0, 10);
        $mediums = [
            'x_post' => 'social',
            'x_thread' => 'social',
            'youtube_search' => 'video',
            'youtube_short' => 'video',
            'research_article' => 'organic',
        ];
        return [
            'utm_source' => $format === 'research_article' ? 'bitmomo' : self::channel_for_format($format),
            'utm_medium' => $mediums[$format],
            'utm_campaign' => $campaign,
            'utm_content' => $format . '-' . $intent_key,
        ];
    }

    private static function best_intent_by_research(array $queue) {
        $candidates = [];
        foreach (['review', 'monitor'] as $bucket) {
            foreach ((array) ($queue[$bucket] ?? []) as $opportunity) {
                if (!is_array($opportunity)) continue;
                $score = isset($opportunity['score']) ? (int) $opportunity['score'] : 0;
                foreach ((array) ($opportunity['research_links'] ?? []) as $link) {
                    if (!is_array($link)) continue;
                    $research_id = self::text($link['research_id'] ?? '');
                    if ($research_id === '') continue;
                    if (!isset($candidates[$research_id]) || $score > $candidates[$research_id]['score']) {
                        $candidates[$research_id] = [
                            'intent' => $opportunity['intent'] ?? [],
                            'matched_terms' => $opportunity['matched_terms'] ?? [],
                            'score' => $score,
                            'detected_language' => $opportunity['detected_language'] ?? null,
                        ];
                    }
                }
            }
        }
        return $candidates;
    }

    private static function search_keyword($primary, array $terms) {
        $preferred = [
            'explicit_signal' => 'bitcoin signal today',
            'long_short' => 'btc long or short',
            'entry_exit' => 'btc entry',
            'analysis_prediction' => 'bitcoin analysis today',
            'breakout_support_resistance' => 'btc support resistance',
            'why_move' => 'why is btc moving',
            'event_reaction' => 'btc event reaction',
            'ai_research' => 'ai bitcoin research',
        ];
        if (isset($preferred[$primary])) return $preferred[$primary];
        return $terms ? $terms[0] : null;
    }

    private static function channel_for_format($format) {
        if (strpos($format, 'x_') === 0) return 'x';
        if (strpos($format, 'youtube_') === 0) return 'youtube';
        return 'bitmomo_research';
    }

    private static function brief_id($research_id, $intent_key, $format) {
        return 'bcb-' . substr(hash('sha256', $research_id . '|' . $intent_key . '|' . $format), 0, 20);
    }

    private static function normalize_findings(array $findings) {
        $out = [];
        foreach ($findings as $finding) {
            if (is_string($finding)) {
                $text = self::text($finding);
                if ($text !== '') $out[] = ['type' => 'finding', 'value' => $text];
            } elseif (is_array($finding)) {
                $type = self::key($finding['type'] ?? 'finding') ?: 'finding';
                $value = $finding['value'] ?? null;
                if (!is_scalar($value)) continue;
                $value = self::text($value);
                if ($value === '') continue;
                $out[] = ['type' => $type, 'value' => $value];
            }
        }
        return $out;
    }

    private static function scalar_map($value) {
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $key => $entry) {
            if (is_scalar($entry) || $entry === null) $out[self::key($key)] = $entry;
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    private static function string_list($values) {
        if (!is_array($values)) $values = [$values];
        $out = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) continue;
            $value = self::text($value);
            if ($value !== '') $out[] = $value;
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_STRING);
        return $out;
    }

    private static function sort_briefs(array &$briefs) {
        $order = ['research_article' => 0, 'youtube_search' => 1, 'x_thread' => 2, 'x_post' => 3, 'youtube_short' => 4];
        usort($briefs, function ($a, $b) use ($order) {
            $oa = $order[$a['format'] ?? ''] ?? 99;
            $ob = $order[$b['format'] ?? ''] ?? 99;
            if ($oa !== $ob) return $oa <=> $ob;
            return strcmp((string) ($a['brief_id'] ?? ''), (string) ($b['brief_id'] ?? ''));
        });
    }

    private static function shorten_title($title) {
        $title = self::text($title);
        return strlen($title) <= 70 ? $title : rtrim(substr($title, 0, 67)) . '...';
    }

    private static function failure(array $errors) {
        $errors = array_values(array_unique($errors));
        sort($errors, SORT_STRING);
        return ['valid' => false, 'errors' => $errors, 'brief' => null];
    }

    private static function canonicalize($value) {
        if (!is_array($value)) return $value;
        $is_list = array_keys($value) === range(0, count($value) - 1);
        if ($is_list) return array_map([__CLASS__, 'canonicalize'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $entry) $value[$key] = self::canonicalize($entry);
        return $value;
    }

    private static function text($value) {
        if (!is_scalar($value)) return '';
        $value = strip_tags((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private static function key($value) {
        $value = strtolower(self::text($value));
        return preg_replace('/[^a-z0-9_\-]/', '', $value);
    }

    private static function now($now) {
        if ($now === null) return time();
        return is_numeric($now) ? (int) $now : time();
    }
}
