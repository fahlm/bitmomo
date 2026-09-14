<?php
if (!defined('ABSPATH')) exit;

/**
 * Pure, deterministic Research Feed V1 projector.
 *
 * P0 deliberately has no WordPress hooks, persistence, REST routes, cron,
 * network calls, or publishing side effects. Callers must pass an already
 * canonical source record explicitly.
 */
final class Bitmomo_Research_Feed_V1 {
    const CONTRACT_VERSION = 'research-feed-v1';
    const CONFIDENCE_SEMANTICS = 'evidence_strength_not_probability';
    const MAX_FUTURE_SECONDS = 300;

    public static function from_btc_intelligence(array $source, $now = null) {
        $now = self::now($now);
        $errors = [];

        $status = self::key($source['status'] ?? '');
        if (!in_array($status, ['fresh', 'delayed'], true)) $errors[] = 'status';

        $price = self::finite_number($source['btc_reference_price'] ?? null);
        if ($price === null || $price <= 0) $errors[] = 'btc_reference_price';

        $bias = self::key($source['directional_bias'] ?? '');
        if (!in_array($bias, ['bullish', 'neutral', 'bearish'], true)) $errors[] = 'directional_bias';

        $confidence = self::confidence_value($source['confidence'] ?? null);
        if ($confidence === null) $errors[] = 'confidence';

        $quality = self::key($source['quality_status'] ?? '');
        if (!in_array($quality, ['complete', 'degraded'], true)) $errors[] = 'quality_status';

        $provenance = is_array($source['provenance'] ?? null) ? $source['provenance'] : [];
        $producer = self::text($provenance['source'] ?? '');
        $as_of = self::valid_time($provenance['as_of'] ?? '', $now);
        if ($producer === '') $errors[] = 'provenance.source';
        if ($as_of === null) $errors[] = 'provenance.as_of';

        $source_record_id = self::text($source['source_record_id'] ?? '');
        if ($source_record_id === '' && $as_of !== null) {
            $source_record_id = 'bitmomo-ai:' . strtotime($as_of);
        }
        if ($source_record_id === '') $errors[] = 'source_record_id';

        if ($errors) return self::failure($errors);

        $drivers = self::string_list($source['key_drivers'] ?? []);
        $findings = [
            ['type' => 'directional_bias', 'value' => $bias],
        ];
        foreach ($drivers as $driver) $findings[] = ['type' => 'primary_driver', 'value' => $driver];

        $opportunity = is_array($source['opportunity'] ?? null) ? $source['opportunity'] : [];
        $opportunity_state = self::key($opportunity['state'] ?? '');
        if (!in_array($opportunity_state, ['high', 'normal', 'low'], true)) $opportunity_state = null;
        $opportunity_version = self::text($opportunity['methodology_version'] ?? '');

        $market_state = self::key($source['market_state'] ?? '');
        if ($market_state === '') $market_state = null;
        $direction_strength = self::key($source['direction_strength'] ?? '');
        if ($direction_strength === '') $direction_strength = null;

        $versions = is_array($source['versions'] ?? null) ? $source['versions'] : [];
        $methodology_version = self::text($versions['engine'] ?? '');
        if ($methodology_version === '') $methodology_version = 'bitmomo-ai-unknown';

        $quality_reasons = [];
        if ($quality === 'degraded') $quality_reasons[] = 'source_quality_degraded';
        if ($status === 'delayed') $quality_reasons[] = 'source_delayed';

        $eligible = $quality === 'complete' && $status === 'fresh';
        $distribution_reasons = [];
        if ($quality !== 'complete') $distribution_reasons[] = 'requires_complete_quality';
        if ($status !== 'fresh') $distribution_reasons[] = 'requires_fresh_btc_intelligence';

        $metrics = [
            'btc_reference_price' => $price,
            'directional_bias' => $bias,
            'direction_strength' => $direction_strength,
            'market_state' => $market_state,
            'opportunity_state' => $opportunity_state,
            'opportunity_methodology_version' => $opportunity_version !== '' ? $opportunity_version : null,
        ];

        $item = [
            'contract_version' => self::CONTRACT_VERSION,
            'research_id' => self::btc_research_id($source_record_id),
            'fingerprint' => '',
            'source_type' => 'btc_intelligence',
            'title' => 'BTC Intelligence — ' . substr($as_of, 0, 16) . 'Z',
            'summary' => 'Canonical BTC intelligence snapshot for downstream research distribution tooling.',
            'findings' => $findings,
            'metrics' => $metrics,
            'confidence' => [
                'value' => $confidence,
                'label' => self::confidence_label($confidence),
                'semantics' => self::CONFIDENCE_SEMANTICS,
            ],
            'limitations' => [
                'Short-horizon market intelligence; not a guaranteed forecast or trading instruction.',
                'Opportunity, when present, describes relative activity and is not a directional signal.',
            ],
            'market_context' => [
                'asset' => 'BTC',
                'reference_price' => $price,
                'directional_bias' => $bias,
                'direction_strength' => $direction_strength,
                'market_state' => $market_state,
                'opportunity_state' => $opportunity_state,
            ],
            'event_links' => [],
            'topics' => self::string_list(array_merge(['bitcoin', 'market-intelligence'], (array) ($source['topics'] ?? []))),
            'tags' => self::string_list($source['tags'] ?? []),
            'provenance' => [
                'source_record_id' => $source_record_id,
                'as_of' => $as_of,
                'producer' => $producer,
                'methodology_version' => $methodology_version,
                'source_refs' => self::string_list(array_merge([$producer], (array) ($source['source_refs'] ?? []))),
            ],
            'data_quality' => [
                'status' => $quality,
                'freshness' => $status,
                'reasons' => $quality_reasons,
            ],
            'distribution' => [
                'eligible' => $eligible,
                'reasons' => $distribution_reasons,
            ],
        ];

        $item['fingerprint'] = self::fingerprint($item);
        return ['valid' => true, 'errors' => [], 'item' => $item];
    }

    public static function from_manual_research(array $source, $now = null) {
        $now = self::now($now);
        $errors = [];

        $slug = self::slug($source['research_slug'] ?? '');
        $version = self::slug($source['version'] ?? '');
        $title = self::text($source['title'] ?? '');
        $summary = self::text($source['summary'] ?? '');
        $as_of = self::valid_time($source['as_of'] ?? '', $now);
        $quality = self::key($source['quality_status'] ?? '');
        $methodology = self::text($source['methodology_version'] ?? ($source['methodology'] ?? ''));
        $source_refs = self::string_list($source['source_refs'] ?? []);
        $limitations = self::string_list($source['limitations'] ?? []);
        $findings = self::normalize_findings($source['findings'] ?? []);

        if ($slug === '') $errors[] = 'research_slug';
        if ($version === '') $errors[] = 'version';
        if ($title === '') $errors[] = 'title';
        if ($summary === '') $errors[] = 'summary';
        if ($as_of === null) $errors[] = 'as_of';
        if (!in_array($quality, ['complete', 'degraded'], true)) $errors[] = 'quality_status';
        if ($methodology === '') $errors[] = 'methodology';
        if (!$source_refs) $errors[] = 'source_refs';
        if (!$limitations) $errors[] = 'limitations';
        if (!$findings) $errors[] = 'findings';

        if ($errors) return self::failure($errors);

        $confidence = self::confidence_value($source['confidence'] ?? null, true);
        $source_record_id = 'manual-research:' . $slug . ':' . $version . ':' . strtotime($as_of);
        $eligible = $quality === 'complete';

        $item = [
            'contract_version' => self::CONTRACT_VERSION,
            'research_id' => self::manual_research_id($slug, $version, $source_record_id),
            'fingerprint' => '',
            'source_type' => 'manual_research',
            'title' => $title,
            'summary' => $summary,
            'findings' => $findings,
            'metrics' => self::normalize_map($source['metrics'] ?? []),
            'confidence' => [
                'value' => $confidence,
                'label' => $confidence === null ? null : self::confidence_label($confidence),
                'semantics' => self::CONFIDENCE_SEMANTICS,
            ],
            'limitations' => $limitations,
            'market_context' => self::normalize_map($source['market_context'] ?? []),
            'event_links' => self::string_list($source['event_links'] ?? []),
            'topics' => self::string_list($source['topics'] ?? []),
            'tags' => self::string_list($source['tags'] ?? []),
            'provenance' => [
                'source_record_id' => $source_record_id,
                'as_of' => $as_of,
                'producer' => 'bitmomo-research',
                'methodology_version' => $methodology,
                'source_refs' => $source_refs,
            ],
            'data_quality' => [
                'status' => $quality,
                'freshness' => 'fresh',
                'reasons' => $quality === 'degraded' ? ['source_quality_degraded'] : [],
            ],
            'distribution' => [
                'eligible' => $eligible,
                'reasons' => $eligible ? [] : ['requires_complete_quality'],
            ],
        ];

        $item['fingerprint'] = self::fingerprint($item);
        return ['valid' => true, 'errors' => [], 'item' => $item];
    }

    /** Keep the first unique fingerprint and report later duplicates. */
    public static function deduplicate(array $items) {
        $unique = [];
        $duplicates = [];
        $seen = [];

        foreach ($items as $index => $item) {
            if (!is_array($item) || self::text($item['fingerprint'] ?? '') === '') continue;
            $fingerprint = (string) $item['fingerprint'];
            if (isset($seen[$fingerprint])) {
                $duplicates[] = [
                    'fingerprint' => $fingerprint,
                    'kept_index' => $seen[$fingerprint],
                    'duplicate_index' => $index,
                    'research_id' => self::text($item['research_id'] ?? ''),
                ];
                continue;
            }
            $seen[$fingerprint] = $index;
            $unique[] = $item;
        }

        return ['items' => $unique, 'duplicates' => $duplicates];
    }

    public static function fingerprint(array $item) {
        $stable = $item;
        unset($stable['fingerprint'], $stable['distribution']);
        $stable = self::canonicalize($stable);
        return 'sha256:' . hash('sha256', json_encode($stable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function failure(array $errors) {
        $errors = array_values(array_unique($errors));
        sort($errors, SORT_STRING);
        return ['valid' => false, 'errors' => $errors, 'item' => null];
    }

    private static function confidence_value($value, $allow_null = false) {
        if (is_array($value)) $value = $value['value'] ?? null;
        if ($value === null || $value === '') return $allow_null ? null : null;
        if (!is_numeric($value)) return null;
        $value = (float) $value;
        if (!is_finite($value) || $value < 0 || $value > 100) return null;
        return round($value, 2);
    }

    private static function confidence_label($value) {
        return $value >= 70 ? 'high' : ($value >= 40 ? 'medium' : 'low');
    }

    private static function valid_time($value, $now) {
        $value = self::text($value);
        if ($value === '') return null;
        $timestamp = strtotime($value);
        if (!$timestamp || $timestamp > ($now + self::MAX_FUTURE_SECONDS)) return null;
        return gmdate('c', $timestamp);
    }

    private static function btc_research_id($source_record_id) {
        return 'bmr-btc-' . substr(hash('sha256', $source_record_id), 0, 16);
    }

    private static function manual_research_id($slug, $version, $source_record_id) {
        return 'bmr-' . $slug . '-' . $version . '-' . substr(hash('sha256', $source_record_id), 0, 8);
    }

    private static function normalize_findings($findings) {
        if (!is_array($findings)) return [];
        $out = [];
        foreach ($findings as $finding) {
            if (is_string($finding)) {
                $text = self::text($finding);
                if ($text !== '') $out[] = ['type' => 'finding', 'value' => $text];
                continue;
            }
            if (!is_array($finding)) continue;
            $type = self::slug($finding['type'] ?? 'finding');
            $value = $finding['value'] ?? null;
            if (is_scalar($value)) $value = self::text($value);
            if ($type === '' || $value === null || $value === '') continue;
            $row = ['type' => $type, 'value' => $value];
            if (isset($finding['label'])) $row['label'] = self::text($finding['label']);
            $out[] = $row;
        }
        usort($out, function ($a, $b) {
            return strcmp(json_encode(self::canonicalize($a)), json_encode(self::canonicalize($b)));
        });
        return $out;
    }

    private static function normalize_map($value) {
        return is_array($value) ? self::canonicalize($value) : [];
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

    private static function finite_number($value) {
        if (!is_numeric($value)) return null;
        $value = (float) $value;
        return is_finite($value) ? $value : null;
    }

    private static function canonicalize($value) {
        if (!is_array($value)) return $value;
        if (self::is_list($value)) {
            $out = [];
            foreach ($value as $entry) $out[] = self::canonicalize($entry);
            return $out;
        }
        $out = [];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) $out[$key] = self::canonicalize($value[$key]);
        return $out;
    }

    private static function is_list(array $value) {
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected) return false;
            $expected++;
        }
        return true;
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

    private static function slug($value) {
        $value = strtolower(self::text($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-');
    }

    private static function now($now) {
        if ($now === null) return time();
        return is_numeric($now) ? (int) $now : time();
    }
}
