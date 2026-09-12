<?php
define('ABSPATH', __DIR__);
require __DIR__ . '/../includes/class-bitmomo-ai-scorecard.php';

function scorecard_check($label, $condition) {
    printf("[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label);
    if (!$condition) $GLOBALS['__scorecard_failed'] = true;
}

function signal_fixture($overrides = []) {
    return array_merge([
        'generated_at' => '2026-09-01T00:10:00+00:00',
        'source_record_id' => 'bitmomo-ai:2026-09-01:morning',
        'direction' => 'bullish', 'confidence' => 60, 'edition' => 'morning',
        'model_version' => 'engine-v1', 'classifier_version' => 'regime-v1', 'regime' => 'expansion',
        'outcome_status' => 'evaluated', 'outcome_methodology' => 'observed-close-24h-v2', 'outcome_direction' => 'correct', 'return_pct' => 1.2,
        'entry_price' => 100, 'high_24h' => 102, 'low_24h' => 99,
        'momentum_direction' => 'bullish', 'trend_direction' => 'bullish',
        'quality_status' => 'complete', 'gate_status' => 'passed', 'missing_data' => false,
    ], $overrides);
}

$signals = [
    signal_fixture(),
    signal_fixture(['generated_at' => '2026-09-01T12:10:00+00:00', 'source_record_id' => 'bitmomo-ai:2026-09-01:us_session', 'direction' => 'neutral', 'confidence' => 42, 'edition' => 'us_session', 'outcome_direction' => 'correct', 'return_pct' => 0.1]),
    signal_fixture(['generated_at' => '2026-09-02T00:10:00+00:00', 'source_record_id' => 'bitmomo-ai:2026-09-02:morning', 'direction' => 'bearish', 'confidence' => 78, 'edition' => '', 'outcome_direction' => 'incorrect', 'return_pct' => 1.0, 'regime' => '', 'classifier_version' => '']),
];
$score = Bitmomo_AI_Scorecard::evaluate($signals);
$v1_key = 'engine-v1 | regime-v1 | observed-close-24h-v2';
$unknown_key = 'engine-v1 | unknown-classifier | observed-close-24h-v2';
$v1 = $score['versions'][$v1_key];
scorecard_check('directional all includes only the compatible model/classifier/outcome methodology group', 2 === $v1['all']['n']);
scorecard_check('current outcome methodology is explicit metadata', 'observed-close-24h-v2' === $v1['outcome_methodology']);
scorecard_check('bullish breakdown is separate', 1 === $v1['by_direction']['bullish']['n']);
scorecard_check('neutral edge case is correct inside ±0.5%', 1 === $v1['by_direction']['neutral']['correct']);
scorecard_check('legacy missing edition is explicitly unknown', isset($score['versions'][$unknown_key]['by_edition']['unknown']));
scorecard_check('missing regime is explicitly unknown', isset($score['versions'][$unknown_key]['by_regime']['unknown']));
scorecard_check('incompatible classifier versions are separated', 'SEPARATED_INCOMPATIBLE_VERSIONS' === $score['version_policy']);
scorecard_check('tiny confidence sample collapses to one bucket', 1 === count($v1['confidence_calibration']));
scorecard_check('tiny confidence bucket is labelled insufficient', 'INSUFFICIENT SAMPLE' === $v1['confidence_calibration'][0]['sample_status']);

$methodology_split = Bitmomo_AI_Scorecard::evaluate([
    signal_fixture(['generated_at' => '2026-09-10T00:10:00+00:00']),
    signal_fixture(['generated_at' => '2026-08-20T00:10:00+00:00', 'outcome_methodology' => '']),
]);
scorecard_check('legacy loose-window outcomes never mix with exact-24h outcomes', isset($methodology_split['versions']['engine-v1 | regime-v1 | observed-close-24h-v2']) && isset($methodology_split['versions']['engine-v1 | regime-v1 | legacy-window-v1']));
$method_keys = array_keys($methodology_split['versions']);
scorecard_check('newest outcome methodology is ordered first for public presentation', 'engine-v1 | regime-v1 | observed-close-24h-v2' === $method_keys[0]);

$large = [];
for ($i = 0; $i < 36; $i++) {
    $large[] = signal_fixture([
        'generated_at' => sprintf('2026-09-%02dT00:10:00+00:00', 1 + ($i % 28)),
        'confidence' => 30 + ($i % 60),
        'outcome_direction' => $i % 3 ? 'correct' : 'incorrect',
    ]);
}
$large_score = Bitmomo_AI_Scorecard::evaluate($large);
scorecard_check('rolling metric is capped at 30', 30 === $large_score['versions'][$v1_key]['rolling_30']['n']);
scorecard_check('adequate sample uses four calibration buckets', 4 === count($large_score['versions'][$v1_key]['confidence_calibration']));

$ranges = [
    ['status' => 'evaluated', 'frozen_original' => true, 'range_methodology' => 'range-model-v1', 'range_low' => 95, 'range_high' => 105, 'reference_price' => 100, 'range_hit' => 'yes', 'breached_low' => 'no', 'breached_high' => 'yes'],
    ['status' => 'evaluated', 'frozen_original' => true, 'range_methodology' => '', 'range_low' => 95, 'range_high' => 105, 'reference_price' => 100, 'range_hit' => 'yes'],
    ['status' => 'evaluated', 'frozen_original' => false, 'range_methodology' => 'range-model-v1', 'range_low' => 95, 'range_high' => 105, 'reference_price' => 100, 'range_hit' => 'yes'],
    ['status' => 'evaluated', 'frozen_original' => true, 'range_methodology' => 'range-model-v1', 'range_low' => 0, 'range_high' => 105, 'reference_price' => 100, 'range_hit' => 'yes'],
];
$range_package = Bitmomo_AI_Scorecard::evaluate([], $ranges)['expected_range'];
$range_score = $range_package['versions']['unknown-model | range-model-v1'];
scorecard_check('Expected Range policy requires frozen versioned methodology', 'FROZEN_VERSIONED_ORIGINAL_ONLY' === $range_package['policy']);
scorecard_check('unversioned/manual ranges are excluded from public-comparable evaluation', 1 === $range_score['n']);
scorecard_check('range hit is measured only after methodology eligibility', 100.0 === $range_score['range_hit_pct']);
scorecard_check('low and high breaches are separate', 0.0 === $range_score['low_breach_pct'] && 100.0 === $range_score['high_breach_pct']);
scorecard_check('range width is normalized to reference price', 10.0 === $range_score['average_width_pct']);

$regime_signals = [
    signal_fixture(['source_record_id' => 'bitmomo-ai:2026-09-01:morning', 'return_pct' => 1, 'entry_price' => 100, 'high_24h' => 103, 'low_24h' => 99]),
    signal_fixture(['source_record_id' => 'bitmomo-ai:2026-09-02:morning', 'return_pct' => -2, 'entry_price' => 100, 'high_24h' => 101, 'low_24h' => 96]),
    signal_fixture(['source_record_id' => 'bitmomo-ai:2026-09-03:morning', 'generated_at' => '2026-08-20T00:10:00+00:00', 'outcome_methodology' => '', 'return_pct' => 3, 'entry_price' => 100, 'high_24h' => 104, 'low_24h' => 99]),
];
$regimes = [
    ['as_of' => '2026-09-01 07:10:00', 'source_record_id' => 'bitmomo-ai:regime:2026-09-01:morning', 'regime' => 'expansion', 'classifier_version' => 'regime-v1', 'provenance' => 'recorded_live'],
    ['as_of' => '2026-09-02 07:10:00', 'source_record_id' => 'bitmomo-ai:regime:2026-09-02:morning', 'regime' => 'distribution', 'classifier_version' => 'regime-v1', 'provenance' => 'recorded_live'],
    ['as_of' => '2026-09-03 07:10:00', 'source_record_id' => 'bitmomo-ai:regime:2026-09-03:morning', 'regime' => 'expansion', 'classifier_version' => 'regime-v1', 'provenance' => 'recorded_live'],
    ['as_of' => '2026-08-01 07:10:00', 'source_record_id' => 'legacy', 'regime' => 'expansion', 'classifier_version' => 'regime-v1', 'provenance' => 'historical_reconstruction'],
];
$regime_score = Bitmomo_AI_Scorecard::evaluate($regime_signals, [], $regimes)['regime_evaluation'];
$current_regime_key = 'regime-v1 | observed-close-24h-v2';
$legacy_regime_key = 'regime-v1 | legacy-window-v1';
scorecard_check('regime evaluation excludes reconstructed history', 3 === $regime_score['append_only_n']);
scorecard_check('regime forward return joins genuine source ids', 1 === $regime_score['versions'][$current_regime_key]['expansion']['n']);
scorecard_check('regime forward volatility uses the same exact settled 24h window', 4.0 === $regime_score['versions'][$current_regime_key]['expansion']['average_forward_volatility_pct']);
scorecard_check('regime proof also separates legacy and exact outcome methodologies', isset($regime_score['versions'][$legacy_regime_key]['expansion']));
scorecard_check('transition frequency counts append-only state changes', 2 === $regime_score['transition_n'] && 100.0 === $regime_score['transition_frequency_pct']);

$quality = Bitmomo_AI_Scorecard::evaluate([
    signal_fixture(['outcome_status' => 'evaluated', 'quality_status' => 'stale', 'missing_data' => true]),
    signal_fixture(['outcome_status' => 'window_missed', 'quality_status' => 'degraded']),
    signal_fixture(['outcome_status' => 'pending']),
], [], [], [['gate_status' => 'blocked', 'quality_status' => '', 'missing_data' => false]])['data_quality'];
scorecard_check('data quality reports stale rate', 25.0 === $quality['stale_rate_pct']);
scorecard_check('data quality reports blocked/degraded rate', 50.0 === $quality['blocked_degraded_rate_pct']);
scorecard_check('settlement denominator includes only matured windows', 2 === $quality['settlement_n']);
scorecard_check('settlement completeness counts evaluated vs missed, not immature pending records', 50.0 === $quality['settlement_completeness_pct']);
scorecard_check('settlement counts make evaluated/missed/pending states explicit', 1 === $quality['settlement_evaluated_n'] && 1 === $quality['settlement_missed_n'] && 1 === $quality['settlement_pending_n']);

exit(!empty($GLOBALS['__scorecard_failed']) ? 1 : 0);
