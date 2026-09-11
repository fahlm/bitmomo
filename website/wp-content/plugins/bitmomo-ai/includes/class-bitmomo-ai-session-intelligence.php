<?php
if (!defined('ABSPATH')) exit;

/** Session identity, calendar, canonical history, and deterministic comparison. */
final class Bitmomo_AI_Session_Intelligence {
    const PRE_OPEN = 'us_pre_open';
    const POST_CLOSE = 'us_post_close';
    const MARKET_TIMEZONE = 'America/New_York';
    const SCHEMA_VERSION = '2.0';
    const HISTORY_OPTION = 'bitmomo_ai_editions';
    const HISTORY_LIMIT = 180;

    public static function normalize_session_type($value) {
        $value = sanitize_key((string) $value);
        if (in_array($value, [self::PRE_OPEN, 'morning'], true)) return self::PRE_OPEN;
        if (in_array($value, [self::POST_CLOSE, 'us_session'], true)) return self::POST_CLOSE;
        return self::POST_CLOSE;
    }

    public static function is_supported_session_type($value) {
        return in_array(sanitize_key((string) $value), [self::PRE_OPEN, self::POST_CLOSE, 'morning', 'us_session'], true);
    }

    public static function legacy_edition($session_type) {
        return self::normalize_session_type($session_type) === self::PRE_OPEN ? 'morning' : 'us_session';
    }

    public static function label($session_type) {
        return self::normalize_session_type($session_type) === self::PRE_OPEN ? 'US PRE-OPEN' : 'US POST-CLOSE';
    }

    public static function presentation_label($session_type, $us_market_status) {
        if (in_array((string) $us_market_status, ['weekend', 'holiday', 'closed'], true)) return 'US MARKETS CLOSED - BTC UPDATE';
        return self::label($session_type);
    }

    public static function opposite($session_type) {
        return self::normalize_session_type($session_type) === self::PRE_OPEN ? self::POST_CLOSE : self::PRE_OPEN;
    }

    public static function next_anchor($session_type, DateTimeImmutable $now = null) {
        $timezone = new DateTimeZone(self::MARKET_TIMEZONE);
        $now = $now ? $now->setTimezone($timezone) : new DateTimeImmutable('now', $timezone);
        list($hour, $minute) = self::anchor_time($session_type);
        $candidate = $now->setTime($hour, $minute, 0);
        if ($candidate <= $now) $candidate = $candidate->modify('+1 day');
        return $candidate;
    }

    public static function session_context($session_type, $generated_at = 'now') {
        $session_type = self::normalize_session_type($session_type);
        $timezone = new DateTimeZone(self::MARKET_TIMEZONE);
        $generated = $generated_at instanceof DateTimeImmutable
            ? $generated_at->setTimezone($timezone)
            : new DateTimeImmutable((string) $generated_at, $timezone);
        list($hour, $minute) = self::anchor_time($session_type);
        $anchor = $generated->setTime($hour, $minute, 0);

        if ($anchor > $generated->modify('+5 minutes')) $anchor = $anchor->modify('-1 day')->setTime($hour, $minute, 0);

        $market_day = self::market_day($anchor);

        return [
            'session_type' => $session_type,
            'session_label' => self::presentation_label($session_type, $market_day['us_market_status']),
            'session_anchor' => $anchor->format(DateTimeInterface::ATOM),
            'market_timezone' => self::MARKET_TIMEZONE,
            'us_market_status' => $market_day['us_market_status'],
            'market_calendar' => $market_day,
        ];
    }

    public static function market_day(DateTimeInterface $date) {
        $timezone = new DateTimeZone(self::MARKET_TIMEZONE);
        $local = (new DateTimeImmutable('@' . $date->getTimestamp()))->setTimezone($timezone);
        $day = $local->format('Y-m-d');
        $weekday = (int) $local->format('N');
        if ($weekday >= 6) {
            return ['date' => $day, 'status' => 'closed', 'us_market_status' => 'weekend', 'reason' => 'weekend', 'close_time' => null];
        }

        $holidays = self::holidays((int) $local->format('Y'));
        if (isset($holidays[$day])) {
            return ['date' => $day, 'status' => 'closed', 'us_market_status' => 'holiday', 'reason' => $holidays[$day], 'close_time' => null];
        }

        $early_close = in_array($day, self::early_closes((int) $local->format('Y')), true);
        return [
            'date' => $day,
            'status' => $early_close ? 'early_close' : 'open',
            'us_market_status' => $early_close ? 'early_close' : 'regular_session_day',
            'reason' => $early_close ? 'scheduled_early_close' : 'regular_session',
            'close_time' => $early_close ? '13:00' : '16:00',
        ];
    }

    public static function build_record($session_type, array $data, array $evaluation, array $gate, $generated_at = null, array $editions = null) {
        $generated_at = $generated_at ?: gmdate('c');
        $generated = new DateTimeImmutable((string) $generated_at);
        $context = self::session_context($session_type, $generated);
        $editions = null === $editions ? self::history() : $editions;
        $comparison = self::previous_valid($editions, $context['session_type'], $generated->getTimestamp());
        $drivers = class_exists('Bitmomo_AI_Key_Drivers') ? Bitmomo_AI_Key_Drivers::derive($evaluation) : [];
        $market_state = self::nullable_key($evaluation['market_state'] ?? ($data['market_state'] ?? null));
        $bias = self::allowed_key($evaluation['bias'] ?? 'neutral', ['bullish', 'neutral', 'bearish'], 'neutral');
        $strength = self::allowed_key($evaluation['direction_strength'] ?? 'neutral', ['strong_bearish', 'bearish', 'neutral', 'bullish', 'strong_bullish'], 'neutral');
        $confidence = max(0, min(100, (int) ($evaluation['confidence'] ?? 0)));
        $structure = self::nullable_key($data['structure']['state'] ?? null);
        $current = [
            'market_state' => $market_state,
            'directional_bias' => $bias,
            'direction_strength' => $strength,
            'confidence' => $confidence,
            'strongest_drivers' => array_slice(array_values($drivers), 0, 3),
            'structural_state' => $structure,
        ];
        $changes = $comparison ? self::changes($current, $data, $comparison) : [];
        $comparison_id = $comparison['edition_id'] ?? ($comparison['source_record_id'] ?? '');
        $anchor_key = preg_replace('/[^0-9TZ+-]/', '', $context['session_anchor']);
        $edition_id = 'bitmomo-ai:' . $anchor_key . ':' . $context['session_type'] . ':' . substr(hash('sha256', $generated_at . '|' . ($data['timestamp'] ?? '')), 0, 10);

        $session_intelligence = [
            'current_setup' => $current,
            'what_happened' => self::what_happened($data, $current),
            'what_changed' => $changes,
            'comparison' => [
                'status' => $comparison ? 'compared' : 'insufficient_history',
                'edition_id' => sanitize_text_field((string) $comparison_id),
                'session_type' => $comparison ? self::normalize_session_type($comparison['session_type'] ?? ($comparison['edition'] ?? '')) : null,
                'generated_at' => $comparison ? (string) ($comparison['generated_at'] ?? $comparison['time'] ?? '') : null,
            ],
            'why_it_matters' => self::why_it_matters($changes),
            'known_events' => [],
            'what_to_watch' => self::what_to_watch($current),
            'crypto_context' => [
                'market_status' => 'trading_24_7',
                'observed_at' => sanitize_text_field((string) ($data['timestamp'] ?? '')),
                'source_diagnostics' => self::observation_times($data['source_diagnostics'] ?? []),
            ],
            'tradfi_context' => [
                'us_market_status' => $context['us_market_status'],
                'observations' => self::tradfi_observations($data['tradfi_observations'] ?? []),
            ],
            'bond_context' => null,
        ];

        return [
            'edition_id' => $edition_id,
            'schema_version' => self::SCHEMA_VERSION,
            'session_type' => $context['session_type'],
            'session_label' => $context['session_label'],
            'session_anchor' => $context['session_anchor'],
            'market_timezone' => self::MARKET_TIMEZONE,
            'us_market_status' => $context['us_market_status'],
            'market_calendar' => $context['market_calendar'],
            'generated_at' => $generated->format(DateTimeInterface::ATOM),
            'time' => $generated->format(DateTimeInterface::ATOM),
            'latest_attempt_status' => ($gate['status'] ?? '') === 'degraded' ? 'degraded' : 'success',
            'canonical_status' => 'valid',
            'source_record_id' => $edition_id,
            'comparison_source_record_id' => sanitize_text_field((string) $comparison_id),
            'valid_snapshot_lineage' => [
                'source_timestamp' => sanitize_text_field((string) ($data['timestamp'] ?? '')),
                'source_provenance' => 'recorded_live',
                'comparison_edition_id' => sanitize_text_field((string) $comparison_id),
            ],
            'market_state' => $market_state,
            'directional_bias' => $bias,
            'confidence' => $confidence,
            'freshness' => 'fresh',
            'provenance' => 'recorded_live',
            'session_intelligence' => $session_intelligence,
            'data' => $data,
            'evaluation' => $evaluation,
            'quality' => $evaluation['quality'] ?? [],
            'edition' => $context['session_type'],
            'legacy_edition' => self::legacy_edition($context['session_type']),
        ];
    }

    public static function append_record(array $record) {
        $editions = self::history();
        $key = (string) ($record['edition_id'] ?? '');
        if ($key === '') return false;
        $editions[$key] = $record;
        update_option(self::HISTORY_OPTION, array_slice($editions, -self::HISTORY_LIMIT, null, true), false);
        return true;
    }

    public static function history() {
        $editions = get_option(self::HISTORY_OPTION, []);
        return is_array($editions) ? $editions : [];
    }

    public static function previous_valid(array $editions, $session_type, $before_timestamp) {
        $wanted = self::opposite($session_type);
        $best = null;
        $best_timestamp = 0;
        foreach ($editions as $record) {
            if (!is_array($record)) continue;
            $raw_session = $record['session_type'] ?? ($record['edition'] ?? '');
            if (!self::is_supported_session_type($raw_session)) continue;
            $record_session = self::normalize_session_type($raw_session);
            if ($record_session !== $wanted || !self::is_valid($record)) continue;
            $timestamp = strtotime((string) ($record['generated_at'] ?? $record['time'] ?? '')) ?: 0;
            if (!$timestamp || $timestamp >= (int) $before_timestamp) continue;
            if (((int) $before_timestamp - $timestamp) > Bitmomo_AI_Intelligence::DELAYED_AGE_SECONDS) continue;
            if ($timestamp > $best_timestamp) {
                $best = $record;
                $best_timestamp = $timestamp;
            }
        }
        return $best;
    }

    public static function free_fields(array $record) {
        $session = is_array($record['session_intelligence'] ?? null) ? $record['session_intelligence'] : [];
        return [
            'current_setup' => is_array($session['current_setup'] ?? null) ? $session['current_setup'] : [],
            'what_happened' => is_array($session['what_happened'] ?? null) ? $session['what_happened'] : [],
            'what_changed' => array_slice((array) ($session['what_changed'] ?? []), 0, 4),
            'comparison' => is_array($session['comparison'] ?? null) ? $session['comparison'] : ['status' => 'insufficient_history'],
            'why_it_matters' => array_slice((array) ($session['why_it_matters'] ?? []), 0, 2),
            'known_events' => array_slice((array) ($session['known_events'] ?? []), 0, 2),
            'what_to_watch' => array_slice((array) ($session['what_to_watch'] ?? []), 0, 2),
            'crypto_context' => is_array($session['crypto_context'] ?? null) ? $session['crypto_context'] : [],
            'tradfi_context' => is_array($session['tradfi_context'] ?? null) ? $session['tradfi_context'] : [],
            'bond_context' => null,
        ];
    }

    public static function pro_fields(array $record) {
        $session = is_array($record['session_intelligence'] ?? null) ? $record['session_intelligence'] : [];
        return [
            'what_matters_next' => (array) ($session['why_it_matters'] ?? []),
            'monitoring_conditions' => (array) ($session['what_to_watch'] ?? []),
            'scenario_contract' => ['bull' => null, 'base' => null, 'bear' => null],
            'bond_context' => null,
        ];
    }

    private static function anchor_time($session_type) {
        return self::normalize_session_type($session_type) === self::PRE_OPEN ? [8, 10] : [20, 10];
    }

    private static function is_valid(array $record) {
        if (($record['canonical_status'] ?? 'valid') !== 'valid') return false;
        if (($record['provenance'] ?? '') !== 'recorded_live') return false;
        $quality = (string) ($record['evaluation']['quality']['status'] ?? ($record['quality']['status'] ?? ''));
        return in_array($quality, ['complete', 'degraded'], true);
    }

    private static function changes(array $current, array $data, array $previous) {
        $prior_setup = (array) ($previous['session_intelligence']['current_setup'] ?? []);
        if (!$prior_setup) {
            $prior_evaluation = (array) ($previous['evaluation'] ?? []);
            $prior_data = (array) ($previous['data'] ?? []);
            $prior_setup = [
                'market_state' => $previous['market_state'] ?? null,
                'directional_bias' => $prior_evaluation['bias'] ?? null,
                'direction_strength' => $prior_evaluation['direction_strength'] ?? null,
                'confidence' => $prior_evaluation['confidence'] ?? null,
                'strongest_drivers' => [],
                'structural_state' => $prior_data['structure']['state'] ?? null,
            ];
        }
        $changes = [];
        foreach (['market_state', 'directional_bias', 'direction_strength', 'structural_state'] as $field) {
            $from = $prior_setup[$field] ?? null;
            $to = $current[$field] ?? null;
            if ($from !== null && $to !== null && $from !== $to) $changes[] = ['field' => $field, 'from' => $from, 'to' => $to];
        }
        if (isset($prior_setup['confidence']) && (int) $prior_setup['confidence'] !== (int) $current['confidence']) {
            $changes[] = ['field' => 'confidence', 'from' => (int) $prior_setup['confidence'], 'to' => (int) $current['confidence'], 'delta' => (int) $current['confidence'] - (int) $prior_setup['confidence']];
        }
        $prior_driver = (string) (($prior_setup['strongest_drivers'][0] ?? ''));
        $current_driver = (string) (($current['strongest_drivers'][0] ?? ''));
        if ($prior_driver !== '' && $current_driver !== '' && $prior_driver !== $current_driver) {
            $changes[] = ['field' => 'strongest_driver', 'from' => $prior_driver, 'to' => $current_driver];
        }
        return $changes;
    }

    private static function what_happened(array $data, array $current) {
        return [
            'observed_window' => 'trailing_24h',
            'btc_change_pct' => isset($data['crowding']['price_change_24h_pct']) ? round((float) $data['crowding']['price_change_24h_pct'], 4) : null,
            'volatility_state' => self::nullable_key($data['volatility']['regime'] ?? null),
            'ending_directional_bias' => $current['directional_bias'],
            'ending_structural_state' => $current['structural_state'],
            'derivatives_context' => [
                'open_interest_change_24h_pct' => isset($data['crowding']['oi_change_24h_pct']) ? round((float) $data['crowding']['oi_change_24h_pct'], 4) : null,
                'funding_rate' => isset($data['carry']['funding_rate']) ? (float) $data['carry']['funding_rate'] : null,
                'basis_pct' => isset($data['carry']['basis_pct']) ? (float) $data['carry']['basis_pct'] : null,
            ],
        ];
    }

    private static function why_it_matters(array $changes) {
        $codes = [];
        foreach ($changes as $change) {
            $field = (string) ($change['field'] ?? '');
            if (in_array($field, ['directional_bias', 'direction_strength'], true)) $codes[] = 'directional_context_changed';
            elseif ($field === 'confidence') $codes[] = 'evidence_strength_changed';
            elseif (in_array($field, ['market_state', 'structural_state'], true)) $codes[] = 'market_structure_changed';
            elseif ($field === 'strongest_driver') $codes[] = 'leading_evidence_changed';
        }
        return array_values(array_unique($codes));
    }

    private static function what_to_watch(array $current) {
        $watch = ['directional_consistency'];
        if (!empty($current['structural_state'])) $watch[] = 'structure_continuity';
        return $watch;
    }

    private static function observation_times($diagnostics) {
        $observations = [];
        foreach ((array) $diagnostics as $row) {
            if (!is_array($row)) continue;
            $input = sanitize_key((string) ($row['requested_input'] ?? ''));
            if ($input === '') continue;
            $observations[] = [
                'requested_input' => $input,
                'provider' => sanitize_key((string) ($row['provider'] ?? '')),
                'observed_at' => sanitize_text_field((string) ($row['observed_at'] ?? '')),
                'freshness' => sanitize_key((string) ($row['freshness'] ?? 'unknown')),
            ];
        }
        return $observations;
    }

    private static function tradfi_observations($rows) {
        $observations = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) continue;
            $name = sanitize_key((string) ($row['requested_input'] ?? ($row['name'] ?? '')));
            $observed_at = sanitize_text_field((string) ($row['observed_at'] ?? ''));
            if ($name === '' || $observed_at === '') continue;
            $observations[] = [
                'requested_input' => $name,
                'source' => sanitize_key((string) ($row['source'] ?? '')),
                'observed_at' => $observed_at,
                'freshness' => sanitize_key((string) ($row['freshness'] ?? 'unknown')),
            ];
        }
        return $observations;
    }

    private static function holidays($year) {
        $timezone = new DateTimeZone(self::MARKET_TIMEZONE);
        $dates = [];
        $add = static function ($date, $reason) use (&$dates) { $dates[$date] = $reason; };
        $add(self::observed_fixed($year . '-01-01'), 'new_year');
        $add(self::observed_fixed(($year + 1) . '-01-01'), 'new_year');
        $add((new DateTimeImmutable("third monday of january {$year}", $timezone))->format('Y-m-d'), 'martin_luther_king_jr_day');
        $add((new DateTimeImmutable("third monday of february {$year}", $timezone))->format('Y-m-d'), 'presidents_day');
        $easter = (new DateTimeImmutable($year . '-03-21', $timezone))->modify('+' . easter_days($year) . ' days');
        $add($easter->modify('-2 days')->format('Y-m-d'), 'good_friday');
        $add((new DateTimeImmutable("last monday of may {$year}", $timezone))->format('Y-m-d'), 'memorial_day');
        if ($year >= 2022) $add(self::observed_fixed($year . '-06-19'), 'juneteenth');
        $add(self::observed_fixed($year . '-07-04'), 'independence_day');
        $add((new DateTimeImmutable("first monday of september {$year}", $timezone))->format('Y-m-d'), 'labor_day');
        $thanksgiving = new DateTimeImmutable("fourth thursday of november {$year}", $timezone);
        $add($thanksgiving->format('Y-m-d'), 'thanksgiving');
        $add(self::observed_fixed($year . '-12-25'), 'christmas');
        return $dates;
    }

    private static function early_closes($year) {
        $timezone = new DateTimeZone(self::MARKET_TIMEZONE);
        $thanksgiving = new DateTimeImmutable("fourth thursday of november {$year}", $timezone);
        $candidates = [$thanksgiving->modify('+1 day'), new DateTimeImmutable($year . '-07-03', $timezone), new DateTimeImmutable($year . '-12-24', $timezone)];
        $days = [];
        $holidays = self::holidays($year);
        foreach ($candidates as $candidate) {
            $day = $candidate->format('Y-m-d');
            if ((int) $candidate->format('N') < 6 && !isset($holidays[$day])) $days[] = $day;
        }
        return array_values(array_unique($days));
    }

    private static function observed_fixed($date) {
        $timezone = new DateTimeZone(self::MARKET_TIMEZONE);
        $value = new DateTimeImmutable($date, $timezone);
        if ((int) $value->format('N') === 6) $value = $value->modify('-1 day');
        elseif ((int) $value->format('N') === 7) $value = $value->modify('+1 day');
        return $value->format('Y-m-d');
    }

    private static function nullable_key($value) {
        if ($value === null || $value === '') return null;
        $value = sanitize_key((string) $value);
        return $value === '' ? null : $value;
    }

    private static function allowed_key($value, array $allowed, $fallback) {
        $value = sanitize_key((string) $value);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
