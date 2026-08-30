<?php
/**
 * Bitmomo_AI_Key_Drivers::derive()/rank_and_cap() contract
 * (KEY DRIVERS CUSTOMER-FACING COPY build + PR #61 targeted copy fix).
 *
 * Pure-logic tests: no WordPress runtime, only the one WP function this
 * class actually calls (__()) is stubbed, matching the convention already
 * used by tests/test-bitmomo-ai-regime-metrics.php.
 *
 * This revision hardens coverage per the "describe the market, not the
 * indicator" copy fix: every distinct customer-facing template is asserted
 * against its exact expected text (direction x4 tiers, structure x4 states
 * + the defensive unknown-state fallback, consolidated direction+structure
 * x2, carry x4 tiers, crowding x2 tiers, volatility x3 regimes, and the
 * calm/no-material fallback), and every one of those generated strings is
 * scanned for banned internal-analyst terminology AND forward-looking /
 * conditional-consequence language (Free must describe current state only).
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

/**
 * A hand-built evaluation array that bypasses Bitmomo_AI_Signal_Engine
 * entirely. Used only to exercise defensive code paths (like structure's
 * unknown-state fallback) that the real engine can structurally never
 * produce, since structure_score() only ever emits scores for its 4 known
 * states (or 0/neutral for anything else, which build_candidates() would
 * already filter out before structure_candidate() is even called).
 */
function raw_evaluation($axes) {
    $blank = array('score' => 0, 'status' => 'neutral');
    return array('axes' => array(
        'direction' => array_merge($blank, $axes['direction'] ?? array()),
        'structure' => array_merge($blank, $axes['structure'] ?? array()),
        'carry' => array_merge($blank, $axes['carry'] ?? array()),
        'crowding' => array_merge($blank, $axes['crowding'] ?? array()),
        'volatility' => array_merge(array('status' => 'normal'), $axes['volatility'] ?? array()),
    ));
}

// ============================================================
// Per-template exact-text coverage
// ============================================================

// --- Direction: 4 tiers.
check_kd(
    'direction positive strong: exact expected text',
    array('Momentum harga BTC saat ini cukup kuat ke arah naik.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10))))
);
check_kd(
    'direction positive moderate: exact expected text',
    array('Momentum harga BTC mulai menguat ke arah naik.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('direction' => array('adx' => 22, 'plus_di' => 30, 'minus_di' => 10))))
);
check_kd(
    'direction negative strong: exact expected text',
    array('Momentum harga BTC saat ini cukup kuat ke arah turun.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('direction' => array('adx' => 30, 'plus_di' => 10, 'minus_di' => 30))))
);
check_kd(
    'direction negative moderate: exact expected text',
    array('Momentum harga BTC mulai melemah dan condong ke arah turun.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('direction' => array('adx' => 22, 'plus_di' => 10, 'minus_di' => 30))))
);

// --- Structure: 4 real states + defensive unknown-state fallback.
check_kd(
    'structure breakout_up: exact expected text',
    array('Struktur harga BTC baru saja menembus level penting ke atas.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('structure' => array('state' => 'breakout_up'))))
);
check_kd(
    'structure hh_hl: exact expected text',
    array('Struktur harga BTC jangka pendek masih menunjukkan pola naik.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('structure' => array('state' => 'hh_hl'))))
);
check_kd(
    'structure breakout_down: exact expected text',
    array('Struktur harga BTC baru saja menembus level penting ke bawah.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('structure' => array('state' => 'breakout_down'))))
);
check_kd(
    'structure lh_ll: exact expected text',
    array('Struktur harga BTC jangka pendek masih menunjukkan pola turun.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('structure' => array('state' => 'lh_ll'))))
);
check_kd(
    'structure defensive fallback (unrecognised state, bullish score): exact expected text',
    array('Struktur harga BTC saat ini condong naik.') === Bitmomo_AI_Key_Drivers::derive(raw_evaluation(array('structure' => array('score' => 45, 'status' => 'bullish', 'state' => 'mystery_state'))))
);
check_kd(
    'structure defensive fallback (unrecognised state, bearish score): exact expected text',
    array('Struktur harga BTC saat ini condong turun.') === Bitmomo_AI_Key_Drivers::derive(raw_evaluation(array('structure' => array('score' => -45, 'status' => 'bearish', 'state' => 'mystery_state'))))
);

// --- Direction + structure consolidation: both signs.
check_kd(
    'consolidated direction+structure, bullish: exact expected text',
    array('Momentum dan struktur harga BTC saat ini sama-sama mendukung arah naik.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array(
        'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10),
        'structure' => array('state' => 'breakout_up'),
    )))
);
check_kd(
    'consolidated direction+structure, bearish: exact expected text',
    array('Momentum dan struktur harga BTC saat ini sama-sama menunjukkan tekanan ke arah turun.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array(
        'direction' => array('adx' => 30, 'plus_di' => 10, 'minus_di' => 30),
        'structure' => array('state' => 'breakout_down'),
    )))
);

// --- Carry: 4 tiers (long-side and short-side, moderate and extreme).
check_kd(
    'carry extreme long-skew: exact expected text',
    array('Posisi long sedang sangat dominan di pasar futures BTC.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('carry' => array('funding_rate' => 0.0008, 'basis_pct' => 0.30))))
);
check_kd(
    'carry moderate long-skew: exact expected text',
    array('Trader futures mulai lebih banyak mengambil posisi long.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('carry' => array('funding_rate' => 0.0003, 'basis_pct' => 0.05))))
);
check_kd(
    'carry extreme short-skew: exact expected text',
    array('Posisi short sedang sangat dominan di pasar futures BTC.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('carry' => array('funding_rate' => -0.0008, 'basis_pct' => -0.30))))
);
check_kd(
    'carry moderate short-skew: exact expected text',
    array('Trader futures mulai lebih banyak mengambil posisi short.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('carry' => array('funding_rate' => -0.0003, 'basis_pct' => -0.05))))
);

// --- Crowding: 2 tiers.
check_kd(
    'crowding strong: exact expected text',
    array('Posisi trader di pasar futures BTC saat ini cukup padat, dengan banyak pelaku pasar mengambil posisi yang serupa.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('crowding' => array('oi_change_24h_pct' => 3, 'price_change_24h_pct' => 1, 'global_long_short_ratio' => 0.7, 'taker_buy_sell_ratio' => 1.2))))
);
check_kd(
    'crowding moderate: exact expected text',
    array('Posisi trader di pasar futures BTC saat ini mulai lebih berat ke satu sisi.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('crowding' => array('oi_change_24h_pct' => 3, 'price_change_24h_pct' => 1, 'global_long_short_ratio' => 1.0, 'taker_buy_sell_ratio' => 1.0))))
);

// --- Volatility: 3 regimes.
check_kd(
    'volatility extreme: exact expected text',
    array('Volatilitas BTC saat ini sangat tinggi dan pergerakan harga jauh lebih besar dari biasanya.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('volatility' => array('atr_percentile_1d' => 97, 'regime' => 'extreme'))))
);
check_kd(
    'volatility high: exact expected text',
    array('Volatilitas BTC sedang meningkat dan pergerakan harga menjadi lebih aktif.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('volatility' => array('atr_percentile_1d' => 80, 'regime' => 'high'))))
);
check_kd(
    'volatility low: exact expected text',
    array('Volatilitas BTC masih rendah dan pergerakan harga relatif tenang.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for(array('volatility' => array('atr_percentile_1d' => 10, 'regime' => 'low'))))
);

// --- Calm / no-material fallback.
check_kd(
    'calm fallback: exact expected text',
    array('Pergerakan BTC saat ini relatif tenang dan belum ada faktor yang terlihat dominan.') === Bitmomo_AI_Key_Drivers::derive(evaluation_for())
);

// ============================================================
// Selection / ranking behavior (architecture unchanged by this fix)
// ============================================================

$evaluation = evaluation_for(array(
    'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10),
    'volatility' => array('atr_percentile_1d' => 97, 'regime' => 'extreme'),
));
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
check_kd('multiple material, non-redundant drivers: more than 1 line', count($drivers) > 1);
check_kd('multiple material drivers: strongest (volatility, materiality 90) ranked first', false !== strpos($drivers[0], 'Volatilitas'));

$evaluation = evaluation_for(array('direction' => array('adx' => 17, 'plus_di' => 30, 'minus_di' => 10)));
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
check_kd('weak/sub-threshold direction (ADX under trend threshold) is excluded, not force-included', array('Pergerakan BTC saat ini relatif tenang dan belum ada faktor yang terlihat dominan.') === $drivers);

$evaluation = evaluation_for(array(
    'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10),
    'structure' => array('state' => 'breakout_up'),
));
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
check_kd('same-signed direction+structure consolidate into exactly 1 line', 1 === count($drivers));
check_kd('consolidation: no separate stand-alone structure "menembus" line also present', 0 === count(array_filter($drivers, function ($line) { return false !== strpos($line, 'menembus'); })));

$evaluation = evaluation_for(array(
    'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10),
    'structure' => array('state' => 'lh_ll'),
));
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
$has_momentum_line = (bool) array_filter($drivers, function ($line) { return false !== strpos($line, 'Momentum harga BTC'); });
$has_structure_line = (bool) array_filter($drivers, function ($line) { return false !== strpos($line, 'Struktur harga'); });
check_kd('conflicting direction vs structure: momentum line retained', $has_momentum_line);
check_kd('conflicting direction vs structure: structure line retained (not hidden/merged)', $has_structure_line);
check_kd('conflicting direction vs structure: two separate lines, not one merged claim', 2 === count($drivers));

// Current engine can produce at most 5 candidates: direction+structure
// conflicting (2, since they don't consolidate) + carry + crowding +
// volatility (1 each) = 5. Verify that ceiling directly.
$evaluation = evaluation_for(array(
    'direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10),
    'structure' => array('state' => 'lh_ll'),
    'carry' => array('funding_rate' => 0.0008, 'basis_pct' => 0.30),
    'crowding' => array('oi_change_24h_pct' => 3, 'price_change_24h_pct' => 1, 'global_long_short_ratio' => 0.7, 'taker_buy_sell_ratio' => 1.2),
    'volatility' => array('atr_percentile_1d' => 97, 'regime' => 'extreme'),
));
$drivers = Bitmomo_AI_Key_Drivers::derive($evaluation);
check_kd('current engine max: all 5 axes material + conflicting direction/structure yields exactly 5 drivers (not 6)', 5 === count($drivers));

// ============================================================
// Comprehensive banned-terminology + forward-looking-language scan
// across every distinct template this class can generate.
// ============================================================

$all_sample_evaluations = array(
    evaluation_for(),
    evaluation_for(array('direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10))),
    evaluation_for(array('direction' => array('adx' => 22, 'plus_di' => 30, 'minus_di' => 10))),
    evaluation_for(array('direction' => array('adx' => 30, 'plus_di' => 10, 'minus_di' => 30))),
    evaluation_for(array('direction' => array('adx' => 22, 'plus_di' => 10, 'minus_di' => 30))),
    evaluation_for(array('structure' => array('state' => 'breakout_up'))),
    evaluation_for(array('structure' => array('state' => 'hh_hl'))),
    evaluation_for(array('structure' => array('state' => 'breakout_down'))),
    evaluation_for(array('structure' => array('state' => 'lh_ll'))),
    raw_evaluation(array('structure' => array('score' => 45, 'status' => 'bullish', 'state' => 'mystery_state'))),
    raw_evaluation(array('structure' => array('score' => -45, 'status' => 'bearish', 'state' => 'mystery_state'))),
    evaluation_for(array('direction' => array('adx' => 30, 'plus_di' => 30, 'minus_di' => 10), 'structure' => array('state' => 'breakout_up'))),
    evaluation_for(array('direction' => array('adx' => 30, 'plus_di' => 10, 'minus_di' => 30), 'structure' => array('state' => 'breakout_down'))),
    evaluation_for(array('carry' => array('funding_rate' => 0.0008, 'basis_pct' => 0.30))),
    evaluation_for(array('carry' => array('funding_rate' => 0.0003, 'basis_pct' => 0.05))),
    evaluation_for(array('carry' => array('funding_rate' => -0.0008, 'basis_pct' => -0.30))),
    evaluation_for(array('carry' => array('funding_rate' => -0.0003, 'basis_pct' => -0.05))),
    evaluation_for(array('crowding' => array('oi_change_24h_pct' => 3, 'price_change_24h_pct' => 1, 'global_long_short_ratio' => 0.7, 'taker_buy_sell_ratio' => 1.2))),
    evaluation_for(array('crowding' => array('oi_change_24h_pct' => 3, 'price_change_24h_pct' => 1, 'global_long_short_ratio' => 1.0, 'taker_buy_sell_ratio' => 1.0))),
    evaluation_for(array('volatility' => array('atr_percentile_1d' => 97, 'regime' => 'extreme'))),
    evaluation_for(array('volatility' => array('atr_percentile_1d' => 80, 'regime' => 'high'))),
    evaluation_for(array('volatility' => array('atr_percentile_1d' => 10, 'regime' => 'low'))),
);

$banned_terms = array(
    'pembacaan', 'pembacaan pasar', 'berdasarkan pembacaan', 'assessment',
    'positioning derivatif', 'crowded positioning', 'crowding', 'carry',
    'composite', 'terkonsentrasi', 'kondisi netral',
);
$forward_looking_markers = array(
    'jika', 'apabila', 'berpotensi', 'potensial', 'membuka ruang', 'nantinya',
    'ke depan', 'seandainya', 'akan menembus', 'yang perlu dipantau',
    'trigger', 'skenario', 'invalidasi', 'dilepas', 'unwind',
);

$all_generated_lines = array();
foreach ($all_sample_evaluations as $sample) {
    foreach (Bitmomo_AI_Key_Drivers::derive($sample) as $line) {
        $all_generated_lines[] = $line;
    }
}
$all_generated_lines = array_values(array_unique($all_generated_lines));

check_kd('sample scan covers every distinct template (>= 21 unique lines generated)', count($all_generated_lines) >= 21);

$banned_hits = array();
$forward_looking_hits = array();
foreach ($all_generated_lines as $line) {
    foreach ($banned_terms as $term) {
        if (false !== stripos($line, $term)) {
            $banned_hits[] = $term . ' :: ' . $line;
        }
    }
    foreach ($forward_looking_markers as $marker) {
        if (false !== stripos($line, $marker)) {
            $forward_looking_hits[] = $marker . ' :: ' . $line;
        }
    }
}
check_kd('no banned internal-analyst terminology in ANY generated template (' . count($banned_hits) . ' hits)', empty($banned_hits));
check_kd('no forward-looking / conditional-consequence language in ANY generated template (' . count($forward_looking_hits) . ' hits)', empty($forward_looking_hits));

// A narrow legitimacy check on the forward-looking scan itself: current-
// state Indonesian sentences that happen to share short substrings with
// the marker list (e.g. plain descriptive text) must not be swept up by
// accident — spot check a few known-clean lines individually.
check_kd(
    'forward-looking scan does not false-positive on legitimate current-state text',
    false === stripos('Momentum harga BTC saat ini cukup kuat ke arah naik.', 'jika')
    && false === stripos('Struktur harga BTC jangka pendek masih menunjukkan pola naik.', 'apabila')
);

// ============================================================
// rank_and_cap() pure primitive (unchanged architecture)
// ============================================================

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
check_kd('8 synthetic candidates capped to exactly 6 (generic contract; current engine can never produce 8)', 6 === count($capped));
$kept_keys = array_map(function ($c) { return $c['key']; }, $capped);
check_kd('capped list keeps the 6 highest-materiality candidates (drops keys 1 and 5)', !in_array('1', $kept_keys, true) && !in_array('5', $kept_keys, true));
check_kd('capped list is sorted strictly by descending materiality', array('2', '4', '6', '8', '3', '7') === $kept_keys);

check_kd('Bitmomo_AI_Key_Drivers::MAX_DRIVERS is 6 (generic display cap; current engine max is 5)', 6 === Bitmomo_AI_Key_Drivers::MAX_DRIVERS);

$passed = count(array_filter($checks, function ($row) { return $row[1]; }));
foreach ($checks as $row) {
    printf("[%s] %s\n", $row[1] ? 'PASS' : 'FAIL', $row[0]);
}
printf("\n%d/%d passed.\n", $passed, count($checks));
exit($passed === count($checks) ? 0 : 1);
