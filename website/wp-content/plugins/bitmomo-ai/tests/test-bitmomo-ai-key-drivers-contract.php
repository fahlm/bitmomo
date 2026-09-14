<?php
/**
 * Bitmomo_AI_Key_Drivers institutional-copy contract.
 *
 * Presentation-only: validates deterministic market-factor wording without
 * changing the signal engine. Run with:
 * php tests/test-bitmomo-ai-key-drivers-contract.php
 */

define('ABSPATH', __DIR__);
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}

require __DIR__ . '/../includes/class-bitmomo-ai-key-drivers.php';

$checks = array();
function check_kd($label, $condition) {
    global $checks;
    $checks[] = array($label, (bool) $condition);
}

function raw_evaluation($axes = array()) {
    $blank = array('score' => 0, 'status' => 'neutral');
    return array('axes' => array(
        'direction' => array_merge($blank, $axes['direction'] ?? array()),
        'structure' => array_merge($blank, $axes['structure'] ?? array()),
        'carry' => array_merge($blank, $axes['carry'] ?? array()),
        'crowding' => array_merge($blank, $axes['crowding'] ?? array()),
        'volatility' => array_merge(array('status' => 'normal'), $axes['volatility'] ?? array()),
    ));
}

function expect_driver($label, $expected, $axes) {
    check_kd($label, array($expected) === Bitmomo_AI_Key_Drivers::derive(raw_evaluation($axes)));
}

// Direction.
expect_driver('strong bullish momentum', 'Momentum harga menunjukkan dorongan bullish yang kuat.', array(
    'direction' => array('score' => 80, 'status' => 'strong_bullish'),
));
expect_driver('moderate bullish momentum', 'Momentum harga menunjukkan penguatan dengan bias bullish.', array(
    'direction' => array('score' => 40, 'status' => 'bullish'),
));
expect_driver('strong bearish momentum', 'Momentum harga menunjukkan tekanan bearish yang kuat.', array(
    'direction' => array('score' => -80, 'status' => 'strong_bearish'),
));
expect_driver('moderate bearish momentum', 'Momentum harga menunjukkan pelemahan dengan bias bearish.', array(
    'direction' => array('score' => -40, 'status' => 'bearish'),
));

// Structure.
expect_driver('breakout structure', 'Struktur harga mencatat breakout di atas level teknikal utama.', array(
    'structure' => array('score' => 70, 'status' => 'strong_bullish', 'state' => 'breakout_up'),
));
expect_driver('higher-high/higher-low structure', 'Struktur harga jangka pendek tetap konstruktif dengan pola higher high dan higher low.', array(
    'structure' => array('score' => 45, 'status' => 'bullish', 'state' => 'hh_hl'),
));
expect_driver('breakdown structure', 'Struktur harga mencatat breakdown di bawah level teknikal utama.', array(
    'structure' => array('score' => -70, 'status' => 'strong_bearish', 'state' => 'breakout_down'),
));
expect_driver('lower-high/lower-low structure', 'Struktur harga jangka pendek tetap lemah dengan pola lower high dan lower low.', array(
    'structure' => array('score' => -45, 'status' => 'bearish', 'state' => 'lh_ll'),
));
expect_driver('unknown bullish structure fallback', 'Struktur harga saat ini menunjukkan bias bullish.', array(
    'structure' => array('score' => 45, 'status' => 'bullish', 'state' => 'unknown'),
));
expect_driver('unknown bearish structure fallback', 'Struktur harga saat ini menunjukkan bias bearish.', array(
    'structure' => array('score' => -45, 'status' => 'bearish', 'state' => 'unknown'),
));

// Consolidation.
expect_driver('bullish momentum + structure consolidation', 'Momentum dan struktur harga sama-sama mengonfirmasi bias bullish.', array(
    'direction' => array('score' => 80, 'status' => 'strong_bullish'),
    'structure' => array('score' => 70, 'status' => 'strong_bullish', 'state' => 'breakout_up'),
));
expect_driver('bearish momentum + structure consolidation', 'Momentum dan struktur harga sama-sama mengonfirmasi bias bearish.', array(
    'direction' => array('score' => -80, 'status' => 'strong_bearish'),
    'structure' => array('score' => -70, 'status' => 'strong_bearish', 'state' => 'breakout_down'),
));

// Carry / futures pricing.
expect_driver('long funding only', 'Biaya funding untuk posisi long berada di atas kondisi normal.', array(
    'carry' => array('score' => -30, 'status' => 'bearish', 'funding_rate' => 0.0003, 'basis_pct' => 0.05, 'data_status' => 'available'),
));
expect_driver('long basis only', 'Futures BTC diperdagangkan dengan premi terhadap harga spot.', array(
    'carry' => array('score' => -30, 'status' => 'bearish', 'funding_rate' => 0.0001, 'basis_pct' => 0.20, 'data_status' => 'available'),
));
expect_driver('long funding + basis', 'Futures BTC diperdagangkan dengan premi terhadap spot, disertai biaya funding long yang meningkat.', array(
    'carry' => array('score' => -70, 'status' => 'strong_bearish', 'funding_rate' => 0.0008, 'basis_pct' => 0.30, 'data_status' => 'available'),
));
expect_driver('short funding only', 'Biaya funding untuk posisi short berada di atas kondisi normal.', array(
    'carry' => array('score' => 30, 'status' => 'bullish', 'funding_rate' => -0.0003, 'basis_pct' => -0.05, 'data_status' => 'available'),
));
expect_driver('short basis only', 'Futures BTC diperdagangkan dengan diskon terhadap harga spot.', array(
    'carry' => array('score' => 30, 'status' => 'bullish', 'funding_rate' => -0.0001, 'basis_pct' => -0.20, 'data_status' => 'available'),
));
expect_driver('short funding + basis', 'Futures BTC diperdagangkan dengan diskon terhadap spot, disertai biaya funding short yang meningkat.', array(
    'carry' => array('score' => 70, 'status' => 'strong_bullish', 'funding_rate' => -0.0008, 'basis_pct' => -0.30, 'data_status' => 'available'),
));

// Explicit carry fail-closed behavior.
$carry_unavailable = Bitmomo_AI_Key_Drivers::derive(raw_evaluation(array(
    'carry' => array('score' => -80, 'status' => 'strong_bearish', 'funding_rate' => 0.0009, 'basis_pct' => 0.35, 'data_status' => 'unavailable'),
    'direction' => array('score' => 80, 'status' => 'strong_bullish'),
)));
check_kd('unavailable carry is suppressed while other material factors remain',
    array('Momentum harga menunjukkan dorongan bullish yang kuat.') === $carry_unavailable
);

// Positioning / crowding facts.
expect_driver('open-interest expansion', 'Open interest futures BTC meningkat, menunjukkan ekspansi posisi terbuka di pasar derivatif.', array(
    'crowding' => array('score' => 30, 'status' => 'bullish', 'oi_change_24h_pct' => 3, 'price_change_24h_pct' => 1, 'global_long_short_ratio' => 1.0, 'taker_buy_sell_ratio' => 1.0),
));
expect_driver('taker buy pressure', 'Taker flow futures menunjukkan tekanan beli yang lebih kuat daripada tekanan jual.', array(
    'crowding' => array('score' => 30, 'status' => 'bullish', 'oi_change_24h_pct' => 0.5, 'price_change_24h_pct' => 0.1, 'global_long_short_ratio' => 1.0, 'taker_buy_sell_ratio' => 1.2),
));
expect_driver('taker sell pressure', 'Taker flow futures menunjukkan tekanan jual yang lebih kuat daripada tekanan beli.', array(
    'crowding' => array('score' => -30, 'status' => 'bearish', 'oi_change_24h_pct' => 0.5, 'price_change_24h_pct' => -0.1, 'global_long_short_ratio' => 1.0, 'taker_buy_sell_ratio' => 0.8),
));
expect_driver('account ratio long', 'Rasio akun long/short menunjukkan proporsi akun lebih condong ke posisi long.', array(
    'crowding' => array('score' => -30, 'status' => 'bearish', 'oi_change_24h_pct' => 0.5, 'price_change_24h_pct' => 0.1, 'global_long_short_ratio' => 1.3, 'taker_buy_sell_ratio' => 1.0),
));
expect_driver('account ratio short', 'Rasio akun long/short menunjukkan proporsi akun lebih condong ke posisi short.', array(
    'crowding' => array('score' => 30, 'status' => 'bullish', 'oi_change_24h_pct' => 0.5, 'price_change_24h_pct' => -0.1, 'global_long_short_ratio' => 0.7, 'taker_buy_sell_ratio' => 1.0),
));

// Volatility.
expect_driver('extreme volatility', 'Volatilitas BTC berada pada level sangat tinggi dengan amplitudo pergerakan jauh di atas kondisi normal.', array(
    'volatility' => array('status' => 'extreme'),
));
expect_driver('high volatility', 'Volatilitas BTC meningkat dengan amplitudo pergerakan di atas kondisi normal.', array(
    'volatility' => array('status' => 'high'),
));
expect_driver('low volatility', 'Volatilitas BTC berada pada level rendah dengan amplitudo pergerakan yang terbatas.', array(
    'volatility' => array('status' => 'low'),
));

expect_driver('calm market fallback', 'Kondisi pasar relatif seimbang dan belum menunjukkan faktor dominan.', array());

// Conflicting direction/structure must remain separate.
$conflict = Bitmomo_AI_Key_Drivers::derive(raw_evaluation(array(
    'direction' => array('score' => 80, 'status' => 'strong_bullish'),
    'structure' => array('score' => -45, 'status' => 'bearish', 'state' => 'lh_ll'),
)));
check_kd('conflicting direction and structure remain visible separately',
    2 === count($conflict)
    && in_array('Momentum harga menunjukkan dorongan bullish yang kuat.', $conflict, true)
    && in_array('Struktur harga jangka pendek tetap lemah dengan pola lower high dan lower low.', $conflict, true)
);

// Ranking remains deterministic and stable on ties.
$ranked = Bitmomo_AI_Key_Drivers::rank_and_cap(array(
    array('key' => 'a', 'materiality' => 10, 'text' => 'a'),
    array('key' => 'b', 'materiality' => 30, 'text' => 'b'),
    array('key' => 'c', 'materiality' => 30, 'text' => 'c'),
    array('key' => 'd', 'materiality' => 20, 'text' => 'd'),
), 3);
check_kd('ranking sorts descending and remains stable on ties',
    array('b', 'c', 'd') === array_values(array_map(function ($item) { return $item['key']; }, $ranked))
);

// Free copy must remain descriptive/current-state only and avoid internal jargon.
$all_lines = array_merge(
    Bitmomo_AI_Key_Drivers::derive(raw_evaluation(array('direction' => array('score' => 80, 'status' => 'strong_bullish')))),
    Bitmomo_AI_Key_Drivers::derive(raw_evaluation(array('carry' => array('score' => -70, 'status' => 'strong_bearish', 'funding_rate' => 0.0008, 'basis_pct' => 0.30, 'data_status' => 'available')))),
    Bitmomo_AI_Key_Drivers::derive(raw_evaluation(array('crowding' => array('score' => 30, 'status' => 'bullish', 'oi_change_24h_pct' => 3, 'price_change_24h_pct' => 1, 'global_long_short_ratio' => 1.0, 'taker_buy_sell_ratio' => 1.2)))),
    Bitmomo_AI_Key_Drivers::derive(raw_evaluation(array('volatility' => array('status' => 'extreme'))))
);
$banned = array('assessment', 'crowding', 'carry', 'composite', 'jika', 'apabila', 'berpotensi', 'skenario', 'invalidasi', 'cukup kuat ke arah', 'menembus level penting');
$banned_hit = false;
foreach ($all_lines as $line) {
    foreach ($banned as $term) {
        if (false !== stripos($line, $term)) $banned_hit = true;
    }
}
check_kd('generated public factors contain no internal or forward-looking language', !$banned_hit);

$pass = 0;
foreach ($checks as $check) {
    list($label, $ok) = $check;
    echo '[' . ($ok ? 'PASS' : 'FAIL') . '] ' . $label . "\n";
    if ($ok) $pass++;
}

echo $pass . '/' . count($checks) . " Key Drivers checks passed\n";
exit($pass === count($checks) ? 0 : 1);
