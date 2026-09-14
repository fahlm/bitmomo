<?php

/**
 * Deterministic, network-free BTC demand classifier and opportunity builder.
 *
 * P1 is research/tooling only. This class does not call platform APIs, publish,
 * reply, DM, like, follow, browse, or generate outbound copy.
 */
final class Bitmomo_Intent_Radar_V1 {
    const CONTRACT_VERSION = 'intent-opportunity-v1';
    const RADAR_VERSION = 'intent-radar-v1';
    const CONFIDENCE_SEMANTICS = 'classification_confidence_not_conversion_probability';
    const MAX_FUTURE_SECONDS = 300;
    const MAX_AGE_SECONDS = 172800; // 48h.
    const DEFAULT_COOLDOWN_SECONDS = 21600; // 6h.

    private static $intent_weights = [
        'explicit_signal' => 44,
        'long_short' => 42,
        'entry_exit' => 40,
        'ai_research' => 38,
        'breakout_support_resistance' => 34,
        'analysis_prediction' => 32,
        'why_move' => 30,
        'event_reaction' => 26,
    ];

    private static $intent_patterns = [
        'explicit_signal' => [
            'btc signal', 'bitcoin signal', 'signal btc', 'signal bitcoin',
            'sinyal btc', 'sinyal bitcoin', 'btc signals', 'bitcoin signals',
        ],
        'long_short' => [
            'long or short', 'short or long', 'long/short', 'long atau short',
            'btc long atau short', 'bitcoin long atau short', 'bullish or bearish',
            'bullish atau bearish',
        ],
        'entry_exit' => [
            'btc entry', 'bitcoin entry', 'entry point', 'where to buy',
            'where to enter', 'when to buy', 'when to sell', 'kapan masuk',
            'kapan beli', 'kapan jual', 'kapan keluar', 'entry dimana',
            'entry di mana', 'take profit', 'stop loss',
        ],
        'analysis_prediction' => [
            'btc analysis', 'bitcoin analysis', 'analysis today', 'analisa bitcoin',
            'analisis bitcoin', 'analisa btc', 'analisis btc', 'price prediction',
            'bitcoin prediction', 'btc prediction', 'prediksi bitcoin', 'prediksi btc',
            'btc next move', 'bitcoin next move', 'next move btc', 'next move bitcoin',
            'arah btc', 'btc naik atau turun', 'bitcoin naik atau turun',
        ],
        'breakout_support_resistance' => [
            'btc breakout', 'bitcoin breakout', 'breakout btc', 'breakout bitcoin',
            'btc support', 'bitcoin support', 'support btc', 'support bitcoin',
            'btc resistance', 'bitcoin resistance', 'resistance btc', 'resistance bitcoin',
            'btc resisten', 'bitcoin resisten', 'level btc', 'level bitcoin',
        ],
        'why_move' => [
            'why btc', 'why bitcoin', 'why is btc', 'why is bitcoin', 'why did btc',
            'why did bitcoin', 'why btc pumping', 'why bitcoin pumping',
            'why btc dumping', 'why bitcoin dumping', 'kenapa btc', 'kenapa bitcoin',
            'kenapa btc naik', 'kenapa bitcoin naik', 'kenapa btc turun',
            'kenapa bitcoin turun',
        ],
        'event_reaction' => [
            'btc fed', 'bitcoin fed', 'btc fomc', 'bitcoin fomc', 'btc cpi',
            'bitcoin cpi', 'btc etf', 'bitcoin etf', 'btc nfp', 'bitcoin nfp',
            'btc rate cut', 'bitcoin rate cut', 'btc rate hike', 'bitcoin rate hike',
            'btc sec', 'bitcoin sec',
        ],
        'ai_research' => [
            'ai btc', 'ai bitcoin', 'ai crypto analysis', 'ai trading bitcoin',
            'ai trading btc', 'ai signal btc', 'ai signal bitcoin',
            'machine learning bitcoin', 'machine learning btc', 'bitcoin ai model',
            'btc ai model', 'ai research crypto', 'ai research bitcoin',
        ],
    ];

    private static $intent_research_terms = [
        'explicit_signal' => ['bitcoin', 'btc', 'market-intelligence', 'direction', 'bias'],
        'long_short' => ['bitcoin', 'btc', 'direction', 'bias', 'scenario'],
        'entry_exit' => ['bitcoin', 'btc', 'risk', 'invalidation', 'support', 'resistance'],
        'analysis_prediction' => ['bitcoin', 'btc', 'market-intelligence', 'forecast', 'scenario'],
        'breakout_support_resistance' => ['bitcoin', 'btc', 'structure', 'support', 'resistance', 'breakout'],
        'why_move' => ['bitcoin', 'btc', 'drivers', 'market-intelligence', 'macro', 'derivatives'],
        'event_reaction' => ['bitcoin', 'btc', 'event', 'macro', 'etf', 'fed', 'cpi', 'nfp'],
        'ai_research' => ['ai', 'research', 'model', 'bitcoin', 'btc'],
    ];

    public static function analyze(array $observation, array $research_items = [], $now = null) {
        $now = self::now($now);
        $normalized = self::normalize_observation($observation, $now);
        if (!$normalized['valid']) return $normalized;

        $obs = $normalized['observation'];
        $text = $obs['normalized_text'];
        $matches = self::match_intents($text);
        $primary_intent = self::primary_intent($matches);
        $language = self::detect_language($text);
        $freshness = self::freshness($obs['observed_at'], $now);
        $spam = self::spam_signals($text);

        $score_parts = [
            'intent' => $primary_intent ? self::$intent_weights[$primary_intent] : 0,
            'btc_relevance' => self::btc_relevance_score($text),
            'question_or_request' => self::request_score($text),
            'time_sensitivity' => self::time_sensitivity_score($text),
            'freshness' => self::freshness_score($freshness['age_seconds']),
            'language_fit' => in_array($language, ['id', 'en'], true) ? 5 : 0,
            'engagement_potential' => self::engagement_score($text),
            'spam_penalty' => -1 * $spam['penalty'],
        ];

        $score = max(0, min(100, array_sum($score_parts)));
        $confidence = self::classification_confidence($primary_intent, $matches, $text);
        $outbound_candidate = self::is_outbound_candidate($obs, $score_parts);
        $policy = self::action_policy($score, $primary_intent, $spam['hard_block'], $outbound_candidate);

        $opportunity = [
            'contract_version' => self::CONTRACT_VERSION,
            'radar_version' => self::RADAR_VERSION,
            'opportunity_id' => self::opportunity_id($obs['source'], $obs['source_ref']),
            'fingerprint' => '',
            'source' => $obs['source'],
            'source_ref' => $obs['source_ref'],
            'source_url' => $obs['source_url'],
            'surface' => $obs['surface'],
            'text' => $obs['text'],
            'author' => ['ref' => $obs['author_ref']],
            'intent' => [
                'primary' => $primary_intent,
                'matches' => $matches,
            ],
            'score' => $score,
            'score_components' => $score_parts,
            'confidence' => [
                'value' => $confidence,
                'semantics' => self::CONFIDENCE_SEMANTICS,
            ],
            'matched_terms' => self::flatten_matched_terms($matches),
            'detected_language' => $language,
            'freshness' => $freshness,
            'spam' => $spam,
            'research_links' => [],
            'suggested_action' => $policy['suggested_action'],
            'priority' => $policy['priority'],
            'approval_required' => $policy['approval_required'],
            'auto_outbound_permitted' => false,
            'cooldown_key' => self::cooldown_key($obs, $primary_intent),
            'observed_at' => $obs['observed_at'],
            'created_at' => gmdate('c', $now),
        ];

        // Research can strengthen an already-recognized demand opportunity, but
        // must never transform generic BTC chatter into demand.
        if ($primary_intent !== null) {
            $opportunity = self::enrich_with_research($opportunity, $research_items);
        }

        if ($primary_intent !== null && $opportunity['research_links']) {
            $opportunity['score_components']['research_fit'] = 5;
            $opportunity['score'] = min(100, $opportunity['score'] + 5);
            $policy = self::action_policy(
                $opportunity['score'],
                $primary_intent,
                $spam['hard_block'],
                $outbound_candidate
            );
            $opportunity['suggested_action'] = $policy['suggested_action'];
            $opportunity['priority'] = $policy['priority'];
            $opportunity['approval_required'] = $policy['approval_required'];
        } else {
            $opportunity['score_components']['research_fit'] = 0;
        }

        $opportunity['fingerprint'] = self::fingerprint($opportunity);
        return ['valid' => true, 'errors' => [], 'opportunity' => $opportunity];
    }

    /** Normalize fixture/mock observations only; no platform request is made. */
    public static function from_x_fixture(array $raw, array $research_items = [], $now = null) {
        return self::analyze([
            'source' => 'x',
            'source_ref' => $raw['id'] ?? '',
            'source_url' => $raw['url'] ?? null,
            'surface' => $raw['surface'] ?? 'post',
            'text' => $raw['text'] ?? '',
            'author_ref' => $raw['author_ref'] ?? '',
            'observed_at' => $raw['created_at'] ?? '',
        ], $research_items, $now);
    }

    public static function from_youtube_fixture(array $raw, array $research_items = [], $now = null) {
        $text = trim((string) ($raw['text'] ?? ''));
        if ($text === '') $text = trim((string) ($raw['title'] ?? '') . ' ' . (string) ($raw['description'] ?? ''));
        return self::analyze([
            'source' => 'youtube',
            'source_ref' => $raw['id'] ?? '',
            'source_url' => $raw['url'] ?? null,
            'surface' => $raw['surface'] ?? 'search_result',
            'text' => $text,
            'author_ref' => $raw['author_ref'] ?? '',
            'observed_at' => $raw['published_at'] ?? '',
        ], $research_items, $now);
    }

    public static function deduplicate(array $opportunities) {
        $items = [];
        $duplicates = [];
        $seen_ids = [];
        $seen_fingerprints = [];

        foreach ($opportunities as $index => $item) {
            if (!is_array($item)) continue;
            $id = self::text($item['opportunity_id'] ?? '');
            $fingerprint = self::text($item['fingerprint'] ?? '');
            if ($id === '' || $fingerprint === '') continue;

            if (isset($seen_ids[$id]) || isset($seen_fingerprints[$fingerprint])) {
                $duplicates[] = [
                    'opportunity_id' => $id,
                    'duplicate_index' => $index,
                    'kept_index' => $seen_ids[$id] ?? $seen_fingerprints[$fingerprint],
                ];
                continue;
            }

            $seen_ids[$id] = $index;
            $seen_fingerprints[$fingerprint] = $index;
            $items[] = $item;
        }

        return ['items' => $items, 'duplicates' => $duplicates];
    }

    /** Cooldown state shape: [cooldown_key => last_action_iso8601]. */
    public static function in_cooldown(array $opportunity, array $cooldown_state, $now = null, $seconds = null) {
        $now = self::now($now);
        $seconds = $seconds === null ? self::DEFAULT_COOLDOWN_SECONDS : max(0, (int) $seconds);
        $key = self::text($opportunity['cooldown_key'] ?? '');
        if ($key === '' || empty($cooldown_state[$key])) return false;
        $last = strtotime((string) $cooldown_state[$key]);
        if (!$last || $last > $now + self::MAX_FUTURE_SECONDS) return false;
        return ($now - $last) < $seconds;
    }

    public static function enrich_with_research(array $opportunity, array $research_items) {
        $intent = self::text($opportunity['intent']['primary'] ?? '');
        if ($intent === '') {
            $opportunity['research_links'] = [];
            return $opportunity;
        }

        $query_terms = self::tokens(strtolower(self::text($opportunity['text'] ?? '')));
        foreach ((array) (self::$intent_research_terms[$intent] ?? []) as $term) $query_terms[] = strtolower($term);
        $query_terms = array_values(array_unique($query_terms));

        $candidates = [];
        foreach ($research_items as $item) {
            if (!is_array($item) || empty($item['distribution']['eligible'])) continue;
            $research_id = self::text($item['research_id'] ?? '');
            $fingerprint = self::text($item['fingerprint'] ?? '');
            if ($research_id === '' || $fingerprint === '') continue;

            $parts = [$item['title'] ?? '', $item['summary'] ?? ''];
            foreach ((array) ($item['topics'] ?? []) as $value) $parts[] = $value;
            foreach ((array) ($item['tags'] ?? []) as $value) $parts[] = $value;
            $haystack = strtolower(self::text(implode(' ', array_map('strval', $parts))));
            $matched = array_values(array_unique(array_intersect($query_terms, self::tokens($haystack))));
            if (!$matched) continue;

            $match_score = min(100, count($matched) * 15);
            if (strpos($haystack, 'bitcoin') !== false || strpos($haystack, 'btc') !== false) {
                $match_score = min(100, $match_score + 10);
            }

            $candidates[] = [
                'research_id' => $research_id,
                'fingerprint' => $fingerprint,
                'title' => self::text($item['title'] ?? ''),
                'match_score' => $match_score,
                'matched_terms' => $matched,
            ];
        }

        usort($candidates, function ($a, $b) {
            if ($a['match_score'] === $b['match_score']) return strcmp($a['research_id'], $b['research_id']);
            return $b['match_score'] <=> $a['match_score'];
        });
        $opportunity['research_links'] = array_slice($candidates, 0, 3);
        return $opportunity;
    }

    public static function fingerprint(array $opportunity) {
        $stable = [
            'contract_version' => self::text($opportunity['contract_version'] ?? ''),
            'opportunity_id' => self::text($opportunity['opportunity_id'] ?? ''),
            'source' => self::text($opportunity['source'] ?? ''),
            'source_ref' => self::text($opportunity['source_ref'] ?? ''),
            'normalized_text' => self::normalize_text($opportunity['text'] ?? ''),
        ];
        return 'sha256:' . hash('sha256', json_encode($stable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function normalize_observation(array $source, $now) {
        $errors = [];
        $platform = self::key($source['source'] ?? '');
        if (!in_array($platform, ['x', 'youtube'], true)) $errors[] = 'source';

        $source_ref = self::text($source['source_ref'] ?? '');
        if ($source_ref === '') $errors[] = 'source_ref';

        $text = self::text($source['text'] ?? '');
        if ($text === '') $errors[] = 'text';

        $observed_at = self::valid_time($source['observed_at'] ?? '', $now);
        if ($observed_at === null) $errors[] = 'observed_at';
        if ($observed_at !== null && ($now - strtotime($observed_at)) > self::MAX_AGE_SECONDS) $errors[] = 'stale_observation';

        if ($errors) return self::failure($errors);

        return [
            'valid' => true,
            'errors' => [],
            'observation' => [
                'source' => $platform,
                'source_ref' => $source_ref,
                'source_url' => self::nullable_text($source['source_url'] ?? null),
                'surface' => self::key($source['surface'] ?? 'unknown') ?: 'unknown',
                'text' => $text,
                'normalized_text' => self::normalize_text($text),
                'author_ref' => self::text($source['author_ref'] ?? ''),
                'observed_at' => $observed_at,
            ],
        ];
    }

    private static function match_intents($text) {
        $matches = [];
        foreach (self::$intent_patterns as $intent => $patterns) {
            $matched = [];
            foreach ($patterns as $pattern) if (strpos($text, $pattern) !== false) $matched[] = $pattern;
            if ($matched) {
                sort($matched, SORT_STRING);
                $matches[$intent] = $matched;
            }
        }
        ksort($matches, SORT_STRING);
        return $matches;
    }

    private static function primary_intent(array $matches) {
        $best = null;
        $best_weight = -1;
        foreach ($matches as $intent => $terms) {
            $weight = self::$intent_weights[$intent] ?? 0;
            if ($weight > $best_weight || ($weight === $best_weight && strcmp($intent, (string) $best) < 0)) {
                $best = $intent;
                $best_weight = $weight;
            }
        }
        return $best;
    }

    private static function btc_relevance_score($text) {
        if (preg_match('/\b(bitcoin|btc|xbt)\b/', $text)) return 16;
        if (preg_match('/\bcrypto\b/', $text)) return 4;
        return 0;
    }

    private static function request_score($text) {
        if (strpos($text, '?') !== false) return 10;
        $terms = [
            'anyone know', 'anyone have', 'looking for', 'need a ', 'need an ',
            'what do you think', 'should i', 'where should', 'when should',
            'ada yang tahu', 'ada yang punya', 'lagi cari', 'butuh ', 'menurut kalian',
            'menurut kamu', 'sebaiknya', 'gimana btc', 'bagaimana btc',
        ];
        foreach ($terms as $term) if (strpos($text, $term) !== false) return 8;
        return 0;
    }

    private static function time_sensitivity_score($text) {
        $terms = ['today', 'now', 'right now', 'tonight', 'this morning', 'this evening', 'hari ini', 'sekarang', 'malam ini', 'pagi ini', 'next move'];
        foreach ($terms as $term) if (strpos($text, $term) !== false) return 5;
        return 0;
    }

    private static function engagement_score($text) {
        $score = strpos($text, '?') !== false ? 3 : 0;
        foreach (['signal', 'analysis', 'analisa', 'analisis', 'long', 'short', 'entry', 'support', 'resistance', 'prediksi', 'prediction'] as $term) {
            if (strpos($text, $term) !== false) { $score += 2; break; }
        }
        return min(5, $score);
    }

    private static function freshness($observed_at, $now) {
        $age = max(0, $now - strtotime((string) $observed_at));
        if ($age <= 900) $band = 'live';
        elseif ($age <= 3600) $band = 'recent';
        elseif ($age <= 21600) $band = 'current';
        elseif ($age <= 86400) $band = 'aging';
        else $band = 'old';
        return ['age_seconds' => $age, 'band' => $band];
    }

    private static function freshness_score($age) {
        if ($age <= 900) return 15;
        if ($age <= 3600) return 12;
        if ($age <= 21600) return 8;
        if ($age <= 86400) return 3;
        return 0;
    }

    private static function classification_confidence($primary, array $matches, $text) {
        if ($primary === null) return 20;
        $count = count($matches[$primary] ?? []);
        $confidence = 72 + min(16, max(0, $count - 1) * 8);
        if (strpos($text, '?') !== false) $confidence += 4;
        return min(95, $confidence);
    }

    private static function spam_signals($text) {
        $penalty = 0;
        $reasons = [];
        $patterns = [
            'guaranteed profit' => 30,
            'guaranteed profits' => 30,
            '100% win rate' => 35,
            '100x' => 20,
            'free money' => 25,
            'airdrop' => 15,
            'giveaway' => 15,
            'dm me' => 15,
            'join vip' => 20,
            'vip signal' => 20,
            'promo code' => 20,
            'referral code' => 20,
        ];
        foreach ($patterns as $pattern => $weight) {
            if (strpos($text, $pattern) !== false) {
                $penalty += $weight;
                $reasons[] = $pattern;
            }
        }
        if (preg_match_all('/https?:\/\//', $text) >= 2) {
            $penalty += 20;
            $reasons[] = 'multiple_urls';
        }
        if (preg_match('/(.)\1{7,}/', $text)) {
            $penalty += 15;
            $reasons[] = 'repeated_characters';
        }
        return [
            'penalty' => min(100, $penalty),
            'reasons' => array_values(array_unique($reasons)),
            'hard_block' => $penalty >= 40,
        ];
    }

    private static function is_outbound_candidate(array $obs, array $score_parts) {
        if (($score_parts['question_or_request'] ?? 0) <= 0) return false;
        if ($obs['source'] === 'x') return in_array($obs['surface'], ['post', 'comment', 'reply', 'unknown'], true);
        if ($obs['source'] === 'youtube') return $obs['surface'] === 'comment';
        return false;
    }

    private static function action_policy($score, $intent, $hard_block, $outbound_candidate) {
        if ($hard_block || $intent === null || $score < 35) {
            return ['priority' => 'ignore', 'suggested_action' => 'ignore', 'approval_required' => false];
        }

        if (!$outbound_candidate) {
            return [
                'priority' => $score >= 55 ? 'p1' : 'p2',
                'suggested_action' => 'monitor',
                'approval_required' => false,
            ];
        }

        if ($score >= 75) {
            return ['priority' => 'p0', 'suggested_action' => 'review_for_reply', 'approval_required' => true];
        }
        if ($score >= 55) {
            return ['priority' => 'p1', 'suggested_action' => 'review_for_reply', 'approval_required' => true];
        }
        return ['priority' => 'p2', 'suggested_action' => 'monitor', 'approval_required' => false];
    }

    private static function cooldown_key(array $obs, $intent) {
        $identity = $obs['author_ref'] !== '' ? $obs['author_ref'] : $obs['source_ref'];
        return 'cooldown:' . substr(hash('sha256', $obs['source'] . '|' . $identity . '|' . (string) $intent), 0, 24);
    }

    private static function opportunity_id($source, $source_ref) {
        return 'bio-' . substr(hash('sha256', $source . '|' . $source_ref), 0, 18);
    }

    private static function flatten_matched_terms(array $matches) {
        $terms = [];
        foreach ($matches as $rows) foreach ($rows as $term) $terms[] = $term;
        $terms = array_values(array_unique($terms));
        sort($terms, SORT_STRING);
        return $terms;
    }

    private static function detect_language($text) {
        $id_terms = [' yang ', ' dan ', ' atau ', 'kenapa', 'kapan', 'gimana', 'bagaimana', 'hari ini', 'sekarang', 'naik', 'turun', 'analisa', 'analisis', 'prediksi', 'sinyal'];
        $en_terms = [' the ', ' and ', ' or ', 'why ', 'when ', 'today', 'now', 'analysis', 'prediction', 'signal', 'where ', 'should '];
        $id = 0;
        $en = 0;
        $padded = ' ' . $text . ' ';
        foreach ($id_terms as $term) if (strpos($padded, $term) !== false) $id++;
        foreach ($en_terms as $term) if (strpos($padded, $term) !== false) $en++;
        if ($id === 0 && $en === 0) return 'unknown';
        return $id >= $en ? 'id' : 'en';
    }

    private static function tokens($text) {
        $parts = preg_split('/[^a-z0-9\-]+/i', strtolower((string) $text));
        $stop = ['the','and','or','a','an','is','are','to','of','in','on','for','with','yang','dan','atau','di','ke','dari','ini','itu'];
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (strlen($part) < 2 || in_array($part, $stop, true)) continue;
            $tokens[] = $part;
        }
        return array_values(array_unique($tokens));
    }

    private static function normalize_text($value) {
        return strtolower(self::text($value));
    }

    private static function failure(array $errors) {
        $errors = array_values(array_unique($errors));
        sort($errors, SORT_STRING);
        return ['valid' => false, 'errors' => $errors, 'opportunity' => null];
    }

    private static function valid_time($value, $now) {
        $value = self::text($value);
        if ($value === '') return null;
        $timestamp = strtotime($value);
        if (!$timestamp || $timestamp > ($now + self::MAX_FUTURE_SECONDS)) return null;
        return gmdate('c', $timestamp);
    }

    private static function text($value) {
        if (!is_scalar($value)) return '';
        $value = strip_tags((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private static function nullable_text($value) {
        if ($value === null) return null;
        $text = self::text($value);
        return $text === '' ? null : $text;
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
