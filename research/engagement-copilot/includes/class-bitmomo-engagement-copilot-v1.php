<?php

/**
 * Deterministic, evidence-grounded engagement draft builder.
 *
 * V1 never sends or publishes. It turns an already-approved-for-review Intent
 * Radar opportunity plus an evidence-locked Content Compiler brief into an
 * operator draft and explicit delivery-policy diagnostics.
 */
final class Bitmomo_Engagement_Copilot_V1 {
    const CONTRACT_VERSION = 'engagement-draft-v1';
    const COPILOT_VERSION = 'engagement-copilot-v1';

    public static function draft(array $opportunity, array $content_brief, array $interaction_context = [], $now = null) {
        $now = self::now($now);
        $errors = self::validate_inputs($opportunity, $content_brief);
        if ($errors) return self::failure($errors);

        $intent = self::key($opportunity['intent']['primary'] ?? '');
        $language = self::language($opportunity['detected_language'] ?? null);
        $evidence = self::select_evidence($content_brief);
        if (!$evidence['finding'] || !$evidence['limitation']) {
            return self::failure(['content_brief.evidence']);
        }

        $policy = self::delivery_policy($opportunity, $interaction_context);
        $strategy = self::strategy($intent, $language);
        $draft_text = self::render_draft($strategy, $evidence, $language);

        $research_id = self::text($content_brief['source_research']['research_id'] ?? '');
        $source = self::key($opportunity['source'] ?? '');
        $source_ref = self::text($opportunity['source_ref'] ?? '');
        $draft_id = 'bed-' . substr(hash('sha256', $source . '|' . $source_ref . '|' . $research_id . '|' . $intent), 0, 20);

        $draft = [
            'contract_version' => self::CONTRACT_VERSION,
            'copilot_version' => self::COPILOT_VERSION,
            'draft_id' => $draft_id,
            'fingerprint' => '',
            'status' => 'human_review_only',
            'source_context' => [
                'source' => $source,
                'source_ref' => $source_ref,
                'source_url' => self::nullable_text($opportunity['source_url'] ?? null),
                'opportunity_id' => self::text($opportunity['opportunity_id'] ?? ''),
                'score' => self::score($opportunity['score'] ?? null),
            ],
            'intent' => $intent,
            'language' => $language,
            'strategy' => $strategy,
            'draft_text' => $draft_text,
            'evidence_trace' => [
                'research_id' => $research_id,
                'research_fingerprint' => self::text($content_brief['source_research']['fingerprint'] ?? ''),
                'finding' => $evidence['finding'],
                'limitation' => $evidence['limitation'],
                'methodology_version' => self::text($content_brief['evidence']['provenance']['methodology_version'] ?? ''),
                'as_of' => self::text($content_brief['evidence']['provenance']['as_of'] ?? ''),
            ],
            'cta' => [
                'mode' => 'none_by_default',
                'direct_link_permitted' => false,
                'note' => 'Use account/profile discovery rather than unsolicited promotional links.',
            ],
            'delivery_policy' => $policy,
            'approval_required' => true,
            'auto_send_permitted' => false,
            'created_at' => gmdate('c', $now),
        ];

        $draft['fingerprint'] = self::fingerprint($draft);
        return ['valid' => true, 'errors' => [], 'draft' => $draft];
    }

    public static function deduplicate(array $drafts) {
        $unique = [];
        $duplicates = [];
        $seen_ids = [];
        $seen_fingerprints = [];

        foreach ($drafts as $index => $draft) {
            if (!is_array($draft)) continue;
            $id = self::text($draft['draft_id'] ?? '');
            $fingerprint = self::text($draft['fingerprint'] ?? '');
            if ($id === '' || $fingerprint === '') continue;

            if (isset($seen_ids[$id]) || isset($seen_fingerprints[$fingerprint])) {
                $duplicates[] = [
                    'draft_id' => $id,
                    'kept_index' => $seen_ids[$id] ?? $seen_fingerprints[$fingerprint],
                    'duplicate_index' => $index,
                ];
                continue;
            }

            $seen_ids[$id] = $index;
            $seen_fingerprints[$fingerprint] = $index;
            $unique[] = $draft;
        }

        return ['drafts' => $unique, 'duplicates' => $duplicates];
    }

    public static function fingerprint(array $draft) {
        $stable = [
            'contract_version' => self::text($draft['contract_version'] ?? ''),
            'draft_id' => self::text($draft['draft_id'] ?? ''),
            'intent' => self::text($draft['intent'] ?? ''),
            'language' => self::text($draft['language'] ?? ''),
            'draft_text' => self::text($draft['draft_text'] ?? ''),
            'evidence_trace' => $draft['evidence_trace'] ?? [],
            'delivery_policy' => $draft['delivery_policy'] ?? [],
        ];
        return 'sha256:' . hash('sha256', json_encode(self::canonicalize($stable), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function validate_inputs(array $opportunity, array $brief) {
        $errors = [];

        if (self::text($opportunity['suggested_action'] ?? '') !== 'review_for_reply') $errors[] = 'opportunity.suggested_action';
        if (empty($opportunity['approval_required'])) $errors[] = 'opportunity.approval_required';
        if (!empty($opportunity['auto_outbound_permitted'])) $errors[] = 'opportunity.auto_outbound_permitted';

        $source = self::key($opportunity['source'] ?? '');
        if (!in_array($source, ['x', 'youtube'], true)) $errors[] = 'opportunity.source';
        if (self::text($opportunity['source_ref'] ?? '') === '') $errors[] = 'opportunity.source_ref';
        if (self::key($opportunity['intent']['primary'] ?? '') === '') $errors[] = 'opportunity.intent';

        if (self::text($brief['contract_version'] ?? '') !== 'content-brief-v1') $errors[] = 'content_brief.contract_version';
        if (self::text($brief['status'] ?? '') !== 'draft_only') $errors[] = 'content_brief.status';
        if (empty($brief['human_review_required'])) $errors[] = 'content_brief.human_review_required';
        if (!empty($brief['auto_publish_permitted'])) $errors[] = 'content_brief.auto_publish_permitted';
        if (self::text($brief['source_research']['research_id'] ?? '') === '') $errors[] = 'content_brief.research_id';

        $linked_ids = [];
        foreach ((array) ($opportunity['research_links'] ?? []) as $link) {
            if (!is_array($link)) continue;
            $id = self::text($link['research_id'] ?? '');
            if ($id !== '') $linked_ids[] = $id;
        }
        $brief_research_id = self::text($brief['source_research']['research_id'] ?? '');
        if (!$linked_ids || !in_array($brief_research_id, $linked_ids, true)) $errors[] = 'research_lineage';

        if (!is_array($brief['evidence']['findings'] ?? null) || !$brief['evidence']['findings']) $errors[] = 'content_brief.findings';
        if (!is_array($brief['evidence']['limitations'] ?? null) || !$brief['evidence']['limitations']) $errors[] = 'content_brief.limitations';

        $errors = array_values(array_unique($errors));
        sort($errors, SORT_STRING);
        return $errors;
    }

    private static function select_evidence(array $brief) {
        $finding = null;
        foreach ((array) ($brief['evidence']['findings'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $value = self::text($row['value'] ?? '');
            if ($value !== '') {
                $finding = $value;
                break;
            }
        }

        $limitation = null;
        foreach ((array) ($brief['evidence']['limitations'] ?? []) as $value) {
            $value = self::text($value);
            if ($value !== '') {
                $limitation = $value;
                break;
            }
        }

        return ['finding' => $finding, 'limitation' => $limitation];
    }

    private static function strategy($intent, $language) {
        $id = [
            'explicit_signal' => ['angle' => 'signal_with_invalidation', 'opening' => 'Kalau mencari sinyal BTC, arah saja belum cukup. Yang penting adalah apa yang bisa membatalkan thesis-nya.'],
            'long_short' => ['angle' => 'direction_with_invalidation', 'opening' => 'Long atau short saja kurang berguna tanpa tahu kapan thesis pasar berubah.'],
            'entry_exit' => ['angle' => 'entry_requires_context', 'opening' => 'Sebelum menentukan entry, pisahkan dulu pembacaan pasar dari kondisi yang membatalkan thesis.'],
            'analysis_prediction' => ['angle' => 'evidence_over_prediction', 'opening' => 'Daripada menebak harga berikutnya, lebih berguna melihat bukti apa yang sedang mendukung pembacaan pasar.'],
            'breakout_support_resistance' => ['angle' => 'levels_need_context', 'opening' => 'Level support/resistance baru berguna kalau dibaca bersama konteks pasar dan invalidasinya.'],
            'why_move' => ['angle' => 'driver_explanation', 'opening' => 'Gerak BTC biasanya tidak cukup dijelaskan oleh satu headline; konteks datanya lebih penting.'],
            'event_reaction' => ['angle' => 'event_changes_setup', 'opening' => 'Untuk event seperti ini, fokusnya bukan hanya reaksi harga awal tetapi apakah setup pasar benar-benar berubah.'],
            'ai_research' => ['angle' => 'ai_as_testable_research', 'opening' => 'AI sebaiknya diperlakukan sebagai model yang bisa diuji, bukan oracle yang selalu benar.'],
        ];

        $en = [
            'explicit_signal' => ['angle' => 'signal_with_invalidation', 'opening' => 'If you are looking for a BTC signal, direction alone is not enough. What can invalidate the thesis matters more.'],
            'long_short' => ['angle' => 'direction_with_invalidation', 'opening' => 'Long or short is less useful without knowing what would invalidate the market view.'],
            'entry_exit' => ['angle' => 'entry_requires_context', 'opening' => 'Before thinking about entry, separate the market view from the condition that would invalidate it.'],
            'analysis_prediction' => ['angle' => 'evidence_over_prediction', 'opening' => 'Rather than guessing the next price, it is more useful to ask what evidence supports the current market view.'],
            'breakout_support_resistance' => ['angle' => 'levels_need_context', 'opening' => 'Support and resistance only become useful when they are read with market context and invalidation.'],
            'why_move' => ['angle' => 'driver_explanation', 'opening' => 'BTC moves are rarely explained well by a single headline; the surrounding market data matters more.'],
            'event_reaction' => ['angle' => 'event_changes_setup', 'opening' => 'For an event like this, the key question is not just the first price reaction but whether the market setup actually changed.'],
            'ai_research' => ['angle' => 'ai_as_testable_research', 'opening' => 'AI is more useful when treated as a testable model, not an oracle that is always right.'],
        ];

        $map = $language === 'id' ? $id : $en;
        return $map[$intent] ?? [
            'angle' => 'evidence_first',
            'opening' => $language === 'id'
                ? 'Pembacaan pasar lebih berguna jika dimulai dari bukti dan batasannya.'
                : 'A market view is more useful when it starts with evidence and its limitations.',
        ];
    }

    private static function render_draft(array $strategy, array $evidence, $language) {
        $finding = self::text($evidence['finding']);
        $limitation = self::text($evidence['limitation']);
        if ($language === 'id') {
            return self::text($strategy['opening'])
                . ' Temuan yang relevan dari Bitmomo: ' . $finding
                . ' Catatan penting: ' . $limitation;
        }
        return self::text($strategy['opening'])
            . ' Relevant Bitmomo finding: ' . $finding
            . ' Important caveat: ' . $limitation;
    }

    private static function delivery_policy(array $opportunity, array $context) {
        $source = self::key($opportunity['source'] ?? '');
        $discovery = self::key($context['discovery_mode'] ?? 'keyword_search');
        $opted_in = !empty($context['opted_in']);
        $reasons = ['human_review_required'];

        if ($source === 'x') {
            if ($discovery === 'keyword_search' && !$opted_in) $reasons[] = 'unsolicited_auto_reply_prohibited';
            $reasons[] = 'x_ai_auto_reply_requires_platform_approval';
        } elseif ($source === 'youtube') {
            $reasons[] = 'youtube_comment_spam_manual_review_required';
        }

        return [
            'delivery_class' => 'human_review_only',
            'discovery_mode' => $discovery,
            'opted_in' => $opted_in,
            'reasons' => array_values(array_unique($reasons)),
            'approval_required' => true,
            'auto_send_permitted' => false,
        ];
    }

    private static function language($value) {
        $value = self::key($value);
        return in_array($value, ['id', 'en'], true) ? $value : 'en';
    }

    private static function score($value) {
        if (!is_numeric($value)) return null;
        return max(0, min(100, (int) $value));
    }

    private static function failure(array $errors) {
        $errors = array_values(array_unique($errors));
        sort($errors, SORT_STRING);
        return ['valid' => false, 'errors' => $errors, 'draft' => null];
    }

    private static function canonicalize($value) {
        if (!is_array($value)) return $value;
        $keys = array_keys($value);
        $is_list = $keys === range(0, count($value) - 1);
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

    private static function nullable_text($value) {
        if ($value === null) return null;
        $value = self::text($value);
        return $value === '' ? null : $value;
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
