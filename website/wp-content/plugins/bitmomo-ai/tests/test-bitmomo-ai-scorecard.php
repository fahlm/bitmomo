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
        'outcome_status' => 'evaluated', 'outcome_direction' => 'correct', 'return_pct' => 1.2,
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
$v1 = $score['versions']['engine-v1 | regime-v1'];
scorecard_check('directional all includes only the compatible version group', 2 === $v1['all']['n']);
scorecard_check('bullish breakdown is separate', 1 === $v1['by_direction']['bullish']['n']);
scorecard_check('neutral edge case is correct inside ±0.5%', 1 === $v1['by_direction']['neutral']['correct']);
scorecard_check('legacy missing edition is explicitly unknown', isset($score['versions']['engine-v1 | unknown-classifier']['by_edition']['unknown']));
scorecard_check('missing regime is explicitly unknown', isset($score['versions']['engine-v1 | unknown-classifier']['by_regime']['unknown']));
scorecard_check('incompatible classifier versions are separated', 'SEPARATED_INCOMPATIBLE_VERSIONS' === $score['version_policy']);
scorecard_check('tiny confidence sample collapses to one bucket', 1 === count($v1['confidence_calibration']));
scorecard_check('tiny confidence bucket is labelled insufficient', 'INSUFFICIENT SAMPLE' === $v1['confidence_calibration'][0]['sample_status']);

$large = [];
for ($i = 0; $i < 36; $i++) {
    $large[] = signal_fixture([
        'generated_at' => sprintf('2026-09-%02dT00:10:00+00:00', 1 + ($i % 28)),
        'confidence' => 30 + ($i % 60),
        'outcome_direction' => $i % 3 ? 'correct' : 'incorrect',
    ]);
}
$large_score = Bitmomo_AI_Scorecard::evaluate($large);
scorecard_check('rolling metric is capped at 30', 30 === $large_score['versions']['engine-v1 | regime-v1']['rolling_30']['n']);
scorecard_check('adequate sample uses four calibration buckets', 4 === count($large_score['versions']['engine-v1 | regime-v1']['confidence_calibration']));

$ranges = [
    ['status' => 'evaluated', 'frozen_original' => true, 'range_low' => 95, 'range_high' => 105, 'reference_price' => 100, 'range_hit' => 'yes', 'breached_low' => 'no', 'breached_high' => 'yes'],
    ['status' => 'evaluated', 'frozen_original' => false, 'range_low' => 95, 'range_high' => 105, 'reference_price' => 100, 'range_hit' => 'yes'],
    ['status' => 'evaluated', 'frozen_original' => true, 'range_low' => 0, 'range_high' => 105, 'reference_price' => 100, 'range_hit' => 'yes'],
];
$range_score = Bitmomo_AI_Scorecard::evaluate([], $ranges)['expected_range']['versions']['unknown-model'];
scorecard_check('only genuinely frozen valid ranges are evaluated', 1 === $range_score['n']);
scorecard_check('range hit is measured', 100.0 === $range_score['range_hit_pct']);
scorecard_check('low and high breaches are separate', 0.0 === $range_score['low_breach_pct'] && 100.0 === $range_score['high_breach_pct']);
scorecard_check('range width is normalized to reference price', 10.0 === $range_score['average_width_pct']);

$regime_signals = [
    signal_fixture(['source_record_id' => 'bitmomo-ai:2026-09-01:morning', 'return_pct' => 1, 'entry_price' => 100, 'high_24h' => 103, 'low_24h' => 99]),
    signal_fixture(['source_record_id' => 'bitmomo-ai:2026-09-02:morning', 'return_pct' => -2, 'entry_price' => 100, 'high_24h' => 101, 'low_24h' => 96]),
];
$regimes = [
    ['as_of' => '2026-09-01 07:10:00', 'source_record_id' => 'bitmomo-ai:regime:2026-09-01:morning', 'regime' => 'expansion', 'classifier_version' => 'regime-v1', 'provenance' => 'recorded_live'],
    ['as_of' => '2026-09-02 07:10:00', 'source_record_id' => 'bitmomo-ai:regime:2026-09-02:morning', 'regime' => 'distribution', 'classifier_version' => 'regime-v1', 'provenance' => 'recorded_live'],
    ['as_of' => '2026-08-01 07:10:00', 'source_record_id' => 'legacy', 'regime' => 'expansion', 'classifier_version' => 'regime-v1', 'provenance' => 'historical_reconstruction'],
];
$regime_score = Bitmomo_AI_Scorecard::evaluate($regime_signals, [], $regimes)['regime_evaluation'];
scorecard_check('regime evaluation excludes reconstructed history', 2 === $regime_score['append_only_n']);
scorecard_check('regime forward return joins genuine source ids', 1 === $regime_score['versions']['regime-v1']['expansion']['n']);
scorecard_check('regime forward volatility uses the same settled 24h window', 4.0 === $regime_score['versions']['regime-v1']['expansion']['average_forward_volatility_pct']);
scorecard_check('transition frequency counts append-only state changes', 1 === $regime_score['transition_n'] && 100.0 === $regime_score['transition_frequency_pct']);

$quality = Bitmomo_AI_Scorecard::evaluate([
    signal_fixture(['outcome_status' => 'evaluated', 'quality_status' => 'stale', 'missing_data' => true]),
    signal_fixture(['outcome_status' => 'pending', 'quality_status' => 'degraded']),
], [], [], [['gate_status' => 'blocked', 'quality_status' => '', 'missing_data' => false]])['data_quality'];
scorecard_check('data quality reports stale rate', 33.3 === $quality['stale_rate_pct']);
scorecard_check('data quality reports blocked/degraded rate', 66.7 === $quality['blocked_degraded_rate_pct']);
scorecard_check('settlement completeness is explicit', 50.0 === $quality['settlement_completeness_pct']);

exit(!empty($GLOBALS['__scorecard_failed']) ? 1 : 0);
