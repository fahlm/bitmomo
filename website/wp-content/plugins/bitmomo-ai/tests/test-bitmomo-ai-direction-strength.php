<?php
define('ABSPATH', __DIR__);
require __DIR__ . '/../includes/class-bitmomo-ai-signal-engine.php';

$checks = [];
function strength_check($label, $condition) { global $checks; $checks[] = [$label, (bool) $condition]; }

$cases = [
    -100 => 'strong_bearish', -60 => 'strong_bearish', -59 => 'bearish', -20 => 'bearish',
    -19 => 'neutral', 0 => 'neutral', 19 => 'neutral', 20 => 'bullish', 59 => 'bullish',
    60 => 'strong_bullish', 100 => 'strong_bullish',
];
foreach ($cases as $score => $expected) strength_check("boundary {$score}", Bitmomo_AI_Signal_Engine::direction_strength($score) === $expected);

function strength_input($volatility_regime, $bias_1d) {
    return [
        'close' => 65000,
        'direction' => ['adx' => 30, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'bullish', 'bias_4h' => 'bullish', 'bias_1d' => $bias_1d],
        'carry' => ['funding_rate' => 0, 'basis_pct' => 0],
        'structure' => ['state' => 'range', 'near_support' => 64000, 'near_resistance' => 66000],
        'crowding' => ['oi_change_24h_pct' => 0, 'price_change_24h_pct' => 0],
        'volatility' => ['regime' => $volatility_regime, 'atr_pct_1h' => 1],
        'quality' => ['status' => 'complete'],
    ];
}

$high_confidence = Bitmomo_AI_Signal_Engine::evaluate(strength_input('normal', 'bullish'));
$low_confidence = Bitmomo_AI_Signal_Engine::evaluate(strength_input('extreme', 'bearish'));
strength_check('fixture has identical aggregate score', $high_confidence['score'] === $low_confidence['score']);
strength_check('fixture has different confidence', $high_confidence['confidence'] !== $low_confidence['confidence']);
strength_check('strength is independent from confidence', $high_confidence['direction_strength'] === $low_confidence['direction_strength']);
strength_check('aggregate range is bounded', $high_confidence['score'] >= -100 && $high_confidence['score'] <= 100);

$aligned_input = strength_input('normal', 'bullish');
$aligned_input['direction']['adx'] = 35;
$aligned_input['structure']['state'] = 'breakout_up';
$aligned = Bitmomo_AI_Signal_Engine::evaluate($aligned_input);

$conflicting_input = $aligned_input;
$conflicting_input['structure']['state'] = 'breakout_down';
$conflicting = Bitmomo_AI_Signal_Engine::evaluate($conflicting_input);
strength_check('strong aligned evidence earns more confidence than equally strong opposing evidence', $aligned['confidence'] > $conflicting['confidence']);
strength_check('strong but cancelling evidence cannot be labelled high-confidence', $conflicting['confidence'] < 70);
strength_check('conflicting evidence actually cancels in the directional aggregate', abs($conflicting['score']) < abs($aligned['score']));

$extreme_aligned_input = $aligned_input;
$extreme_aligned_input['volatility']['regime'] = 'extreme';
$extreme_aligned = Bitmomo_AI_Signal_Engine::evaluate($extreme_aligned_input);
strength_check('extreme volatility reduces confidence without changing direction strength', $extreme_aligned['confidence'] < $aligned['confidence'] && $extreme_aligned['direction_strength'] === $aligned['direction_strength']);

$missing_optional_input = $aligned_input;
$missing_optional_input['carry'] = [];
$missing_optional_input['crowding'] = [];
$missing_optional = Bitmomo_AI_Signal_Engine::evaluate($missing_optional_input);
strength_check('missing optional axes never inflate confidence above the fully observed aligned fixture', $missing_optional['confidence'] <= $aligned['confidence']);

$theme = file_get_contents(__DIR__ . '/../../../themes/bitmomo-child-v3/template-parts/home-hero.php');
strength_check('homepage consumes canonical direction_strength', strpos($theme, "['direction_strength']") !== false);
strength_check('homepage has no confidence threshold for strength naming', !preg_match('/confidence\s*>?=\s*70\s*\?\s*[\'\"]Strong/', $theme));

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " direction-strength checks passed.\n";
