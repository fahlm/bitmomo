<?php
if (!defined('ABSPATH')) exit;

/** Read-only evaluation layer. It never participates in generation or projection. */
final class Bitmomo_AI_Scorecard {
    const MIN_SAMPLE = 10;
    const STRONG_SAMPLE = 30;

    public static function evaluate(array $signals, array $pro_ranges = [], array $regimes = [], array $quality_events = []) {
        usort($signals, function ($a, $b) { return strcmp((string) ($a['generated_at'] ?? ''), (string) ($b['generated_at'] ?? '')); });
        $settled = array_values(array_filter($signals, function ($row) { return ($row['outcome_status'] ?? '') === 'evaluated'; }));
        $version_groups = [];
        foreach ($settled as $row) {
            $key = self::version_key($row);
            $version_groups[$key][] = $row;
        }
        $versions = [];
        foreach ($version_groups as $key => $rows) $versions[$key] = self::direction_package($rows);

        return [
            'version_policy' => count($versions) > 1 ? 'SEPARATED_INCOMPATIBLE_VERSIONS' : 'SINGLE_VERSION',
            'versions' => $versions,
            'expected_range' => self::expected_range($pro_ranges),
            'regime_evaluation' => self::regime_evaluation($regimes, $settled),
            'data_quality' => self::data_quality($signals, $quality_events),
            'sample_rules' => ['minimum' => self::MIN_SAMPLE, 'strong' => self::STRONG_SAMPLE],
        ];
    }

    private static function direction_package(array $rows) {
        $rolling = array_slice(array_reverse($rows), 0, 30);
        $by_direction = [];
        foreach (['bullish', 'bearish', 'neutral'] as $direction) {
            $by_direction[$direction] = self::accuracy(array_values(array_filter($rows, function ($row) use ($direction) { return ($row['direction'] ?? '') === $direction; })));
        }
        $editions = [];
        $regimes = [];
        foreach ($rows as $row) {
            $edition = in_array(($row['edition'] ?? ''), ['morning', 'us_session'], true) ? $row['edition'] : 'unknown';
            $regime = trim((string) ($row['regime'] ?? '')) ?: 'unknown';
            $editions[$edition][] = $row;
            $regimes[$regime][] = $row;
        }
        foreach ($editions as $key => $set) $editions[$key] = self::accuracy($set);
        foreach ($regimes as $key => $set) $regimes[$key] = self::accuracy($set);

        return [
            'all' => self::accuracy($rows),
            'rolling_30' => self::accuracy($rolling),
            'by_direction' => $by_direction,
            'confidence_calibration' => self::calibration($rows),
            'by_edition' => $editions,
            'by_regime' => $regimes,
            'baselines' => self::baselines($rows),
        ];
    }

    private static function accuracy(array $rows) {
        $correct = $incorrect = $inconclusive = 0;
        foreach ($rows as $row) {
            $value = (string) ($row['outcome_direction'] ?? '');
            if ($value === 'correct') $correct++;
            elseif ($value === 'incorrect') $incorrect++;
            else $inconclusive++;
        }
        $conclusive = $correct + $incorrect;
        return [
            'n' => count($rows), 'conclusive_n' => $conclusive,
            'correct' => $correct, 'incorrect' => $incorrect, 'inconclusive' => $inconclusive,
            'accuracy_pct' => $conclusive ? round(100 * $correct / $conclusive, 1) : null,
            'sample_status' => self::sample_status($conclusive),
        ];
    }

    private static function calibration(array $rows) {
        $n = count($rows);
        $ranges = $n < 10 ? [[0, 100]] : ($n < 30 ? [[0, 49], [50, 69], [70, 100]] : [[0, 39], [40, 54], [55, 69], [70, 100]]);
        $result = [];
        foreach ($ranges as $range) {
            $bucket = array_values(array_filter($rows, function ($row) use ($range) {
                $confidence = (int) ($row['confidence'] ?? -1);
                return $confidence >= $range[0] && $confidence <= $range[1];
            }));
            $metric = self::accuracy($bucket);
            $metric['range'] = $range[0] . '–' . $range[1];
            $result[] = $metric;
        }
        return $result;
    }

    private static function baselines(array $rows) {
        $always_neutral = $previous = $momentum = $trend = [];
        $previous_direction = null;
        foreach ($rows as $row) {
            $return = isset($row['return_pct']) ? (float) $row['return_pct'] : null;
            if ($return === null) continue;
            $always_neutral[] = self::baseline_result('neutral', $return);
            if ($previous_direction !== null) $previous[] = self::baseline_result($previous_direction, $return);
            if (in_array(($row['momentum_direction'] ?? ''), ['bullish', 'neutral', 'bearish'], true)) $momentum[] = self::baseline_result($row['momentum_direction'], $return);
            if (in_array(($row['trend_direction'] ?? ''), ['bullish', 'neutral', 'bearish'], true)) $trend[] = self::baseline_result($row['trend_direction'], $return);
            if (in_array(($row['direction'] ?? ''), ['bullish', 'neutral', 'bearish'], true)) $previous_direction = $row['direction'];
        }
        return [
            'always_neutral' => self::binary_accuracy($always_neutral),
            'previous_direction' => self::binary_accuracy($previous),
            'simple_momentum' => self::binary_accuracy($momentum),
            'simple_trend' => self::binary_accuracy($trend),
        ];
    }

    private static function baseline_result($direction, $return) {
        if ($direction === 'neutral') return abs($return) < 0.5;
        return $direction === 'bullish' ? $return >= 0.5 : $return <= -0.5;
    }

    private static function binary_accuracy(array $results) {
        $correct = count(array_filter($results));
        $n = count($results);
        return ['n' => $n, 'correct' => $correct, 'accuracy_pct' => $n ? round(100 * $correct / $n, 1) : null, 'sample_status' => self::sample_status($n)];
    }

    private static function expected_range(array $rows) {
        $eligible = array_values(array_filter($rows, function ($row) {
            return ($row['status'] ?? '') === 'evaluated' && !empty($row['frozen_original'])
                && (float) ($row['range_low'] ?? 0) > 0 && (float) ($row['range_high'] ?? 0) >= (float) ($row['range_low'] ?? 0);
        }));
        $groups = [];
        foreach ($eligible as $row) {
            $version = trim((string) ($row['model_version'] ?? '')) ?: 'unknown-model';
            $groups[$version][] = $row;
        }
        $versions = [];
        foreach ($groups as $version => $version_rows) $versions[$version] = self::expected_range_metric($version_rows);
        return ['version_policy' => count($versions) > 1 ? 'SEPARATED_INCOMPATIBLE_VERSIONS' : 'SINGLE_VERSION', 'versions' => $versions, 'policy' => 'FROZEN_ORIGINAL_ONLY'];
    }

    private static function expected_range_metric(array $eligible) {
        $hit = $low = $high = 0; $widths = [];
        foreach ($eligible as $row) {
            $hit += ($row['range_hit'] ?? '') === 'yes' ? 1 : 0;
            $low += ($row['breached_low'] ?? '') === 'yes' ? 1 : 0;
            $high += ($row['breached_high'] ?? '') === 'yes' ? 1 : 0;
            $reference = (float) ($row['reference_price'] ?? 0);
            if ($reference > 0) $widths[] = 100 * ((float) $row['range_high'] - (float) $row['range_low']) / $reference;
        }
        $n = count($eligible);
        return [
            'n' => $n, 'range_hit_pct' => $n ? round(100 * $hit / $n, 1) : null,
            'low_breach_pct' => $n ? round(100 * $low / $n, 1) : null,
            'high_breach_pct' => $n ? round(100 * $high / $n, 1) : null,
            'average_width_pct' => $widths ? round(array_sum($widths) / count($widths), 2) : null,
            'sample_status' => self::sample_status($n),
        ];
    }

    private static function regime_evaluation(array $regimes, array $signals) {
        $signal_index = [];
        foreach ($signals as $row) if (!empty($row['source_record_id'])) $signal_index[self::normalize_source_id($row['source_record_id'])] = $row;
        $live = array_values(array_filter($regimes, function ($row) { return ($row['provenance'] ?? '') === 'recorded_live'; }));
        usort($live, function ($a, $b) { return strcmp((string) ($a['as_of'] ?? ''), (string) ($b['as_of'] ?? '')); });
        $groups = []; $transitions = 0; $previous = null;
        foreach ($live as $row) {
            $classifier = trim((string) ($row['classifier_version'] ?? '')) ?: 'unknown';
            $regime = trim((string) ($row['regime'] ?? '')) ?: 'unknown';
            if ($previous !== null && $previous !== $regime) $transitions++;
            $previous = $regime;
            $signal = $signal_index[self::normalize_source_id($row['source_record_id'] ?? '')] ?? null;
            if (!$signal) continue;
            $entry = (float) ($signal['entry_price'] ?? 0); $high = (float) ($signal['high_24h'] ?? 0); $low = (float) ($signal['low_24h'] ?? 0);
            $groups[$classifier][$regime][] = [
                'return_pct' => (float) ($signal['return_pct'] ?? 0),
                'volatility_pct' => $entry > 0 && $high > 0 && $low > 0 ? 100 * ($high - $low) / $entry : null,
            ];
        }
        $metrics = [];
        foreach ($groups as $classifier => $by_regime) foreach ($by_regime as $regime => $rows) {
            $returns = array_column($rows, 'return_pct');
            $vols = array_values(array_filter(array_column($rows, 'volatility_pct'), function ($v) { return $v !== null; }));
            $n = count($rows);
            $metrics[$classifier][$regime] = ['n' => $n, 'average_forward_return_pct' => round(array_sum($returns) / $n, 3), 'average_forward_volatility_pct' => $vols ? round(array_sum($vols) / count($vols), 3) : null, 'sample_status' => self::sample_status($n)];
        }
        return ['versions' => $metrics, 'append_only_n' => count($live), 'transition_n' => $transitions, 'transition_frequency_pct' => count($live) > 1 ? round(100 * $transitions / (count($live) - 1), 1) : null];
    }

    private static function data_quality(array $signals, array $events) {
        $rows = array_merge($signals, $events); $n = count($rows); $stale = $blocked = $degraded = $missing = 0;
        foreach ($rows as $row) {
            $quality = (string) ($row['quality_status'] ?? ''); $gate = (string) ($row['gate_status'] ?? '');
            $stale += $quality === 'stale' ? 1 : 0; $blocked += $gate === 'blocked' ? 1 : 0; $degraded += ($quality === 'degraded' || $gate === 'degraded') ? 1 : 0;
            $missing += !empty($row['missing_data']) ? 1 : 0;
        }
        $settled = count(array_filter($signals, function ($row) { return in_array(($row['outcome_status'] ?? ''), ['evaluated', 'window_missed'], true); }));
        $signal_n = count($signals);
        return ['n' => $n, 'stale_rate_pct' => $n ? round(100 * $stale / $n, 1) : null, 'blocked_degraded_rate_pct' => $n ? round(100 * ($blocked + $degraded) / $n, 1) : null, 'missing_data_rate_pct' => $n ? round(100 * $missing / $n, 1) : null, 'settlement_n' => $signal_n, 'settlement_completeness_pct' => $signal_n ? round(100 * $settled / $signal_n, 1) : null, 'sample_status' => self::sample_status($n)];
    }

    private static function version_key(array $row) {
        $model = trim((string) ($row['model_version'] ?? '')) ?: 'unknown-model';
        $classifier = trim((string) ($row['classifier_version'] ?? '')) ?: 'unknown-classifier';
        return $model . ' | ' . $classifier;
    }

    private static function normalize_source_id($id) { return str_replace('bitmomo-ai:regime:', 'bitmomo-ai:', (string) $id); }
    private static function sample_status($n) { return $n < self::MIN_SAMPLE ? 'INSUFFICIENT SAMPLE' : ($n < self::STRONG_SAMPLE ? 'EARLY SAMPLE' : 'ADEQUATE'); }
}

/** WordPress adapter for the private admin scorecard. */
final class Bitmomo_AI_Scorecard_Repository {
    public static function build() {
        $regimes = self::regimes();
        $regime_index = [];
        foreach ($regimes as $row) $regime_index[self::normalize_source_id($row['source_record_id'])] = $row;
        $signals = [];
        foreach (get_posts(['post_type' => Bitmomo_AI_Content_Types::SIGNAL, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'date', 'order' => 'ASC']) as $id) {
            $input = json_decode((string) get_post_meta($id, '_bm_input_snapshot', true), true);
            $input = is_array($input) ? $input : [];
            $source_id = (string) get_post_meta($id, '_bm_source_record_id', true);
            $regime = $regime_index[self::normalize_source_id($source_id)] ?? [];
            $optional_missing = (array) ($input['quality']['optional_missing'] ?? []);
            $signals[] = [
                'id' => $id, 'generated_at' => (string) get_post_meta($id, '_bm_generated_at', true),
                'source_record_id' => $source_id, 'direction' => (string) get_post_meta($id, '_bm_direction', true),
                'confidence' => (int) get_post_meta($id, '_bm_confidence', true), 'edition' => (string) get_post_meta($id, '_bm_edition', true),
                'model_version' => (string) get_post_meta($id, '_bm_model', true), 'classifier_version' => (string) ($regime['classifier_version'] ?? ''), 'regime' => (string) ($regime['regime'] ?? ''),
                'outcome_status' => (string) get_post_meta($id, '_bm_outcome_status', true), 'outcome_direction' => (string) get_post_meta($id, '_bm_outcome_direction', true),
                'return_pct' => get_post_meta($id, '_bm_outcome_return_pct', true), 'entry_price' => (float) get_post_meta($id, '_bm_market_price', true),
                'high_24h' => (float) get_post_meta($id, '_bm_outcome_high_24h', true), 'low_24h' => (float) get_post_meta($id, '_bm_outcome_low_24h', true),
                'momentum_direction' => (string) ($input['direction']['bias_4h'] ?? ''), 'trend_direction' => (string) ($input['direction']['bias_1d'] ?? ''),
                'quality_status' => (string) ($input['quality']['status'] ?? ''), 'gate_status' => (string) get_post_meta($id, '_bm_quality_gate_status', true),
                'missing_data' => empty($input) || !empty($optional_missing),
            ];
        }
        return Bitmomo_AI_Scorecard::evaluate($signals, self::pro_ranges($signals), $regimes, self::quality_events());
    }

    private static function regimes() {
        if (!post_type_exists('bm_regime_state')) return [];
        $rows = [];
        foreach (get_posts(['post_type' => 'bm_regime_state', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'date', 'order' => 'ASC']) as $id) {
            $rows[] = ['as_of' => (string) get_post_meta($id, '_bitmomo_regime_as_of', true), 'source_record_id' => (string) get_post_meta($id, '_bitmomo_regime_source_record_id', true), 'regime' => (string) get_post_meta($id, '_bitmomo_regime_regime', true), 'classifier_version' => (string) get_post_meta($id, '_bitmomo_regime_classifier_version', true), 'provenance' => (string) get_post_meta($id, '_bitmomo_regime_provenance', true)];
        }
        return $rows;
    }

    private static function pro_ranges(array $signals) {
        if (!post_type_exists('bm_pro_brief')) return [];
        $model_index = [];
        foreach ($signals as $signal) if (!empty($signal['source_record_id'])) $model_index[self::normalize_source_id($signal['source_record_id'])] = $signal['model_version'];
        $rows = [];
        foreach (get_posts(['post_type' => 'bm_pro_brief', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]) as $id) {
            $original = json_decode((string) get_post_meta($id, '_bitmomo_pro_evaluation_original', true), true);
            $original = is_array($original) ? $original : [];
            $source_id = (string) ($original['source_record_id'] ?? '');
            $rows[] = ['status' => (string) get_post_meta($id, '_bitmomo_pro_evaluation_status', true), 'frozen_original' => !empty($original), 'range_low' => (float) ($original['expected_range_low'] ?? 0), 'range_high' => (float) ($original['expected_range_high'] ?? 0), 'reference_price' => (float) ($original['btc_reference_price'] ?? 0), 'model_version' => $model_index[self::normalize_source_id($source_id)] ?? '', 'range_hit' => (string) get_post_meta($id, '_bitmomo_pro_outcome_range_hit', true), 'breached_low' => (string) get_post_meta($id, '_bitmomo_pro_outcome_breached_low', true), 'breached_high' => (string) get_post_meta($id, '_bitmomo_pro_outcome_breached_high', true)];
        }
        return $rows;
    }

    private static function quality_events() {
        $events = [];
        foreach ((array) get_option(Bitmomo_AI_Scheduler::PUBLISH_LOG_OPTION, []) as $entry) $events[] = ['gate_status' => (string) ($entry['status'] ?? ''), 'quality_status' => (string) ($entry['quality_status'] ?? ''), 'missing_data' => false];
        return $events;
    }

    public static function render_admin() {
        $scorecard = self::build();
        echo '<h2>' . esc_html__('Intelligence evaluation scorecard', 'bitmomo-ai') . '</h2>';
        echo '<p><em>' . esc_html__('Private diagnostic only. Metrics are separated by model/classifier version and are not marketing claims.', 'bitmomo-ai') . '</em></p>';
        foreach ($scorecard['versions'] as $version => $metrics) {
            echo '<h3>' . esc_html($version) . '</h3>';
            self::render_metric_table(__('Directional accuracy', 'bitmomo-ai'), [
                'All available settled' => $metrics['all'],
                'Rolling 30' => $metrics['rolling_30'],
                'Bullish' => $metrics['by_direction']['bullish'],
                'Bearish' => $metrics['by_direction']['bearish'],
                'Neutral' => $metrics['by_direction']['neutral'],
            ]);
            self::render_metric_table(__('Edition breakdown', 'bitmomo-ai'), $metrics['by_edition']);
            self::render_metric_table(__('Regime breakdown', 'bitmomo-ai'), $metrics['by_regime']);
            self::render_metric_table(__('Baselines', 'bitmomo-ai'), $metrics['baselines']);
            self::render_metric_table(__('Confidence calibration', 'bitmomo-ai'), array_combine(array_map(function ($row) { return $row['range']; }, $metrics['confidence_calibration']), $metrics['confidence_calibration']));
        }
        foreach ($scorecard['expected_range']['versions'] as $version => $metric) self::render_metric_table(__('Expected Range evaluation', 'bitmomo-ai') . ' — ' . $version, ['Frozen originals only' => $metric]);
        self::render_regime_table($scorecard['regime_evaluation']);
        self::render_metric_table(__('Data quality', 'bitmomo-ai'), ['All recorded events' => $scorecard['data_quality']]);
    }

    private static function render_metric_table($title, array $rows) {
        echo '<h3>' . esc_html($title) . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__('Segment', 'bitmomo-ai') . '</th><th>' . esc_html__('n', 'bitmomo-ai') . '</th><th>' . esc_html__('Metrics', 'bitmomo-ai') . '</th><th>' . esc_html__('Sample status', 'bitmomo-ai') . '</th></tr></thead><tbody>';
        foreach ($rows as $label => $row) {
            $copy = $row; unset($copy['n'], $copy['sample_status'], $copy['range']);
            echo '<tr><td>' . esc_html((string) $label) . '</td><td>' . esc_html((string) ($row['n'] ?? 0)) . '</td><td><code>' . esc_html(wp_json_encode($copy)) . '</code></td><td>' . esc_html((string) ($row['sample_status'] ?? 'INSUFFICIENT SAMPLE')) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_regime_table(array $evaluation) {
        echo '<h3>' . esc_html__('Regime forward evaluation', 'bitmomo-ai') . '</h3>';
        echo '<p>' . esc_html(sprintf('Append-only n=%d; transitions=%d; transition frequency=%s', (int) $evaluation['append_only_n'], (int) $evaluation['transition_n'], $evaluation['transition_frequency_pct'] === null ? 'N/A' : $evaluation['transition_frequency_pct'] . '%')) . '</p>';
        foreach ($evaluation['versions'] as $version => $rows) self::render_metric_table($version, $rows);
    }

    private static function normalize_source_id($id) { return str_replace('bitmomo-ai:regime:', 'bitmomo-ai:', (string) $id); }
}
