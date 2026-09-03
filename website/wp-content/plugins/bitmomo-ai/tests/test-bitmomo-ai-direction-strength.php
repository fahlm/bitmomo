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

$theme = file_get_contents(__DIR__ . '/../../../themes/bitmomo-child-v3/template-parts/home-hero.php');
strength_check('homepage consumes canonical direction_strength', strpos($theme, "['direction_strength']") !== false);
strength_check('homepage has no confidence threshold for strength naming', !preg_match('/confidence\s*>?=\s*70\s*\?\s*[\'\"]Strong/', $theme));

$failed = array_filter($checks, function ($row) { return !$row[1]; });
foreach ($checks as $row) echo ($row[1] ? 'PASS' : 'FAIL') . ': ' . $row[0] . PHP_EOL;
if ($failed) exit(1);
echo 'All ' . count($checks) . " direction-strength checks passed.\n";

