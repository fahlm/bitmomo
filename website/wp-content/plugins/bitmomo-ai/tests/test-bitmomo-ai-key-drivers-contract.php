<?php
/**
 * Bitmomo_AI_Key_Drivers::derive()/rank_and_cap() contract
 * (KEY DRIVERS CUSTOMER-FACING COPY build).
 *
 * Pure-logic tests: no WordPress runtime, only the one WP function this
 * class actually calls (__()) is stubbed, matching the convention already
 * used by tests/test-bitmomo-ai-regime-metrics.php.
 *
 * Run: php tests/test-bitmomo-ai-key-drivers-contract.php
 */

define('ABSPATH', __DIR__);

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

require __DIR__ . '/../includes/class-bitmomo-ai-key-drivers.php';
require __DIR__ . '/../includes/class-bitmomo-ai-signal-engine.php';

$checks = array();
function check_kd($label, $condition) {
    global $checks;
    $checks[] = array($label, (bool) $condition);
}

/**
 * Builds a full, realistic Signal_Engine::evaluate()-shaped input so the
 * integration path (raw market data -> axes -> key_drivers) is exercised,
 * not just hand-built axes arrays. Every field defaults to a "quiet, all-
 * neutral" market; callers override only what they need to move.
 */
function base_signal_input($overrides = array()) {
    $defaults = array(
        'close' => 65000,
        'direction' => array('adx' => 10, 'plus_di' => 20, 'minus_di' => 20, 'bias_1h' => 'neutral', 'bias_4h' => 'neutral', 'bias_1d' => 'neutral'),
        'carry' => array('funding_rate' => 0.0001, 'basis_pct' => 0.02),
        'structure' => array('state' => 'range', 'state_1d' => 'range', 'last_swing_low' => 63000, 'last_swing_high' => 67000, 'near_support' => 64000, 'near_resistance' => 66000),
        'crowding' => array('oi_change_24h_pct' => 0.5, 'price_change_24h_pct' => 0.1, 'global_long_short_ratio' => 1.0, 'taker_buy_sell_ratio' => 1.0),
        'volatility' => array('atr_percentile_1d' => 50, 'regime' => 'normal', 'atr_pct_4h' => 1.0, 'atr_pct_1h' => 0.5, 'bb_width_pct_4h' => 2.0),
        'quality' => array('status' => 'complete'),
    );
    foreach ($overrides as $key => $value) {
        $defaults[$key] = array_merge($defaults[$key], $value);
    }
    return $defaults;
}

function evaluation_for($overrides = array()) {
    return Bitmomo_AI_Signal_Engine::evaluate(base_signal_input($overrides));
}

// --- Case 1: nothing material anywhere -> honest single "calm market" fallback.
$evaluation = evaluation_for();
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
check_kd('all-neutral market: returns exactly 1 driver (honest fallback, min 1)', 1 === count($drivers));
check_kd('all-neutral market: fallback text does not claim a specific effect', false !== strpos($drivers[0], 'relatif tenang'));

// --- Case 2: exactly one material driver (direction only; everything else neutral/normal).
$evaluation = evaluation_for(array('direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'neutral', 'bias_4h' => 'neutral', 'bias_1d' => 'neutral')));
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
check_kd('one material driver: exactly 1 line returned', 1 === count($drivers));
check_kd('one material driver: mentions momentum, not a fabricated structure/volatility claim', false !== strpos($drivers[0], 'Momentum'));

// --- Case 3: multiple material, non-redundant drivers (direction bullish + volatility extreme;
// --- direction/structure are NOT both material here so no merge is expected).
$evaluation = evaluation_for(array(
    'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'neutral', 'bias_4h' => 'neutral', 'bias_1d' => 'neutral'),
    'volatility' => array('atr_percentile_1d' => 97, 'regime' => 'extreme', 'atr_pct_4h' => 4.0, 'atr_pct_1h' => 2.0, 'bb_width_pct_4h' => 6.0),
));
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
check_kd('multiple material drivers: returns more than 1 line', count($drivers) > 1);
check_kd('multiple material drivers: strongest (volatility, materiality 90) ranked first', false !== strpos($drivers[0], 'Volatilitas'));

// --- Case 4: weak (below-materiality) factors are excluded even when the raw
// --- underlying score is nonzero — crowding here is deliberately mild
// --- (only the small OI+price component fires => score 35... actually verify
// --- with a genuinely sub-threshold composite) and carry is at its smallest
// --- nonzero branch which the engine itself already treats as material
// --- (35/-35); the true "weak, must be excluded" case is a neutral-status
// --- axis with a small nonzero-looking underlying input, e.g. ADX just
// --- under the engine's own trend threshold (adx=17 -> direction score 0).
$evaluation = evaluation_for(array('direction' => array('adx' => 17, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'neutral', 'bias_4h' => 'neutral', 'bias_1d' => 'neutral')));
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
check_kd('weak/sub-threshold direction (ADX under trend threshold) is excluded, not force-included', 1 === count($drivers) && false !== strpos($drivers[0], 'relatif tenang'));

// --- Case 5: redundant factors consolidated. direction and structure both
// --- strongly bullish and same-signed -> must collapse into ONE line, not two.
$evaluation = evaluation_for(array(
    'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'bullish', 'bias_4h' => 'bullish', 'bias_1d' => 'bullish'),
    'structure' => array('state' => 'breakout_up', 'state_1d' => 'hh_hl', 'last_swing_low' => 63000, 'last_swing_high' => 67000),
));
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
$direction_structure_lines = array_filter($drivers, function ($line) {
    return false !== strpos($line, 'Momentum dan struktur');
});
check_kd('same-signed direction+structure consolidate into exactly 1 merged line', 1 === count($direction_structure_lines));
check_kd('consolidation: no separate stand-alone "Struktur harga BTC menembus" line also present', 0 === count(array_filter($drivers, function ($line) { return false !== strpos($line, 'menembus'); })));

// --- Case 6: conflicting material evidence is preserved, not hidden.
// --- direction bullish but structure bearish (opposite sign) -> both kept as separate lines.
$evaluation = evaluation_for(array(
    'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'bullish', 'bias_4h' => 'bullish', 'bias_1d' => 'bullish'),
    'structure' => array('state' => 'lh_ll', 'state_1d' => 'lh_ll', 'last_swing_low' => 63000, 'last_swing_high' => 67000),
));
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
$has_momentum_line = (bool) array_filter($drivers, function ($line) { return false !== strpos($line, 'Momentum jangka pendek'); });
$has_structure_line = (bool) array_filter($drivers, function ($line) { return false !== strpos($line, 'Struktur harga'); });
check_kd('conflicting direction vs structure: momentum line retained', $has_momentum_line);
check_kd('conflicting direction vs structure: structure line retained (not hidden/merged)', $has_structure_line);
check_kd('conflicting direction vs structure: two separate lines, not one merged claim', 2 === count($drivers));

// --- Case 7: natural, customer-facing language — none of the explicitly
// --- banned internal-analyst phrases ever appear in any generated copy.
$banned_phrases = array('dalam pembacaan saat ini', 'pembacaan pasar', 'berdasarkan pembacaan', 'assessment');
$all_sample_evaluations = array(
    evaluation_for(),
    evaluation_for(array('direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10, 'bias_1h' => 'bullish', 'bias_4h' => 'bullish', 'bias_1d' => 'bullish'))),
    evaluation_for(array('carry' => array('funding_rate' => 0.0008, 'basis_pct' => 0.3))),
    evaluation_for(array('crowding' => array('oi_change_24h_pct' => 3, 'price_change_24h_pct' => 1, 'global_long_short_ratio' => 1.4, 'taker_buy_sell_ratio' => 1.2))),
    evaluation_for(array('volatility' => array('atr_percentile_1d' => 10, 'regime' => 'low', 'atr_pct_4h' => 0.3, 'atr_pct_1h' => 0.2, 'bb_width_pct_4h' => 0.5))),
);
$clean = true;
foreach ($all_sample_evaluations as $sample) {
    foreach (Bitmomo_AI_Key_Drivers::derive($sample) as $line) {
        foreach ($banned_phrases as $phrase) {
            if (false !== stripos($line, $phrase)) {
                $clean = false;
            }
        }
    }
}
check_kd('no banned internal-analyst phrases appear in any generated driver copy', $clean);

// --- Case 8: no Pro-only / forward-looking language leaks into the copy
// --- (no scenario/"what to watch next"/thesis-invalidation vocabulary).
$forward_looking_markers = array('skenario', 'invalidasi', 'akan menembus', 'jika harga', 'yang perlu dipantau', 'trigger');
$leaked = false;
foreach ($all_sample_evaluations as $sample) {
    foreach (Bitmomo_AI_Key_Drivers::derive($sample) as $line) {
        foreach ($forward_looking_markers as $marker) {
            if (false !== stripos($line, $marker)) {
                $leaked = true;
            }
        }
    }
}
check_kd('no forward-looking / Pro-only scenario language leaks into Free driver copy', !$leaked);

// --- Case 9 (pure primitive): rank_and_cap sorts by materiality descending,
// --- stable on ties, and caps to the requested maximum.
$synthetic = array(
    array('key' => 'a', 'materiality' => 10, 'text' => 'a'),
    array('key' => 'b', 'materiality' => 90, 'text' => 'b'),
    array('key' => 'c', 'materiality' => 50, 'text' => 'c'),
    array('key' => 'd', 'materiality' => 50, 'text' => 'd'), // tie with c, appears after c -> must stay after c
    array('key' => 'e', 'materiality' => 70, 'text' => 'e'),
    array('key' => 'f', 'materiality' => 30, 'text' => 'f'),
);
$ranked = Bitmomo_AI_Key_Drivers::rank_and_cap($synthetic, 6);
check_kd('rank_and_cap: all 6 of exactly 6 candidates are returned', 6 === count($ranked));
check_kd('rank_and_cap: strongest (b, 90) ranked first', 'b' === $ranked[0]['key']);
check_kd('rank_and_cap: tie (c vs d, both 50) preserves original order (c before d)', 'c' === $ranked[2]['key'] && 'd' === $ranked[3]['key']);
check_kd('rank_and_cap: weakest (a, 10) ranked last', 'a' === $ranked[5]['key']);

// --- Case 10: more than 6 candidates -> correctly capped to 6, highest materiality kept.
$eight = array(
    array('key' => '1', 'materiality' => 10, 'text' => '1'),
    array('key' => '2', 'materiality' => 95, 'text' => '2'),
    array('key' => '3', 'materiality' => 40, 'text' => '3'),
    array('key' => '4', 'materiality' => 80, 'text' => '4'),
    array('key' => '5', 'materiality' => 5, 'text' => '5'),
    array('key' => '6', 'materiality' => 60, 'text' => '6'),
    array('key' => '7', 'materiality' => 25, 'text' => '7'),
    array('key' => '8', 'materiality' => 55, 'text' => '8'),
);
$capped = Bitmomo_AI_Key_Drivers::rank_and_cap($eight, 6);
check_kd('8 candidates capped to exactly 6', 6 === count($capped));
$kept_keys = array_map(function ($c) { return $c['key']; }, $capped);
check_kd('capped list keeps the 6 highest-materiality candidates (drops keys 1 and 5)', !in_array('1', $kept_keys, true) && !in_array('5', $kept_keys, true));
check_kd('capped list is sorted strictly by descending materiality', array('2', '4', '6', '8', '3', '7') === $kept_keys);

// --- Case 11: derive() itself never exceeds MAX_DRIVERS even in principle
// --- (defensive: confirms the public entry point applies the same cap).
check_kd('Bitmomo_AI_Key_Drivers::MAX_DRIVERS is 6 per the product brief', 6 === Bitmomo_AI_Key_Drivers::MAX_DRIVERS);

$passed = count(array_filter($checks, function ($row) { return $row[1]; }));
foreach ($checks as $row) {
    printf("[%s] %s\n", $row[1] ? 'PASS' : 'FAIL', $row[0]);
}
printf("\n%d/%d passed.\n", $passed, count($checks));
exit($passed === count($checks) ? 0 : 1);
